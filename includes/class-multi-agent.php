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
    private $ai_client;
    private $text_ai = 'openai';
    private $execution_log = [];
    private $params = [];

    public function __construct()
    {
        // Cliente sera definido no run_pipeline baseado na escolha do usuario
    }

    public function run_pipeline($params)
    {
        $this->params = $params;
        $this->execution_log = [];
        $this->text_ai = $params['text_ai'] ?? 'openai';

        // Inicializa o cliente de IA baseado na escolha
        if ($this->text_ai === 'gemini') {
            $this->ai_client = new WPAI_Gemini_Client();
            if (!$this->ai_client->has_api_key()) {
                return new WP_Error('no_api_key', __('Configure a API Key do Gemini.', 'wp-ai-post-generator'));
            }
        } else {
            $this->ai_client = new WPAI_OpenAI_Client();
            if (!$this->ai_client->has_api_key()) {
                return new WP_Error('no_api_key', __('Configure a API Key da OpenAI.', 'wp-ai-post-generator'));
            }
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

        // Etapa 3: Revisao
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

        // Etapa 3.5: Gerar prompt de thumbnail junto com revisao (otimizacao)
        $thumbnail_prompt = null;
        $thumbnail_data = null;
        $generate_thumb = !empty($params['generate_thumbnail']) && $params['generate_thumbnail'] === true;
        
        if ($generate_thumb) {
            $this->log_step('thumbnail_prompt', 'started');
            $thumbnail_prompt = $this->run_thumbnail_agent($article, $briefing, null);
            if (!is_wp_error($thumbnail_prompt)) {
                $this->log_step('thumbnail_prompt', 'completed', $thumbnail_prompt);
            }
        }

        // Etapa 4: Gerar Titulos SEO
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

        // Etapa 6: Gerar imagem da thumbnail (ja com prompt pronto)
        if ($generate_thumb && !empty($thumbnail_prompt)) {
            $this->log_step('thumbnail_image', 'started');
            $thumbnail_data = $this->generate_thumbnail_image($thumbnail_prompt, $params);
            if (!is_wp_error($thumbnail_data)) {
                $this->log_step('thumbnail_image', 'completed');
            } else {
                $this->log_step('thumbnail_image', 'error', $thumbnail_data->get_error_message());
                $thumbnail_data = null;
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
            'thumbnail_data' => $thumbnail_data,
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

        // Mapeamento de tons
        $tone_map = [
            'auto' => 'escolha o tom mais adequado ao contexto',
            'neutro' => 'neutro e objetivo',
            'profissional' => 'profissional e técnico',
            'informal' => 'informal e descontraído',
            'informativo' => 'informativo e educacional',
            'jornalistico' => 'jornalístico factual e imparcial',
            'marketing' => 'persuasivo e engajante para conversão',
            'energetico' => 'energético e vibrante',
            'amigavel' => 'amigável e acolhedor',
            'serio' => 'sério e formal',
            'otimista' => 'otimista e positivo',
            'pensativo' => 'pensativo e reflexivo',
            'esperancoso' => 'esperançoso e inspirador'
        ];
        
        // Mapeamento de tipos de conteúdo
        $type_map = [
            'auto' => 'escolha o formato mais adequado',
            'artigo' => 'artigo de blog aprofundado e completo',
            'sumario' => 'sumário executivo com pontos-chave',
            'noticia' => 'notícia jornalística atualizada',
            'listicle' => 'listicle com itens numerados e organizados',
            'tutorial' => 'tutorial passo a passo prático',
            'review' => 'review/análise detalhada com prós e contras',
            'entrevista' => 'formato de entrevista com perguntas e respostas',
            'aida' => 'estrutura AIDA (Atenção, Interesse, Desejo, Ação)'
        ];
        
        // Mapeamento de pessoa narrativa
        $person_map = [
            'auto' => 'escolha a pessoa mais adequada',
            '1s' => 'primeira pessoa do singular (eu, meu, minha)',
            '1p' => 'primeira pessoa do plural (nós, nosso, nossa)',
            '2' => 'segunda pessoa (você, seu, sua)',
            '3' => 'terceira pessoa impessoal (ele, ela, eles)'
        ];

        // Obter valores com fallback
        $tone_val = $tone_map[$this->params['tone']] ?? $tone_map['neutro'];
        $type_val = $type_map[$this->params['writing_type']] ?? $type_map['artigo'];
        $person_val = $person_map[$this->params['person_type']] ?? $person_map['3'];

        $prompt = <<<PROMPT
Crie um briefing SEO completo para:

**TÍTULO SUGERIDO:** {$this->params['desired_title']}
**CONTEXTO/ASSUNTO:** {$this->params['subject_context']}

**ESPECIFICAÇÕES:**
- Tom de voz: {$tone_val}
- Tipo de texto: {$type_val}
- Pessoa narrativa: {$person_val}
- Extensão: aproximadamente {$this->params['word_count']} palavras (conteúdo longo ranqueia melhor)

Lembre-se: O conteúdo deve demonstrar EXPERIÊNCIA real no assunto, seguindo E-E-A-T do Google.
PROMPT;

        $response = $this->ai_client->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.5]);
        return is_wp_error($response) ? $response : $response['content'];
    }

    private function run_writer_agent($briefing, $feedback = null)
    {
        $system = <<<PROMPT
Você é o WriterAgent, um redator SEO especialista otimizado para Rank Math SEO.

**REGRAS OBRIGATÓRIAS PARA RANK MATH SEO:**

1. ESTRUTURA OTIMIZADA:
   - OBRIGATÓRIO: Comece com um sumário/índice após o primeiro parágrafo usando:
     <div class="wp-block-rank-math-toc-block"><h2>Índice</h2><nav><ul><li><a href="#secao1">Seção 1</a></li>...</ul></nav></div>
   - Use H2 e H3 com IDs para âncoras (ex: <h2 id="secao1">Título com Palavra-chave</h2>)
   - Parágrafos curtos (2-4 linhas máximo)
   - Use listas (ul/ol) para melhor escaneabilidade
   - Inclua FAQ no final com schema markup

2. PALAVRA-CHAVE (CRÍTICO PARA RANK MATH):
   - Palavra-chave EXATA nos primeiros 10% do conteúdo (primeiro parágrafo)
   - Palavra-chave em pelo menos 2 subtítulos H2/H3
   - Densidade de 0.5-1.5% (aparecer 5-10x em 1500 palavras)
   - Use variações e sinônimos naturalmente

3. LINKS (OBRIGATÓRIO):
   - Inclua 2-3 links EXTERNOS relevantes (fontes confiáveis como Wikipedia, sites .gov, .edu)
     Formato: <a href="URL" target="_blank" rel="dofollow">texto âncora</a>
   - Inclua 1-2 sugestões de links INTERNOS com placeholder:
     [LINK_INTERNO: texto âncora sugerido]

4. IMAGENS (OBRIGATÓRIO):
   - Inclua 2-3 placeholders de imagem com alt text contendo palavra-chave:
     [IMAGEM: descrição detalhada | alt="texto com palavra-chave"]

5. CONTEÚDO E-E-A-T:
   - Demonstre EXPERIÊNCIA real no assunto
   - Use dados, estatísticas e exemplos específicos
   - Cite fontes quando possível
   - Conteúdo profundo e completo

6. ESCRITA HUMANIZADA:
   - PROIBIDO: "No mundo atual", "Em um cenário onde", "É importante destacar"
   - PROIBIDO: excesso de "realmente", "certamente", "absolutamente"
   - Varie tamanho das frases naturalmente
   - Escreva como especialista humano

7. FAQ SCHEMA (NO FINAL):
   <div class="rank-math-faq-block">
   <div class="rank-math-faq-item"><h3 class="rank-math-question">Pergunta com palavra-chave?</h3>
   <div class="rank-math-answer">Resposta completa de 40-60 palavras.</div></div>
   </div>

**FORMATO DE SAÍDA (CRÍTICO):**
- Responda APENAS com o HTML puro do artigo
- NÃO use blocos de código markdown (```html ou ```)
- NÃO inclua explicações antes ou depois do HTML
- O conteúdo deve ser compatível com o editor Gutenberg do WordPress
- Comece diretamente com a primeira tag HTML (ex: <p> ou <div>)
PROMPT;

        $prompt = "**BRIEFING SEO:**\n{$briefing}\n\n";

        if ($feedback) {
            $prompt .= "**FEEDBACK DO REVISOR (ajuste baseado nestas observações):**\n{$feedback}\n\n";
        }

        $prompt .= "Escreva agora o artigo completo otimizado para SEO, seguindo todas as regras.";

        $response = $this->ai_client->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.7, 'max_tokens' => 4096]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Limpa marcacoes markdown do HTML
        $content = $response['content'];
        $content = preg_replace('/^```html\s*/i', '', $content);
        $content = preg_replace('/^```\s*/i', '', $content);
        $content = preg_replace('/\s*```$/i', '', $content);
        $content = trim($content);
        
        return $content;
    }

    private function run_reviewer_agent($article, $briefing)
    {
        $system = <<<PROMPT
Você é o ReviewerAgent, especialista em Rank Math SEO. Avalie usando o CHECKLIST RANK MATH:

**CHECKLIST RANK MATH SEO (verifique cada item):**

1. **SEO BÁSICO:**
   - [ ] Palavra-chave nos primeiros 10% do conteúdo?
   - [ ] Palavra-chave aparece no conteúdo com densidade 0.5-1.5%?
   - [ ] Conteúdo tem 1000+ palavras?

2. **ESTRUTURA:**
   - [ ] Possui Table of Contents/Sumário?
   - [ ] Palavra-chave em pelo menos 1 subtítulo H2/H3?
   - [ ] Parágrafos curtos (2-4 linhas)?

3. **LINKS:**
   - [ ] Possui links externos (DoFollow)?
   - [ ] Possui sugestões de links internos?

4. **IMAGENS:**
   - [ ] Possui placeholders de imagem com alt text?
   - [ ] Alt text contém palavra-chave?

5. **FAQ:**
   - [ ] Possui seção FAQ com schema markup?
   - [ ] Perguntas contêm palavra-chave?

6. **HUMANIZAÇÃO:**
   - [ ] Sem clichês de IA?
   - [ ] Linguagem natural e fluida?

**SCORING:**
- SEO Rank Math (0-10): Baseado no checklist acima
- E-E-A-T (0-10): Experiência, expertise, autoridade
- Humanização (0-10): Naturalidade da escrita
- Engajamento (0-10): Captura e mantém atenção

Responda em JSON:
{
    "approved": true/false (aprove se média >= 8),
    "scores": {"seo": X, "eeat": X, "humanization": X, "engagement": X},
    "overall_score": X,
    "rank_math_checklist": {
        "keyword_in_first_10_percent": true/false,
        "keyword_density_ok": true/false,
        "has_toc": true/false,
        "keyword_in_subheadings": true/false,
        "has_external_links": true/false,
        "has_internal_links": true/false,
        "has_images_with_alt": true/false,
        "has_faq": true/false
    },
    "issues": ["problema específico"],
    "suggestions": ["melhoria específica para Rank Math"]
}
PROMPT;

        $prompt = "BRIEFING:\n{$briefing}\n\nARTIGO:\n{$article}\n\nAnalise criticamente.";

        $response = $this->ai_client->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.3]);
        return is_wp_error($response) ? $response : $response['content'];
    }

    private function run_title_generator($article, $briefing)
    {
        $system = <<<PROMPT
Você é especialista em títulos SEO otimizados para Rank Math.

**REGRAS RANK MATH PARA TÍTULOS (CRÍTICO):**

1. **PALAVRA-CHAVE NO INÍCIO** (obrigatório):
   - A palavra-chave DEVE estar nas primeiras 3-4 palavras
   - Exemplo: "Marketing Digital: 7 Estratégias para 2024"
   - NÃO: "7 Estratégias de Marketing Digital" (keyword no final)

2. **INCLUIR NÚMERO** (obrigatório para 2 dos 4 títulos):
   - Números aumentam CTR em 36%
   - Use: 5, 7, 10, 15, 21 (números ímpares performam melhor)
   - Exemplos: "7 Dicas", "10 Passos", "5 Erros"

3. **LIMITE DE CARACTERES:**
   - MÁXIMO 55 caracteres (ideal: 50-55)
   - Conte os caracteres ANTES de finalizar

4. **FORMATO IDEAL:**
   - [Palavra-chave]: [Número] [Benefício] [Ano/Contexto]
   - "SEO para E-commerce: 7 Técnicas que Funcionam em 2024"

**GERE 4 TÍTULOS:**
1. Com número + keyword no início
2. Com número + keyword no início (variação)
3. Pergunta com keyword no início
4. Benefício direto com keyword no início

Responda APENAS em JSON:
{
    "titles": [
        {"title": "Keyword: 7 Benefícios...", "style": "numero", "characters": XX, "has_number": true, "keyword_position": "inicio"},
        {"title": "Keyword: 10 Passos...", "style": "numero", "characters": XX, "has_number": true, "keyword_position": "inicio"},
        {"title": "Keyword: Como...?", "style": "pergunta", "characters": XX, "has_number": false, "keyword_position": "inicio"},
        {"title": "Keyword: Guia...", "style": "beneficio", "characters": XX, "has_number": false, "keyword_position": "inicio"}
    ],
    "recommended": 0,
    "focus_keyword": "palavra-chave identificada"
}
PROMPT;

        $prompt = "BRIEFING:\n" . substr($briefing, 0, 1000) . "\n\nARTIGO (resumo):\n" . substr($article, 0, 1500) . "\n\nGere 4 títulos profissionais.";

        $response = $this->ai_client->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.8]);

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
Você é especialista em Rank Math SEO. Gere metadados que passem em TODOS os testes do Rank Math.

