# WP Multi-Agent AI Post Generator

**Versão:** 1.0.0  
**Autor:** [Dante Testa](https://dantetesta.com.br)  
**Criado em:** 2025-12-11 09:19  
**Atualizado em:** 2025-12-11 09:41

---

## 📌 Descrição

Plugin WordPress premium para geração de artigos profissionais usando um sistema multi-agente da OpenAI. O plugin utiliza **cinco agentes especializados** que trabalham em conjunto para produzir conteúdo de alta qualidade, totalmente otimizado para SEO.

### 🤖 Sistema Multi-Agente

| Agente | Função |
|--------|--------|
| **InterpreterAgent** | Analisa o input e cria briefing SEO estruturado |
| **WriterAgent** | Escreve artigo otimizado seguindo E-E-A-T |
| **ReviewerAgent** | Revisa qualidade, humanização e SEO |
| **TitleAgent** | Gera 5 títulos profissionais para escolha |
| **SEOAgent** | Cria metadados completos para Rank Math |

---

## ✨ Novas Funcionalidades v1.0.0

### 🏷️ Geração de 5 Títulos Profissionais
- 5 opções de título com estilos diferentes
- Indicação de título recomendado
- Contagem de caracteres para SERP
- Estilos: informativo, lista, pergunta, benefício, urgência

### 🔍 Integração Completa com Rank Math SEO
- **Meta Title** otimizado (até 60 caracteres)
- **Meta Description** persuasiva (150-160 caracteres)
- **Focus Keyword** principal
- **Secondary Keywords** (palavras-chave LSI)
- **FAQ Schema** para rich snippets
- Tudo salvo automaticamente no Rank Math

### 📈 Prompts Otimizados para SEO
- Conteúdo seguindo E-E-A-T do Google
- Estrutura escaneável (H2, H3, listas)
- Palavras-chave naturalmente distribuídas
- FAQ no final do artigo
- Escrita humanizada (sem clichês de IA)

### 🎨 Design Premium
- Gradientes violeta/índigo modernos
- Animações suaves e micro-interações
- Glassmorphism e sombras premium
- Preview SERP em tempo real
- Interface 100% responsiva

---

## 🚀 Instalação

1. Faça upload da pasta `wp-ai-post-generator` para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Vá para **AI Post Gen** no menu lateral e configure sua API Key
4. Certifique-se que o **Rank Math SEO** está instalado para aproveitar todos os recursos

---

## ⚙️ Configuração

### Obtendo sua API Key

1. Acesse [platform.openai.com](https://platform.openai.com)
2. Crie uma conta ou faça login
3. Vá para API Keys e crie uma nova chave
4. Copie a chave (começa com `sk-`)

### Configurando o Plugin

1. No WordPress, vá para **AI Post Gen**
2. Cole sua API Key no campo correspondente
3. Selecione o modelo desejado
4. Clique em "Salvar Configurações"
5. Use "Testar Conexão" para verificar

---

## 📝 Como Usar

### Passo a Passo

1. Vá para **Posts → Todos os Posts**
2. Clique no botão **"Criar Post com IA"**
3. Preencha os campos:
   - **Título desejado**: Sugestão inicial (será melhorado)
   - **Assunto / Contexto**: Descrição detalhada do tema
   - **Tom de voz**: Neutro, Profissional, Humanizado, etc.
   - **Tipo de texto**: Notícia, Artigo, Review, Tutorial, etc.
   - **Pessoa narrativa**: 1ª, 2ª ou 3ª pessoa
   - **Quantidade de palavras**: 700, 1500 ou 2500
   - **Salvar como**: Rascunho ou Publicado
4. Clique em **"Gerar Artigo"**
5. Acompanhe o progresso no painel lateral
6. **Escolha um dos 5 títulos** sugeridos
7. Verifique o **preview SEO**
8. Clique em **"Salvar Post"**

---

## 🔧 Modelos Disponíveis

| Modelo | Descrição | Recomendação |
|--------|-----------|--------------|
| GPT-4.1 | Mais poderoso e preciso | Artigos complexos |
| GPT-4.1 Mini | Rápido e econômico | Uso diário |
| GPT-o1 | Raciocínio avançado | Análises profundas |
| GPT-o3 Mini | Raciocínio rápido | Equilíbrio |

---

## 🎯 Integração Rank Math SEO

O plugin preenche automaticamente:

```
rank_math_title          → Meta Title otimizado
rank_math_description    → Meta Description persuasiva
rank_math_focus_keyword  → Palavra-chave principal + secundárias
rank_math_schema_FAQPage → Schema FAQ para rich snippets
```

### Preview SERP

O modal mostra exatamente como seu artigo aparecerá no Google:
- URL simulada
- Título SEO (azul)
- Meta description

---

## 🔒 Segurança

| Requisito | Implementação |
|-----------|---------------|
| API Key Criptografada | AES-256-CBC com chave única |
| Nonce Validation | Todas requisições AJAX |
| Capability Checks | edit_posts, manage_options |
| Sanitização | sanitize_text_field, wp_kses_post |
| Escape | esc_html, esc_attr |
| Index Files | Proteção contra listagem |

---

## 📊 Pipeline de Geração

```
┌──────────────────┐
│   Input Usuário  │
│ (Título+Contexto)│
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ 1. Interpreter   │  → Briefing SEO estruturado
│    Agent         │     (E-E-A-T, keywords, FAQ)
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ 2. Writer        │  → Artigo otimizado SEO
│    Agent         │     (H2/H3, FAQ, escaneável)
└────────┬─────────┘
         │
         ▼
┌──────────────────┐     ┌─────────────┐
│ 3. Reviewer      │────▶│ Score < 8?  │
│    Agent         │     └──────┬──────┘
└────────┬─────────┘            │
         │    ┌─────── SIM ─────┤
         │    │                 │ NÃO
         │    ▼                 │
         │  Reescrever          │
         │                      │
         ▼                      ▼
┌──────────────────┐
│ 4. Title         │  → 5 títulos profissionais
│    Generator     │     (com recomendação)
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ 5. SEO           │  → Metadados Rank Math
│    Agent         │     (title, desc, FAQ schema)
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ 6. Usuário       │  → Escolhe título
│    Escolhe       │  → Confirma dados SEO
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│   Post Salvo     │  ← Rank Math preenchido
│   + SEO Pronto   │
└──────────────────┘
```

---

## 🎨 Tons de Voz

| Tom | Descrição | Melhor para |
|-----|-----------|-------------|
| **Neutro** | Objetivo e imparcial | Notícias, resumos |
| **Profissional** | Formal e técnico | B2B, corporativo |
| **Humanizado** | Empático e próximo | Blogs, lifestyle |
| **Jornalístico** | Informativo factual | Portais de notícias |
| **Técnico** | Detalhado especializado | Tutoriais, guides |
| **Marketing** | Persuasivo envolvente | Landing pages, vendas |
| **Storytelling** | Narrativo emocional | Branding, histórias |

---

## 📄 Tipos de Texto

- **Notícia**: Formato jornalístico, pirâmide invertida
- **Resumo**: Síntese objetiva de informações
- **Artigo**: Texto completo com análise profunda
- **Review**: Avaliação crítica detalhada
- **Tutorial**: Passo a passo didático com exemplos

---

## 🔄 Changelog

### v1.0.0 (2025-12-11)
- ✨ Lançamento inicial
- 🤖 Sistema multi-agente completo (5 agentes)
- 🏷️ Geração de 5 títulos profissionais
- 🔍 Integração completa Rank Math SEO
- 📈 Prompts otimizados para E-E-A-T
- 🎨 Interface premium com gradientes
- 📱 Design 100% responsivo
- 🔒 Criptografia AES-256-CBC
- ✅ FAQ Schema para rich snippets

---

## 📞 Suporte

Para suporte ou dúvidas:
- **Site**: [dantetesta.com.br](https://dantetesta.com.br)
- **Email**: contato@dantetesta.com.br

---

## 📜 Licença

GPL v2 ou posterior. Consulte o arquivo LICENSE para mais detalhes.
