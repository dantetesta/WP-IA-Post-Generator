<?php
/**
 * Sistema Multi-Agente com SEO Avançado e Geração de Thumbnails
 * @package WP_AI_Post_Generator
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @updated 2025-12-11 10:25
 */

if (!defined('ABSPATH'))
    exit;

class WPAI_Multi_Agent
{
    private const MAX_ITERATIONS = 3;
    private $openai;
    private $execution_log = [];
    private $params = [];

    public function __construct()
    {
        $this->openai = new WPAI_OpenAI_Client();
    }

    public function run_pipeline($params)
    {
        $this->params = $params;
        $this->execution_log = [];

        if (!$this->openai->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key da OpenAI.', 'wp-ai-post-generator'));
        }

        // Etapa 1: Interpretação
        $this->log_step('interpretation', 'started');
        $briefing = $this->run_interpreter_agent();
        if (is_wp_error($briefing)) {
            $this->log_step('interpretation', 'error', $briefing->get_error_message());
            return $briefing;
        }
        $this->log_step('interpretation', 'completed', $briefing);

        // Etapa 2: Primeira Versão
        $this->log_step('first_draft', 'started');
        $article = $this->run_writer_agent($briefing);
        if (is_wp_error($article)) {
            $this->log_step('first_draft', 'error', $article->get_error_message());
            return $article;
        }
        $this->log_step('first_draft', 'completed', $article);

        // Etapa 3: Revisão
        $iteration = 0;
        $approved = false;
        $reviews = [];

        while (!$approved && $iteration < self::MAX_ITERATIONS) {
            $iteration++;
            $this->log_step('review', 'started', "Iteração {$iteration}");

            $review = $this->run_reviewer_agent($article, $briefing);
            if (is_wp_error($review)) {
                $this->log_step('review', 'error', $review->get_error_message());
                return $review;
            }

            $reviews[] = ['iteration' => $iteration, 'review' => $review];
            $this->log_step('review', 'completed', $review);

            if ($this->is_approved($review)) {
                $approved = true;
                $this->log_step('iteration', 'approved', "Aprovado na iteração {$iteration}");
            } else {
                $this->log_step('rewrite', 'started');
                $article = $this->run_writer_agent($briefing, $review);
                if (is_wp_error($article))
                    return $article;
                $this->log_step('rewrite', 'completed', $article);
            }
        }

        // Etapa 4: Gerar Títulos SEO
        $this->log_step('titles', 'started');
        $titles = $this->run_title_generator($article, $briefing);
        if (is_wp_error($titles)) {
            $this->log_step('titles', 'error', $titles->get_error_message());
            return $titles;
        }
        $this->log_step('titles', 'completed', $titles);

        // Etapa 5: Gerar SEO Metadata
        $this->log_step('seo', 'started');
        $seo_data = $this->run_seo_agent($article, $briefing);
        if (is_wp_error($seo_data)) {
            $this->log_step('seo', 'error', $seo_data->get_error_message());
            return $seo_data;
        }
        $this->log_step('seo', 'completed', $seo_data);

        // Etapa 6: Gerar Prompt de Thumbnail (se solicitado)
        $thumbnail_prompt = null;
        if (!empty($params['generate_thumbnail']) && $params['generate_thumbnail'] === true) {
            $this->log_step('thumbnail', 'started');
            $thumbnail_prompt = $this->run_thumbnail_agent($article, $briefing, $seo_data);
            if (is_wp_error($thumbnail_prompt)) {
                $this->log_step('thumbnail', 'error', $thumbnail_prompt->get_error_message());
                // Não falhar o pipeline por causa da thumbnail
                $thumbnail_prompt = null;
            } else {
                $this->log_step('thumbnail', 'completed', $thumbnail_prompt);
            }
        }

        $this->log_step('final', 'completed');
        return [
            'success' => true,
            'article' => $article,
            'briefing' => $briefing,
            'reviews' => $reviews,
            'iterations' => $iteration,
            'titles' => $titles,
            'seo' => $seo_data,
            'thumbnail_prompt' => $thumbnail_prompt,
            'execution_log' => $this->execution_log,
        ];
    }