**CHECKLIST RANK MATH - TODOS OBRIGATÓRIOS:**

**1. META TITLE (SEO Title) - CRÍTICO:**
- Palavra-chave EXATA no INÍCIO (primeiras 3-4 palavras)
- DEVE conter um NÚMERO (ex: "7 Dicas", "10 Passos")
- MÁXIMO 55 caracteres
- Formato: "[Keyword]: [Número] [Benefício]"
- Exemplo: "Marketing Digital: 7 Estratégias Essenciais"

**2. META DESCRIPTION - CRÍTICO:**
- Palavra-chave EXATA no INÍCIO (primeiras palavras)
- Entre 120-145 caracteres (ideal: 140)
- Call-to-action no final
- Exemplo: "Marketing digital explicado! Descubra 7 estratégias comprovadas para aumentar suas vendas online. Confira agora!"

**3. FOCUS KEYWORD:**
- Palavra-chave principal CURTA (2-4 palavras)
- Termo de busca real do Google
- DEVE aparecer no meta_title e meta_description

**4. SLUG/URL - CRÍTICO:**
- DEVE conter a focus_keyword
- Máximo 50 caracteres
- Apenas hífens, sem stop words
- Exemplo: "marketing-digital-estrategias"

**5. SECONDARY KEYWORDS:**
- 4-5 variações/sinônimos da keyword
- Termos LSI relacionados

