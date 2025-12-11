# WP Multi-Agent AI Post Generator - Roadmap

**Autor:** [Dante Testa](https://dantetesta.com.br)
**Data de Criação:** 2025-12-11 09:19
**Versão:** 1.0.0

---

## 📌 Visão Geral

Plugin WordPress para geração de artigos profissionais usando sistema multi-agente da OpenAI, com interface visual moderna e opções avançadas de customização textual.

---

## 🎯 Objetivos do Projeto

1. Criar sistema de configuração seguro para API Key da OpenAI
2. Implementar pipeline multi-agente (Interpretador → Escritor → Revisor)
3. Desenvolver interface visual moderna com feedback em tempo real
4. Garantir segurança com criptografia e validações
5. Suportar múltiplos tons de voz e tipos de texto

---

## 📂 Estrutura de Arquivos

```
wp-ai-post-generator/
├── wp-ai-post-generator.php          # Arquivo principal do plugin
├── ROADMAP.md                         # Este arquivo
├── README.md                          # Documentação de uso
├── includes/
│   ├── class-plugin.php               # Classe principal do plugin
│   ├── class-admin.php                # Configurações administrativas
│   ├── class-openai-client.php        # Cliente da API OpenAI
│   ├── class-multi-agent.php          # Sistema multi-agente
│   ├── class-encryption.php           # Criptografia da API Key
│   └── class-ajax-handler.php         # Handlers AJAX
├── assets/
│   ├── css/
│   │   ├── admin.css                  # Estilos do painel admin
│   │   └── modal.css                  # Estilos do modal de geração
│   └── js/
│       ├── admin.js                   # JavaScript do painel
│       └── modal.js                   # JavaScript do modal
└── templates/
    ├── settings-page.php              # Template da página de configurações
    └── generation-modal.php           # Template do modal de geração
```

---

## 🚀 Fases de Desenvolvimento

### Fase 1: Estrutura Base (Estimativa: 30min) ✅
- [x] Criar estrutura de diretórios
- [x] Arquivo principal do plugin com headers
- [x] Classe principal com ativação/desativação
- [x] Sistema de autoload de classes

### Fase 2: Sistema de Configuração (Estimativa: 45min) ✅
- [x] Página de configurações no admin
- [x] Campo para API Key com criptografia
- [x] Seletor de modelo da OpenAI
- [x] Validação e sanitização de inputs
- [x] Armazenamento seguro das opções

### Fase 3: Cliente OpenAI (Estimativa: 45min) ✅
- [x] Classe cliente para API OpenAI
- [x] Método de chat completions
- [x] Tratamento de erros e rate limits
- [x] Logs de requisições

### Fase 4: Sistema Multi-Agente (Estimativa: 1h) ✅
- [x] InterpreterAgent - análise e briefing
- [x] WriterAgent - criação do artigo
- [x] ReviewerAgent - revisão e feedback
- [x] Loop de iteração para ajustes
- [x] Controle de fluxo do pipeline

### Fase 5: Interface do Usuário (Estimativa: 1h30min) ✅
- [x] Botão "Criar Post com IA" na listagem de posts
- [x] Modal de geração com campos de customização
- [x] UI de feedback por etapas (step viewer)
- [x] Exibição de respostas de cada agente
- [x] Animações e micro-interações

### Fase 6: Integração com Posts (Estimativa: 30min) ✅
- [x] Criação de posts como rascunho
- [x] Criação de posts publicados
- [x] Formatação do conteúdo gerado
- [x] Suporte a HTML estruturado

### Fase 7: Segurança e Otimização (Estimativa: 30min) ✅
- [x] Validação de nonce em todas as requisições
- [x] Verificação de capabilities
- [x] Sanitização de inputs
- [x] Escape de outputs
- [x] Arquivos index.php de segurança

### Fase 8: Documentação e Finalização (Estimativa: 30min) ✅
- [x] README completo
- [x] Comentários no código
- [x] ROADMAP atualizado
- [x] Estrutura finalizada

---

## 🛡️ Requisitos de Segurança

| Requisito | Implementação |
|-----------|---------------|
| API Key Criptografada | OpenSSL com chave única |
| Nonce Validation | wp_nonce_field / wp_verify_nonce |
| Capabilities | edit_posts, manage_options |
| Sanitização | sanitize_text_field, wp_kses |
| Escape | esc_html, esc_attr, esc_js |

---

## 🔌 Hooks WordPress Utilizados

- `admin_menu` - Adicionar menu de configurações
- `admin_enqueue_scripts` - Carregar CSS/JS
- `admin_notices` - Exibir notificações
- `wp_ajax_*` - Handlers AJAX
- `plugin_action_links_*` - Links na lista de plugins

---

## 🔄 Workflow do Pipeline Multi-Agente

```
┌─────────────────┐
│  Input Usuário  │
│  (Título, Tom)  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ InterpreterAgent │ ← Briefing estruturado
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   WriterAgent   │ ← Primeira versão do artigo
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌──────────────┐
│  ReviewerAgent  │────▶│ Precisa      │
│                 │     │ Ajustes?     │
└────────┬────────┘     └──────┬───────┘
         │                     │
         │  ┌──────────────────┤
         │  │ SIM              │ NÃO
         │  ▼                  │
         │  WriterAgent        │
         │  (nova versão)      │
         │  │                  │
         │  └──────────────────┤
         │                     │
         ▼                     ▼
┌─────────────────┐
│  Artigo Final   │
│  (Criar Post)   │
└─────────────────┘
```

---

## 📊 Estimativa Total

| Fase | Tempo Estimado |
|------|----------------|
| Fase 1 | 30 min |
| Fase 2 | 45 min |
| Fase 3 | 45 min |
| Fase 4 | 1h |
| Fase 5 | 1h 30min |
| Fase 6 | 30 min |
| Fase 7 | 30 min |
| Fase 8 | 30 min |
| **Total** | **~6h** |

---

## 📝 Changelog

### v1.1.0 (2025-12-11)
- Integração com Rank Math SEO
  - Meta Title, Description, Focus Keywords
  - Limites de caracteres otimizados
  - Slug SEO otimizado
- Geração de 5 Títulos SEO com recomendação
- Preview do artigo antes de salvar
- Geração de Thumbnails com Google Gemini
  - ThumbnailAgent especializado
  - Suporte a formatos 1:1, 3:2, 16:9
  - Anexação automática como imagem destacada
- Toggle para habilitar/desabilitar thumbnail
- Tabs de preview (Conteúdo/SEO)

### v1.0.0 (2025-12-11)
- Versão inicial do plugin
- Sistema multi-agente completo
- Interface visual moderna
- Suporte a múltiplos tons e tipos de texto

---

## 📂 Estrutura de Arquivos Atualizada

```
wp-ai-post-generator/
├── wp-ai-post-generator.php          # Arquivo principal do plugin
├── ROADMAP.md                         # Este arquivo
├── README.md                          # Documentação de uso
├── includes/
│   ├── class-admin.php                # Configurações administrativas
│   ├── class-openai-client.php        # Cliente da API OpenAI
│   ├── class-gemini-client.php        # Cliente da API Gemini (novo)
│   ├── class-multi-agent.php          # Sistema multi-agente + ThumbnailAgent
│   ├── class-encryption.php           # Criptografia das API Keys
│   └── class-ajax-handler.php         # Handlers AJAX
├── assets/
│   ├── css/
│   │   ├── admin.css                  # Estilos do painel admin
│   │   └── modal.css                  # Estilos do modal de geração
│   └── js/
│       ├── admin.js                   # JavaScript do painel
│       └── modal.js                   # JavaScript do modal
```

---

**Plugin desenvolvido por [Dante Testa](https://dantetesta.com.br)**