    private function run_interpreter_agent()
    {
        $system = <<<PROMPT
Você é o InterpreterAgent, especialista em análise de briefings para criação de conteúdo SEO de alta qualidade.

Sua função é criar um briefing estruturado que resultará em conteúdo que:
- Ranqueia bem no Google (E-E-A-T: Experiência, Expertise, Autoridade, Confiabilidade)
- Atende à intenção de busca do usuário
- Segue as diretrizes de conteúdo útil do Google
- É original, profundo e agrega valor real

Analise o input e crie um briefing completo incluindo:
1. Objetivo principal e intenção de busca
2. Público-alvo e suas dores/necessidades
3. Estrutura ideal com H2/H3 sugeridos
4. Pontos-chave únicos a abordar
5. Palavras-chave principais e LSI (semânticas)
6. Perguntas frequentes relacionadas (para FAQ)
7. Tom e estilo específicos
8. O que evitar (frases clichês, conteúdo raso)

Responda em português brasileiro de forma estruturada.
PROMPT;

        $tone_map = ['Neutro' => 'neutro e objetivo', 'Profissional' => 'profissional e técnico', 'Humanizado' => 'humanizado e empático', 'Jornalístico' => 'jornalístico factual', 'Técnico' => 'técnico especializado', 'Marketing' => 'persuasivo e engajante', 'Storytelling' => 'narrativo envolvente'];
        $type_map = ['Notícia' => 'notícia jornalística atualizada', 'Resumo' => 'resumo informativo completo', 'Artigo' => 'artigo de blog aprofundado', 'Review' => 'review/análise detalhada', 'Tutorial' => 'tutorial passo a passo prático'];
        $person_map = ['Primeira pessoa' => 'primeira pessoa (eu/nós)', 'Segunda pessoa' => 'segunda pessoa (você)', 'Terceira pessoa' => 'terceira pessoa impessoal'];

        $prompt = <<<PROMPT
Crie um briefing SEO completo para:

**TÍTULO SUGERIDO:** {$this->params['desired_title']}
**CONTEXTO/ASSUNTO:** {$this->params['subject_context']}

**ESPECIFICAÇÕES:**
- Tom de voz: {$tone_map[$this->params['tone']]}
- Tipo de texto: {$type_map[$this->params['writing_type']]}
- Pessoa narrativa: {$person_map[$this->params['person_type']]}
- Extensão: aproximadamente {$this->params['word_count']} palavras (conteúdo longo ranqueia melhor)

Lembre-se: O conteúdo deve demonstrar EXPERIÊNCIA real no assunto, seguindo E-E-A-T do Google.
PROMPT;

        $response = $this->openai->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.5]);
        return is_wp_error($response) ? $response : $response['content'];
    }

    private function run_writer_agent($briefing, $feedback = null)
    {
        $system = <<<PROMPT
Você é o WriterAgent, um redator SEO especialista com anos de experiência em criar conteúdo que ranqueia no Google.

**REGRAS OBRIGATÓRIAS PARA SEO:**

1. ESTRUTURA:
   - Use H2 e H3 estrategicamente (palavras-chave nos headings)
   - Parágrafos curtos (2-4 linhas) para melhor leitura
   - Use listas (ul/ol) quando apropriado
   - Inclua uma seção de FAQ com 3-5 perguntas no final

2. CONTEÚDO:
   - Primeira frase deve capturar atenção e conter palavra-chave
   - Demonstre EXPERIÊNCIA real no assunto (E-E-A-T)
   - Seja específico, use dados, exemplos, estatísticas quando possível
   - Responda completamente à intenção de busca
   - Conteúdo original e profundo, não superficial

3. ESCRITA HUMANIZADA:
   - NÃO use clichês de IA: "No mundo atual", "Em um cenário onde", "É importante destacar"
   - NÃO use excesso de advérbios: "realmente", "certamente", "absolutamente"
   - Varie o tamanho das frases naturalmente
   - Use transições naturais entre parágrafos
   - Escreva como um humano especialista falaria

4. FORMATAÇÃO HTML:
   - Use: h2, h3, p, ul, ol, li, strong, em
   - NÃO inclua h1 (título principal)
   - NÃO use tags desnecessárias

5. SEO ON-PAGE:
   - Palavra-chave no primeiro parágrafo
   - Palavras-chave naturalmente distribuídas (densidade 1-2%)
   - Palavras-chave LSI/semânticas relacionadas
   - Subtítulos informativos e com palavras-chave

Responda APENAS com o conteúdo HTML do artigo, sem explicações.
PROMPT;

        $prompt = "**BRIEFING SEO:**\n{$briefing}\n\n";

        if ($feedback) {
            $prompt .= "**FEEDBACK DO REVISOR (ajuste baseado nestas observações):**\n{$feedback}\n\n";
        }

        $prompt .= "Escreva agora o artigo completo otimizado para SEO, seguindo todas as regras.";

        $response = $this->openai->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.7, 'max_tokens' => 4096]);
        return is_wp_error($response) ? $response : $response['content'];
    }

    private function run_reviewer_agent($article, $briefing)
    {
        $system = <<<PROMPT
Você é o ReviewerAgent, um especialista em SEO e qualidade de conteúdo.

Avalie o artigo usando estes critérios:

1. **SEO On-Page (0-10):**
   - Uso adequado de headings (H2, H3)
   - Palavras-chave bem distribuídas
   - Estrutura escaneável

2. **Qualidade E-E-A-T (0-10):**
   - Demonstra experiência no assunto
   - Conteúdo aprofundado e útil
   - Informações precisas

3. **Humanização (0-10):**
   - Linguagem natural (não robótica)
   - Sem clichês de IA
   - Fluidez de leitura

4. **Engajamento (0-10):**
   - Captura atenção inicial
   - Mantém interesse
   - Call-to-action ou conclusão forte

Responda em JSON:
{
    "approved": true/false (aprove se média >= 8),
    "scores": {"seo": X, "eeat": X, "humanization": X, "engagement": X},
    "overall_score": X,
    "issues": ["problema específico 1", "problema específico 2"],
    "suggestions": ["melhoria específica 1", "melhoria específica 2"]
}
PROMPT;

        $prompt = "BRIEFING:\n{$briefing}\n\nARTIGO:\n{$article}\n\nAnalise criticamente.";

        $response = $this->openai->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.3]);
        return is_wp_error($response) ? $response : $response['content'];
    }

    private function run_title_generator($article, $briefing)
    {
        $system = <<<PROMPT
Você é um especialista em copywriting e SEO focado em criar títulos CURTOS e impactantes.

Crie 5 títulos para o artigo seguindo RIGOROSAMENTE estas regras:

**REGRAS DE TÍTULOS (OBRIGATÓRIO):**
1. MÁXIMO 45-55 caracteres (NUNCA exceder 55!)
2. Palavra-chave principal NO INÍCIO
3. Conciso e direto ao ponto
4. Sem palavras desnecessárias (artigos, preposições)
5. Gatilho emocional ou numérico quando possível
6. Evite títulos genéricos ou vagos

**ESTILOS (um de cada):**
- Informativo direto: "Marketing Digital: Guia Completo 2024"
- Lista/Número: "7 Estratégias de SEO para Ranquear"
- Pergunta: "Como Aumentar Vendas Online?"
- Benefício claro: "Dobre seu Tráfego com Essas Técnicas"
- Urgência: "O Que Mudou no SEO em 2024"

IMPORTANTE: Conte os caracteres. Se passar de 55, REESCREVA mais curto.

Responda APENAS em JSON:
{
    "titles": [
        {"title": "Título Curto Aqui", "style": "informativo", "characters": XX},
        {"title": "Título Curto Aqui", "style": "lista", "characters": XX},
        {"title": "Título Curto Aqui", "style": "pergunta", "characters": XX},
        {"title": "Título Curto Aqui", "style": "benefício", "characters": XX},
        {"title": "Título Curto Aqui", "style": "urgência", "characters": XX}
    ],
    "recommended": 0
}

"recommended" é o índice (0-4) do título que mais recomenda.
PROMPT;

        $prompt = "BRIEFING:\n" . substr($briefing, 0, 1000) . "\n\nARTIGO (resumo):\n" . substr($article, 0, 1500) . "\n\nGere 5 títulos profissionais.";

        $response = $this->openai->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.8]);

        if (is_wp_error($response))
            return $response;

        $titles_data = json_decode($response['content'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Tentar extrair JSON do texto
            preg_match('/\{[\s\S]*\}/', $response['content'], $matches);
            if (!empty($matches[0])) {
                $titles_data = json_decode($matches[0], true);
            }
        }

        return $titles_data ?: ['titles' => [['title' => $this->params['desired_title'], 'style' => 'original', 'characters' => strlen($this->params['desired_title'])]], 'recommended' => 0];
    }

    private function run_seo_agent($article, $briefing)
    {
        $system = <<<PROMPT
Você é um especialista em SEO técnico para Rank Math e Google.

Gere metadados SEO CURTOS e OTIMIZADOS seguindo RIGOROSAMENTE estas regras:

**META TITLE (SEO Title):**
- MÁXIMO 50-55 caracteres (NUNCA exceder 55)
- Palavra-chave principal no início
- Conciso e impactante
- NÃO incluir nome do site

**META DESCRIPTION:**
- EXATAMENTE entre 120-145 caracteres (NUNCA exceder 145)
- Palavra-chave no início
- Uma frase clara e direta
- Call-to-action implícito no final

**FOCUS KEYWORD:**
- Palavra-chave principal CURTA (2-3 palavras apenas)
- Termo que as pessoas realmente buscam no Google

**SECONDARY KEYWORDS:**
- 4-5 palavras-chave relacionadas
- Termos curtos e específicos

**SLUG SUGERIDO:**
- Máximo 50 caracteres
- Apenas palavras-chave essenciais
- Sem stop words (de, do, da, para, etc)

**FAQ:**
- 3 perguntas extraídas do conteúdo
- Respostas de 30-50 palavras

IMPORTANTE: Conte os caracteres ANTES de responder. Se exceder, reescreva mais curto.

Responda APENAS em JSON:
{
    "meta_title": "Título curto até 55 chars",
    "meta_description": "Descrição entre 120-145 caracteres exatos",
    "focus_keyword": "keyword curta",
    "secondary_keywords": ["kw1", "kw2", "kw3", "kw4"],
    "slug": "slug-curto-otimizado",
    "faq": [
        {"question": "Pergunta 1?", "answer": "Resposta 1"},
        {"question": "Pergunta 2?", "answer": "Resposta 2"},
        {"question": "Pergunta 3?", "answer": "Resposta 3"}
    ]
}
PROMPT;

        $prompt = "BRIEFING:\n" . substr($briefing, 0, 1000) . "\n\nARTIGO:\n" . substr($article, 0, 2500) . "\n\nGere os metadados SEO perfeitos.";

        $response = $this->openai->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.4]);

        if (is_wp_error($response))
            return $response;

        $seo_data = json_decode($response['content'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            preg_match('/\{[\s\S]*\}/', $response['content'], $matches);
            if (!empty($matches[0])) {
                $seo_data = json_decode($matches[0], true);
            }
        }

        return $seo_data ?: [
            'meta_title' => $this->params['desired_title'],
            'meta_description' => '',
            'focus_keyword' => '',
            'secondary_keywords' => [],
            'faq' => []
        ];
    }

    private function is_approved($review)
    {
        $data = json_decode($review, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['approved']))
            return (bool) $data['approved'];
        return strpos(strtolower($review), '"approved": true') !== false || strpos(strtolower($review), '"approved":true') !== false;
    }

    private function log_step($step, $status, $data = null)
    {
        $this->execution_log[] = ['step' => $step, 'status' => $status, 'data' => $data, 'timestamp' => current_time('mysql')];
    }

    public function get_execution_log()
    {
        return $this->execution_log;
    }

    public function create_post($title, $content, $status = 'draft', $seo_data = [])
    {
        // Preparar dados do post
        $post_data = [
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post($content),
            'post_status' => $status === 'Publicado' ? 'publish' : 'draft',
            'post_author' => get_current_user_id(),
            'post_type' => 'post',
        ];

        // Usar slug otimizado se disponível
        if (!empty($seo_data['slug'])) {
            $post_data['post_name'] = sanitize_title($seo_data['slug']);
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id))
            return $post_id;

        // Meta do plugin
        update_post_meta($post_id, '_wpai_generated', true);
        update_post_meta($post_id, '_wpai_generated_at', current_time('mysql'));

        // Integração com Rank Math SEO
        if (!empty($seo_data)) {
            $this->save_rank_math_seo($post_id, $seo_data);
        }

        return $post_id;
    }

    private function save_rank_math_seo($post_id, $seo_data)
    {
        // Meta Title
        if (!empty($seo_data['meta_title'])) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['meta_title']));
        }

        // Meta Description
        if (!empty($seo_data['meta_description'])) {
            update_post_meta($post_id, 'rank_math_description', sanitize_text_field($seo_data['meta_description']));
        }

        // Focus Keyword + Secondary Keywords
        // Rank Math usa vírgulas SEM espaço para separar keywords
        $all_keywords = [];

        if (!empty($seo_data['focus_keyword'])) {
            $all_keywords[] = sanitize_text_field($seo_data['focus_keyword']);
        }

        if (!empty($seo_data['secondary_keywords']) && is_array($seo_data['secondary_keywords'])) {
            foreach ($seo_data['secondary_keywords'] as $kw) {
                $all_keywords[] = sanitize_text_field($kw);
            }
        }

        if (!empty($all_keywords)) {
            // Rank Math usa vírgulas sem espaço
            update_post_meta($post_id, 'rank_math_focus_keyword', implode(',', $all_keywords));
        }

        // NOTA: FAQ Schema foi removido da integração direta com Rank Math
        // O FAQ já está incluído no conteúdo HTML do artigo gerado pelo WriterAgent
        // Para FAQ Schema, recomenda-se usar o bloco FAQ nativo do Rank Math no editor
        // ou implementar via JSON-LD no frontend quando o plugin Rank Math não estiver ativo

        // Marcar como SEO otimizado
        update_post_meta($post_id, '_wpai_seo_optimized', true);
    }

    /**
     * ThumbnailAgent - Especialista em criar prompts para thumbnails de blog
     */
    private function run_thumbnail_agent($article, $briefing, $seo_data)
    {
        $system = <<<PROMPT
Você é um especialista em design visual e criação de thumbnails para blogs profissionais.

Sua função é criar um PROMPT PERFEITO para gerar uma imagem de destaque (thumbnail) impactante.

**REGRAS PARA O PROMPT:**

1. DESCRIÇÃO VISUAL CLARA:
   - Descreva a cena principal em detalhes
   - Especifique cores, iluminação e atmosfera
   - Mencione elementos visuais relevantes ao tema

2. ESTILO:
   - Fotografia profissional OU ilustração digital de alta qualidade
   - Moderno, limpo e atrativo
   - Adequado para blogs sérios e profissionais

3. EVITAR:
   - Texto na imagem (thumbnails não devem ter texto)
   - Rostos reconhecíveis de pessoas reais
   - Elementos confusos ou muito detalhados
   - Marcas ou logos

4. FOCO:
   - A imagem deve comunicar o tema do artigo instantaneamente
   - Deve ser visualmente atraente em tamanhos pequenos
   - Cores vibrantes mas profissionais

Responda APENAS com o prompt em inglês (Gemini funciona melhor em inglês), formato texto puro, sem aspas ou formatação.
Máximo 100 palavras.
PROMPT;

        $focus_keyword = $seo_data['focus_keyword'] ?? '';
        $meta_desc = $seo_data['meta_description'] ?? '';

        $prompt = <<<PROMPT
Crie um prompt para gerar a thumbnail perfeita para este artigo:

**TEMA PRINCIPAL:** {$this->params['desired_title']}
**PALAVRA-CHAVE:** {$focus_keyword}
**CONTEXTO:** {$meta_desc}
**RESUMO DO ARTIGO:** 
{$this->extract_summary($article)}

Gere o prompt em inglês, focando em criar uma imagem profissional e impactante.
PROMPT;

        $response = $this->openai->chat_completion(
            [['role' => 'user', 'content' => $prompt]],
            ['system' => $system, 'temperature' => 0.7, 'max_tokens' => 300]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        // Limpar e retornar o prompt
        $thumbnail_prompt = trim($response['content']);
        $thumbnail_prompt = preg_replace('/^["\']|["\']$/m', '', $thumbnail_prompt); // Remove aspas

        return $thumbnail_prompt;
    }

    /**
     * Extrai um resumo do artigo para o ThumbnailAgent
     */
    private function extract_summary($article)
    {
        // Remove tags HTML
        $text = wp_strip_all_tags($article);

        // Pega os primeiros 500 caracteres
        $summary = substr($text, 0, 500);

        // Corta na última palavra completa
        $summary = preg_replace('/\s+\S*$/', '', $summary);

        return $summary . '...';
    }
}