**6. TAGS:**
- 5 tags relevantes
- Incluir a focus_keyword como tag

**VALIDAÇÃO ANTES DE RESPONDER:**
- [ ] Focus keyword aparece no meta_title? (NO INÍCIO)
- [ ] Focus keyword aparece na meta_description? (NO INÍCIO)
- [ ] Focus keyword aparece no slug?
- [ ] Meta title tem número?
- [ ] Meta title <= 55 caracteres?
- [ ] Meta description entre 120-145 caracteres?

Responda APENAS em JSON:
{
    "meta_title": "Keyword: 7 Benefício em 55 chars",
    "meta_title_chars": XX,
    "meta_description": "Keyword explicada! Descubra... Call-to-action. (120-145 chars)",
    "meta_description_chars": XX,
    "focus_keyword": "keyword principal",
    "focus_keyword_in_title": true,
    "focus_keyword_in_description": true,
    "secondary_keywords": ["kw1", "kw2", "kw3", "kw4", "kw5"],
    "tags": ["focus keyword", "tag2", "tag3", "tag4", "tag5"],
    "slug": "keyword-principal-otimizado",
    "faq": [
        {"question": "Pergunta com keyword?", "answer": "Resposta completa 40-60 palavras."},
        {"question": "Pergunta 2?", "answer": "Resposta 2"},
        {"question": "Pergunta 3?", "answer": "Resposta 3"}
    ]
}
PROMPT;

        $prompt = "BRIEFING:\n" . substr($briefing, 0, 1000) . "\n\nARTIGO:\n" . substr($article, 0, 2500) . "\n\nGere os metadados SEO e TAGS perfeitos.";

        $response = $this->ai_client->chat_completion([['role' => 'user', 'content' => $prompt]], ['system' => $system, 'temperature' => 0.4]);

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
            'tags' => [],
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
     * ThumbnailAgent - Especialista em criar prompts para DALL-E e Gemini Imagen
     */
    private function run_thumbnail_agent($article, $briefing, $seo_data)
    {
        $system = <<<PROMPT
You are an expert AI image prompt engineer specializing in creating professional blog thumbnails using DALL-E 3 and Google Gemini Imagen.

Your task: Create a PERFECT image generation prompt that will produce a stunning, clickable blog thumbnail.

**PROMPT STRUCTURE (follow exactly):**

1. **STYLE PREFIX** (choose one):
   - "Professional editorial photograph, "
   - "Cinematic wide shot, "
   - "High-end commercial photography, "
   - "Hyperrealistic digital art, "
   - "Modern minimalist illustration, "

2. **MAIN SUBJECT**: Describe the central visual element that represents the article topic. Be specific and concrete.

3. **COMPOSITION**: Camera angle, framing (rule of thirds, centered, etc.)

4. **LIGHTING**: Golden hour, soft diffused, dramatic shadows, studio lighting, etc.

5. **COLOR PALETTE**: Specify 2-3 dominant colors that evoke the right mood.

6. **ATMOSPHERE/MOOD**: Professional, inspiring, trustworthy, dynamic, etc.

7. **TECHNICAL SPECS**: "8K resolution, sharp focus, depth of field, professional color grading"

**STRICT RULES:**
- NO text, letters, words, or typography in the image
- NO recognizable faces or real people
- NO logos, brands, or watermarks
- Focus on symbolic/metaphorical representation of the topic
- Image must be impactful at small sizes (thumbnail)
- Use English only

**OUTPUT FORMAT:**
Single paragraph, 80-120 words, no quotes, no bullet points.
PROMPT;

        $focus_keyword = $seo_data['focus_keyword'] ?? $this->params['desired_title'];
        $summary = $this->extract_summary($article);

        $prompt = <<<PROMPT
Create a professional DALL-E/Gemini image prompt for this article:

TITLE: {$this->params['desired_title']}
MAIN TOPIC: {$focus_keyword}
ARTICLE SUMMARY: {$summary}

Generate a detailed, professional image prompt following the exact structure provided. The image should instantly communicate the article's theme and be visually striking as a blog thumbnail.
PROMPT;

        $response = $this->ai_client->chat_completion(
            [['role' => 'user', 'content' => $prompt]],
            ['system' => $system, 'temperature' => 0.8, 'max_tokens' => 400]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        // Limpar e retornar o prompt
        $thumbnail_prompt = trim($response['content']);
        $thumbnail_prompt = preg_replace('/^["\']|["\']$/m', '', $thumbnail_prompt);
        $thumbnail_prompt = preg_replace('/^(Prompt:|Image Prompt:)/i', '', $thumbnail_prompt);

        return trim($thumbnail_prompt);
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

    // Gera a imagem da thumbnail usando o provider selecionado
    private function generate_thumbnail_image($prompt, $params)
    {
        $provider = $params['thumbnail_provider'] ?? 'gemini';
        $format = $params['thumbnail_format'] ?? '16:9';
        
        $result = null;
        
        if ($provider === 'dalle') {
            $openai = new WPAI_OpenAI_Client();
            $size = $this->format_to_dalle_size($format);
            $result = $openai->generate_image($prompt, $size);
        } else {
            // Gemini como padrao
            $gemini = new WPAI_Gemini_Client();
            $result = $gemini->generate_image($prompt, $format);
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Otimiza a imagem (reduz tamanho)
        $optimized = $this->optimize_image_data($result);
        
        return $optimized;
    }

    // Converte formato para tamanho DALL-E
    private function format_to_dalle_size($format)
    {
        $sizes = [
            '1:1' => '1024x1024',
            '16:9' => '1792x1024',
            '9:16' => '1024x1792',
            '4:3' => '1792x1024',
            '3:4' => '1024x1792',
        ];
        return $sizes[$format] ?? '1792x1024';
    }

    // Otimiza dados da imagem (reduz tamanho para preview)
    private function optimize_image_data($image_result)
    {
        if (!isset($image_result['data'])) {
            return $image_result;
        }
        
        $image_data = base64_decode($image_result['data']);
        $mime_type = $image_result['mime_type'] ?? 'image/png';
        
        // Cria imagem a partir dos dados
        $source = imagecreatefromstring($image_data);
        if (!$source) {
            return $image_result;
        }
        
        $orig_width = imagesx($source);
        $orig_height = imagesy($source);
        
        // Redimensiona para max 800px de largura (para preview)
        $max_width = 800;
        if ($orig_width > $max_width) {
            $ratio = $max_width / $orig_width;
            $new_width = $max_width;
            $new_height = (int)($orig_height * $ratio);
            
            $resized = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
            
            // Converte para JPEG com qualidade 85 (menor tamanho)
            ob_start();
            imagejpeg($resized, null, 85);
            $optimized_data = ob_get_clean();
            
            imagedestroy($resized);
            imagedestroy($source);
            
            return [
                'success' => true,
                'data' => base64_encode($optimized_data),
                'data_original' => $image_result['data'],
                'mime_type' => 'image/jpeg',
                'width' => $new_width,
                'height' => $new_height,
            ];
        }
        
        imagedestroy($source);
        return $image_result;
    }
}

