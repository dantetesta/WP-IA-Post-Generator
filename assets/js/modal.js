/**
 * WP AI Post Generator - Modal com Sistema de Abas v2
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @updated 2025-12-11 14:30
 */

(function ($) {
    'use strict';

    const WPAI_Modal = {
        $overlay: null,
        $modal: null,
        isGenerating: false,
        generatedData: null,
        selectedTitle: null,
        generateThumbnail: false,
        thumbnailProvider: 'dalle',
        thumbnailFormat: '1792x1024',
        currentTab: 'form',
        mediaRecorder: null,
        isRecording: false,

        init: function () {
            this.createModal();
            this.bindEvents();
            // Só carrega categorias se o post type suportar
            if (wpaiPostGen.hasCategories) {
                this.loadCategories();
            }
        },

        createModal: function () {
            const hasOpenAI = wpaiPostGen.hasImageGeneration || false;
            const hasGemini = wpaiPostGen.hasGeminiKey || false;
            const dalleFormats = wpaiPostGen.imageFormats || {};
            const geminiFormats = {
                '1:1': { label: '1:1', description: 'Quadrada' },
                '4:3': { label: '4:3', description: 'Paisagem' },
                '16:9': { label: '16:9', description: 'Wide' }
            };

            const modalHtml = `
                <div class="wpai-modal-overlay" id="wpai-modal-overlay">
                    <div class="wpai-modal" role="dialog" aria-modal="true">
                        <!-- Header -->
                        <div class="wpai-modal-header">
                            <h2><span class="dashicons dashicons-superhero-alt"></span> Criar ${wpaiPostGen.currentPostTypeLabel || 'Post'} com IA</h2>
                            <button type="button" class="wpai-modal-close" aria-label="Fechar">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>

                        <!-- Navegação por Abas -->
                        <div class="wpai-tabs">
                            <button class="wpai-tab active" data-tab="form">
                                <span class="dashicons dashicons-edit"></span>
                                <span>Configurar</span>
                            </button>
                            <button class="wpai-tab" data-tab="pipeline">
                                <span class="dashicons dashicons-update"></span>
                                <span>Pipeline</span>
                            </button>
                            <button class="wpai-tab" data-tab="titles" disabled>
                                <span class="dashicons dashicons-tag"></span>
                                <span>Títulos</span>
                            </button>
                            <button class="wpai-tab" data-tab="preview" disabled>
                                <span class="dashicons dashicons-visibility"></span>
                                <span>Preview</span>
                            </button>
                            <button class="wpai-tab" data-tab="seo" disabled>
                                <span class="dashicons dashicons-search"></span>
                                <span>SEO</span>
                            </button>
                            <button class="wpai-tab" data-tab="thumbnail" disabled style="display: none;">
                                <span class="dashicons dashicons-format-image"></span>
                                <span>Imagem</span>
                            </button>
                            <button class="wpai-tab" data-tab="publish" disabled>
                                <span class="dashicons dashicons-category"></span>
                                <span>Publicar</span>
                            </button>
                        </div>

                        <!-- Conteúdo das Abas -->
                        <div class="wpai-tab-content">
                            <!-- Aba: Formulário -->
                            <div class="wpai-tab-panel active" id="wpai-panel-form">
                                <div class="wpai-form-section">
                                    <label for="wpai-title" class="wpai-label">Título desejado</label>
                                    <input type="text" id="wpai-title" class="wpai-input" placeholder="Ex: Guia Completo de Marketing Digital para 2024">
                                </div>

                                <div class="wpai-form-section">
                                    <div class="wpai-label-row">
                                        <label for="wpai-context" class="wpai-label">Assunto / Contexto</label>
                                        <div class="wpai-context-actions">
                                            ${(hasOpenAI || hasGemini) ? '<button type="button" class="wpai-icon-btn" id="wpai-voice-btn" title="Gravar voz"><span class="dashicons dashicons-microphone"></span></button>' : ''}
                                            <button type="button" class="wpai-icon-btn" id="wpai-improve-btn" title="Melhorar com IA">
                                                <span class="dashicons dashicons-star-filled"></span>
                                                <span class="btn-label">IA</span>
                                            </button>
                                        </div>
                                    </div>
                                    <textarea id="wpai-context" class="wpai-textarea" placeholder="Descreva o tema, público-alvo, palavras-chave...${hasOpenAI ? ' ou use o microfone para ditar' : ''}"></textarea>
                                    <div class="wpai-context-info">
                                        <span id="wpai-char-count">0</span>/500 caracteres
                                    </div>
                                </div>

                                <div class="wpai-form-row-compact">
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Tom</label>
                                        <select id="wpai-tone" class="wpai-select-compact">
                                            <option value="auto">⚡ Auto</option>
                                            <option value="neutro" selected>🎯 Neutro</option>
                                            <option value="profissional">💼 Profissional</option>
                                            <option value="informal">👕 Informal</option>
                                            <option value="informativo">ℹ️ Informativo</option>
                                            <option value="jornalistico">📰 Jornalístico</option>
                                            <option value="marketing">🚀 Marketing</option>
                                            <option value="energetico">⚡ Energético</option>
                                            <option value="amigavel">😊 Amigável</option>
                                            <option value="serio">😐 Sério</option>
                                            <option value="otimista">🤩 Otimista</option>
                                            <option value="pensativo">🤔 Pensativo</option>
                                            <option value="esperancoso">🤞 Esperançoso</option>
                                        </select>
                                    </div>
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Tipo</label>
                                        <select id="wpai-type" class="wpai-select-compact">
                                            <option value="auto">⚡ Auto</option>
                                            <option value="artigo" selected>📝 Artigo</option>
                                            <option value="sumario">📋 Sumário</option>
                                            <option value="noticia">📢 Notícia</option>
                                            <option value="listicle">📊 Listicle</option>
                                            <option value="tutorial">📚 Tutorial</option>
                                            <option value="review">⭐ Review</option>
                                            <option value="entrevista">🎤 Entrevista</option>
                                            <option value="aida">🎯 AIDA</option>
                                        </select>
                                    </div>
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Pessoa</label>
                                        <select id="wpai-person" class="wpai-select-compact">
                                            <option value="auto">⚡ Auto</option>
                                            <option value="1s">1ª Singular</option>
                                            <option value="1p">1ª Plural</option>
                                            <option value="2">2ª Pessoa</option>
                                            <option value="3" selected>3ª Pessoa</option>
                                        </select>
                                    </div>
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Palavras</label>
                                        <select id="wpai-words" class="wpai-select-compact">
                                            <option value="700">~700</option>
                                            <option value="1500" selected>~1500</option>
                                            <option value="2500">~2500</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Grid 2 colunas: IA Texto + Imagem -->
                                <div class="wpai-ai-grid">
                                    <!-- Coluna Esquerda: IA para Texto -->
                                    <div class="wpai-ai-col">
                                        ${(hasOpenAI || hasGemini) ? `
                                        <label class="wpai-label-sm">IA para Texto</label>
                                        <div class="wpai-ai-options">
                                            ${hasOpenAI ? `
                                            <label class="wpai-ai-option available ${hasOpenAI ? 'selected' : ''}" data-ai="openai">
                                                <input type="radio" name="wpai-text-ai" value="openai" ${hasOpenAI ? 'checked' : ''}>
                                                <span class="wpai-ai-icon">🤖</span>
                                                <span class="wpai-ai-name">OpenAI</span>
                                                <span class="wpai-ai-model">GPT-4</span>
                                            </label>
                                            ` : ''}
                                            ${hasGemini ? `
                                            <label class="wpai-ai-option available ${!hasOpenAI ? 'selected' : ''}" data-ai="gemini">
                                                <input type="radio" name="wpai-text-ai" value="gemini" ${!hasOpenAI ? 'checked' : ''}>
                                                <span class="wpai-ai-icon">✨</span>
                                                <span class="wpai-ai-name">Gemini</span>
                                                <span class="wpai-ai-model">2.5 Flash</span>
                                            </label>
                                            ` : ''}
                                        </div>
                                        ` : `
                                        <div class="wpai-ai-warning">
                                            <span class="dashicons dashicons-warning"></span>
                                            <span>Configure API Key</span>
                                        </div>
                                        `}
                                    </div>
                                    
                                    <!-- Coluna Direita: IA para Imagem -->
                                    <div class="wpai-ai-col wpai-img-col">
                                        <div class="wpai-img-header">
                                            <label class="wpai-switch-mini">
                                                <input type="checkbox" id="wpai-generate-thumbnail">
                                                <span class="wpai-slider-mini"></span>
                                            </label>
                                            <span class="wpai-label-sm">🖼️ Imagem</span>
                                        </div>
                                        <div class="wpai-img-options" id="wpai-thumb-options">
                                            <div class="wpai-provider-mini">
                                                <label class="wpai-prov-opt ${hasOpenAI ? '' : 'off'}" data-provider="dalle">
                                                    <input type="radio" name="wpai-provider" value="dalle" ${hasOpenAI ? 'checked' : 'disabled'}>
                                                    <span>DALL-E</span>
                                                </label>
                                                <label class="wpai-prov-opt ${hasGemini ? '' : 'off'}" data-provider="gemini">
                                                    <input type="radio" name="wpai-provider" value="gemini" ${!hasOpenAI && hasGemini ? 'checked' : ''} ${hasGemini ? '' : 'disabled'}>
                                                    <span>Gemini</span>
                                                </label>
                                            </div>
                                            <div class="wpai-size-mini" id="wpai-dalle-formats">
                                                <label class="wpai-sz" data-format="1024x1024"><input type="radio" name="wpai-format-dalle" value="1024x1024"><span>1:1</span></label>
                                                <label class="wpai-sz sel" data-format="1792x1024"><input type="radio" name="wpai-format-dalle" value="1792x1024" checked><span>16:9</span></label>
                                                <label class="wpai-sz" data-format="1024x1792"><input type="radio" name="wpai-format-dalle" value="1024x1792"><span>9:16</span></label>
                                            </div>
                                            <div class="wpai-size-mini" id="wpai-gemini-formats" style="display: none;">
                                                <label class="wpai-sz" data-format="1:1"><input type="radio" name="wpai-format-gemini" value="1:1"><span>1:1</span></label>
                                                <label class="wpai-sz sel" data-format="4:3"><input type="radio" name="wpai-format-gemini" value="4:3" checked><span>4:3</span></label>
                                                <label class="wpai-sz" data-format="16:9"><input type="radio" name="wpai-format-gemini" value="16:9"><span>16:9</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aba: Pipeline -->
                            <div class="wpai-tab-panel" id="wpai-panel-pipeline">
                                <div class="wpai-pipeline-list">
                                    <div class="wpai-step pending" data-step="interpretation">
                                        <div class="wpai-step-num"><span>1</span><svg class="wpai-progress-ring" viewBox="0 0 60 60"><circle class="wpai-progress-ring-bg" cx="30" cy="30" r="28"/><circle class="wpai-progress-ring-fill" cx="30" cy="30" r="28"/></svg></div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Interpretação</div>
                                        </div>
                                        <div class="wpai-step-ai" data-ai-type="text"></div>
                                    </div>
                                    <div class="wpai-step pending" data-step="first_draft">
                                        <div class="wpai-step-num"><span>2</span><svg class="wpai-progress-ring" viewBox="0 0 60 60"><circle class="wpai-progress-ring-bg" cx="30" cy="30" r="28"/><circle class="wpai-progress-ring-fill" cx="30" cy="30" r="28"/></svg></div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Escrita</div>
                                        </div>
                                        <div class="wpai-step-ai" data-ai-type="text"></div>
                                    </div>
                                    <div class="wpai-step pending" data-step="review">
                                        <div class="wpai-step-num"><span>3</span><svg class="wpai-progress-ring" viewBox="0 0 60 60"><circle class="wpai-progress-ring-bg" cx="30" cy="30" r="28"/><circle class="wpai-progress-ring-fill" cx="30" cy="30" r="28"/></svg></div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Revisão</div>
                                        </div>
                                        <div class="wpai-step-ai" data-ai-type="text"></div>
                                    </div>
                                    <div class="wpai-step pending" data-step="titles">
                                        <div class="wpai-step-num"><span>4</span><svg class="wpai-progress-ring" viewBox="0 0 60 60"><circle class="wpai-progress-ring-bg" cx="30" cy="30" r="28"/><circle class="wpai-progress-ring-fill" cx="30" cy="30" r="28"/></svg></div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Títulos SEO</div>
                                        </div>
                                        <div class="wpai-step-ai" data-ai-type="text"></div>
                                    </div>
                                    <div class="wpai-step pending" data-step="seo">
                                        <div class="wpai-step-num"><span>5</span><svg class="wpai-progress-ring" viewBox="0 0 60 60"><circle class="wpai-progress-ring-bg" cx="30" cy="30" r="28"/><circle class="wpai-progress-ring-fill" cx="30" cy="30" r="28"/></svg></div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">SEO & Rank Math</div>
                                        </div>
                                        <div class="wpai-step-ai" data-ai-type="text"></div>
                                    </div>
                                    <div class="wpai-step pending" data-step="thumbnail" style="display: none;">
                                        <div class="wpai-step-num"><span>6</span><svg class="wpai-progress-ring" viewBox="0 0 60 60"><circle class="wpai-progress-ring-bg" cx="30" cy="30" r="28"/><circle class="wpai-progress-ring-fill" cx="30" cy="30" r="28"/></svg></div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Thumbnail</div>
                                        </div>
                                        <div class="wpai-step-ai" data-ai-type="image"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aba: Títulos -->
                            <div class="wpai-tab-panel" id="wpai-panel-titles">
                                <div class="wpai-panel-header">
                                    <h3>Escolha o Título</h3>
                                    <span class="wpai-badge">4 sugestões SEO</span>
                                </div>
                                <div class="wpai-titles-list" id="wpai-titles-list"></div>
                            </div>

                            <!-- Aba: Publicação -->
                            <div class="wpai-tab-panel" id="wpai-panel-publish">
                                <div class="wpai-panel-header">
                                    <h3>Opções de Publicação</h3>
                                    <span class="wpai-badge wpai-post-type-badge">${wpaiPostGen.currentPostTypeLabel || 'Post'}</span>
                                </div>
                                <div class="wpai-publish-options">
                                    <div class="wpai-options-grid">
                                        <div class="wpai-option-item wpai-category-wrapper" ${wpaiPostGen.hasCategories ? '' : 'style="display:none;"'}>
                                            <label class="wpai-label">📁 Categoria</label>
                                            <select id="wpai-category" class="wpai-select-compact">
                                                <option value="0">Sem categoria</option>
                                            </select>
                                        </div>
                                        <div class="wpai-option-item">
                                            <label class="wpai-label">📋 Status</label>
                                            <select id="wpai-publish-status" class="wpai-select-compact">
                                                <option value="draft">Rascunho</option>
                                                <option value="publish">Publicar</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="wpai-tags-section" ${wpaiPostGen.hasTags ? '' : 'style="display:none;"'}>
                                        <label class="wpai-label">🏷️ Tags sugeridas pela IA</label>
                                        <div class="wpai-tags-list" id="wpai-tags-list"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aba: Preview -->
                            <div class="wpai-tab-panel" id="wpai-panel-preview">
                                <div class="wpai-panel-header">
                                    <h3>Preview do Artigo</h3>
                                </div>
                                <div class="wpai-article-content" id="wpai-article-content"></div>
                            </div>

                            <!-- Aba: SEO -->
                            <div class="wpai-tab-panel" id="wpai-panel-seo">
                                <div class="wpai-panel-header">
                                    <h3>Dados SEO</h3>
                                    <span class="wpai-badge wpai-badge-success">Rank Math</span>
                                </div>
                                <div class="wpai-seo-grid">
                                    <div class="wpai-serp-preview">
                                        <div class="wpai-serp-label">Preview Google</div>
                                        <div class="wpai-serp-url" id="wpai-serp-url">seusite.com.br › artigo</div>
                                        <div class="wpai-serp-title" id="wpai-serp-title">Título SEO</div>
                                        <div class="wpai-serp-desc" id="wpai-serp-desc">Descrição meta...</div>
                                    </div>
                                    <div class="wpai-seo-data">
                                        <div class="wpai-seo-item">
                                            <label>Focus Keyword</label>
                                            <span id="wpai-seo-focus">-</span>
                                        </div>
                                        <div class="wpai-seo-item">
                                            <label>Keywords Secundárias</label>
                                            <div class="wpai-seo-keywords" id="wpai-seo-keywords"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aba: Thumbnail -->
                            <div class="wpai-tab-panel" id="wpai-panel-thumbnail">
                                <div class="wpai-panel-header">
                                    <h3>Thumbnail IA</h3>
                                    <button type="button" class="wpai-btn-small" id="wpai-regenerate-thumb-btn">
                                        <span class="dashicons dashicons-update"></span> Regenerar
                                    </button>
                                </div>
                                <div class="wpai-thumb-prompt" id="wpai-thumb-prompt">
                                    <label>Prompt <small>(edite se necessário)</small>:</label>
                                    <textarea id="wpai-thumb-prompt-text" class="wpai-prompt-textarea" rows="3" placeholder="Prompt para geração da imagem..."></textarea>
                                </div>
                                <div class="wpai-thumb-result" id="wpai-thumb-result">
                                    <div class="wpai-thumb-loading" id="wpai-thumb-loading" style="display: none;">
                                        <div class="wpai-img-animation">
                                            <div class="wpai-img-frame large">
                                                <span class="dashicons dashicons-format-image"></span>
                                            </div>
                                            <div class="wpai-img-frame small">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                            </div>
                                        </div>
                                        <div class="wpai-size-indicator">
                                            <span class="size-from">~3 MB</span>
                                            <span class="arrow">→</span>
                                            <span class="size-to">~150 KB</span>
                                        </div>
                                        <div class="wpai-process-steps">
                                            <div class="wpai-process-step active" data-step="generate">
                                                <span class="step-icon">🎨</span>
                                                <span class="step-label">Gerando</span>
                                            </div>
                                            <div class="wpai-process-step" data-step="optimize">
                                                <span class="step-icon">⚡</span>
                                                <span class="step-label">Otimizando</span>
                                            </div>
                                            <div class="wpai-process-step" data-step="save">
                                                <span class="step-icon">💾</span>
                                                <span class="step-label">Salvando</span>
                                            </div>
                                        </div>
                                        <p class="status-text" id="wpai-thumb-status">Gerando imagem com IA...</p>
                                    </div>
                                    <div class="wpai-thumb-image" id="wpai-thumb-image" style="display: none;">
                                        <img src="" id="wpai-thumb-img" alt="Thumbnail">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="wpai-modal-footer">
                            <div class="wpai-footer-info">
                                <span class="dashicons dashicons-info"></span>
                                <span id="wpai-footer-text">Preencha os campos e clique em Gerar.</span>
                                <span id="wpai-footer-ai" class="wpai-footer-ai-badge" style="display: none;"></span>
                            </div>
                            <div class="wpai-footer-actions">
                                <div class="wpai-result-links" id="wpai-result-links" style="display: none;">
                                    <a href="#" class="wpai-link-edit" target="_blank">Editar</a>
                                    <a href="#" class="wpai-link-view" target="_blank">Ver</a>
                                </div>
                                <button type="button" class="wpai-btn-secondary" id="wpai-btn-cancel">Cancelar</button>
                                <button type="button" class="wpai-btn-primary" id="wpai-btn-main">
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <span>Gerar Artigo</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            this.$overlay = $('#wpai-modal-overlay');
            this.$modal = this.$overlay.find('.wpai-modal');
        },

        bindEvents: function () {
            const self = this;

            // Open modal
            $(document).on('click', '.wpai-generate-btn', function (e) {
                e.preventDefault();
                self.open();
            });

            // Close modal
            this.$overlay.on('click', '.wpai-modal-close, #wpai-btn-cancel', function () {
                if (!self.isGenerating) {
                    self.close();
                }
            });

            // Close on overlay click
            this.$overlay.on('click', function (e) {
                if (e.target === this && !self.isGenerating) {
                    self.close();
                }
            });

            // Tab navigation
            this.$overlay.on('click', '.wpai-tab:not([disabled])', function () {
                const tab = $(this).data('tab');
                self.switchTab(tab);
            });

            // Main button
            this.$overlay.on('click', '#wpai-btn-main', function () {
                const $btn = $(this);
                if ($btn.hasClass('wpai-btn-save')) {
                    self.savePost();
                } else {
                    self.generate();
                }
            });

            // Title selection
            this.$overlay.on('click', '.wpai-title-option', function () {
                self.selectTitle($(this));
            });

            // Thumbnail toggle
            this.$overlay.on('change', '#wpai-generate-thumbnail', function () {
                self.generateThumbnail = $(this).is(':checked');
                $('#wpai-thumb-options').toggleClass('show', self.generateThumbnail);
                $('[data-step="thumbnail"]').toggle(self.generateThumbnail);
                $('[data-tab="thumbnail"]').toggle(self.generateThumbnail);
            });

            // Text AI selection (clique no card)
            this.$overlay.on('click', '.wpai-ai-option', function () {
                const $option = $(this);
                const $input = $option.find('input[name="wpai-text-ai"]');
                
                // Marca o radio button
                $input.prop('checked', true);
                
                // Atualiza visual
                $('.wpai-ai-option').removeClass('selected');
                $option.addClass('selected');
                
                console.log('WPAI: Text AI selecionada:', $input.val());
            });

            // Provider selection (novo layout minimalista)
            this.$overlay.on('change', 'input[name="wpai-provider"]', function () {
                self.thumbnailProvider = $(this).val();

                // Esconde todos os formatos primeiro
                $('#wpai-dalle-formats, #wpai-gemini-formats').hide();

                if (self.thumbnailProvider === 'dalle') {
                    $('#wpai-dalle-formats').show();
                    self.thumbnailFormat = $('input[name="wpai-format-dalle"]:checked').val();
                } else if (self.thumbnailProvider === 'gemini') {
                    $('#wpai-gemini-formats').show();
                    self.thumbnailFormat = $('input[name="wpai-format-gemini"]:checked').val();
                }
            });

            // Format selection (novo layout minimalista)
            this.$overlay.on('change', 'input[name="wpai-format-dalle"], input[name="wpai-format-gemini"]', function () {
                $(this).closest('.wpai-size-mini').find('.wpai-sz').removeClass('sel');
                $(this).closest('.wpai-sz').addClass('sel');
                self.thumbnailFormat = $(this).val();
            });

            // Character count
            this.$overlay.on('input', '#wpai-context', function () {
                const count = $(this).val().length;
                $('#wpai-char-count').text(count);
                if (count > 500) {
                    $('#wpai-char-count').css('color', '#ef4444');
                } else {
                    $('#wpai-char-count').css('color', '');
                }
            });

            // Voice recording
            this.$overlay.on('click', '#wpai-voice-btn', function () {
                self.toggleVoiceRecording();
            });

            // Improve description with AI
            this.$overlay.on('click', '#wpai-improve-btn', function () {
                self.improveDescription();
            });

            // Regenerate thumbnail button (usa prompt editado)
            this.$overlay.on('click', '#wpai-regenerate-thumb-btn', function () {
                const editedPrompt = $('#wpai-thumb-prompt-text').val().trim();
                if (editedPrompt) {
                    self.regenerateThumbnail(editedPrompt);
                } else {
                    self.showToast('Digite um prompt para gerar a imagem.', 'error');
                }
            });
        },

        toggleVoiceRecording: function () {
            const self = this;
            const $btn = $('#wpai-voice-btn');

            if (this.isRecording) {
                // Stop recording
                if (this.mediaRecorder) {
                    this.mediaRecorder.stop();
                }
                this.isRecording = false;
                $btn.removeClass('recording');
                $btn.find('.dashicons').removeClass('dashicons-controls-pause').addClass('dashicons-microphone');
            } else {
                // Verificar se está em contexto seguro (HTTPS ou localhost)
                const isSecure = window.isSecureContext || location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1' || location.hostname.endsWith('.local');

                if (!isSecure) {
                    this.showToast('Gravação requer HTTPS. Use o SSL do Local Sites.', 'error');
                    return;
                }

                // Verificar API
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.showToast('API de gravação não disponível neste navegador.', 'error');
                    return;
                }

                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then(function (stream) {
                        self.isRecording = true;
                        $btn.addClass('recording');
                        $btn.find('.dashicons').removeClass('dashicons-microphone').addClass('dashicons-controls-pause');

                        const chunks = [];
                        self.mediaRecorder = new MediaRecorder(stream);

                        self.mediaRecorder.ondataavailable = function (e) {
                            chunks.push(e.data);
                        };

                        self.mediaRecorder.onstop = function () {
                            stream.getTracks().forEach(track => track.stop());
                            const blob = new Blob(chunks, { type: 'audio/webm' });
                            self.transcribeAudio(blob);
                        };

                        self.mediaRecorder.start();
                        self.showToast('🎙️ Gravando... Clique novamente para parar.', 'success');
                    })
                    .catch(function (err) {
                        console.error('Erro getUserMedia:', err);
                        if (err.name === 'NotAllowedError') {
                            self.showToast('Permissão de microfone negada. Permita nas configurações.', 'error');
                        } else if (err.name === 'NotFoundError') {
                            self.showToast('Nenhum microfone encontrado.', 'error');
                        } else {
                            self.showToast('Erro ao acessar microfone: ' + err.message, 'error');
                        }
                    });
            }
        },

        transcribeAudio: function (blob) {
            const self = this;
            const formData = new FormData();
            formData.append('audio', blob, 'recording.webm');
            formData.append('action', 'wpai_transcribe_audio');
            formData.append('nonce', wpaiPostGen.nonce);

            $('#wpai-voice-btn').addClass('loading');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success && response.data.text) {
                        const currentText = $('#wpai-context').val();
                        const newText = currentText + (currentText ? ' ' : '') + response.data.text;
                        $('#wpai-context').val(newText).trigger('input');
                        self.showToast('Transcrição adicionada!', 'success');
                    } else {
                        self.showToast(response.data?.message || 'Erro na transcrição', 'error');
                    }
                },
                error: function () {
                    self.showToast('Erro ao transcrever áudio', 'error');
                },
                complete: function () {
                    $('#wpai-voice-btn').removeClass('loading');
                }
            });
        },

        improveDescription: function () {
            const self = this;
            const text = $('#wpai-context').val().trim();

            if (!text) {
                this.showToast('Escreva algo primeiro para melhorar.', 'error');
                return;
            }

            if (text.length < 10) {
                this.showToast('Texto muito curto para melhorar.', 'error');
                return;
            }

            $('#wpai-improve-btn').addClass('loading');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_improve_description',
                    nonce: wpaiPostGen.nonce,
                    text: text
                },
                success: function (response) {
                    if (response.success && response.data.improved) {
                        $('#wpai-context').val(response.data.improved).trigger('input');
                        self.showToast('Descrição melhorada!', 'success');
                    } else {
                        self.showToast(response.data?.message || 'Erro ao melhorar', 'error');
                    }
                },
                error: function () {
                    self.showToast('Erro ao melhorar descrição', 'error');
                },
                complete: function () {
                    $('#wpai-improve-btn').removeClass('loading');
                }
            });
        },

        switchTab: function (tab) {
            this.currentTab = tab;
            this.$modal.find('.wpai-tab').removeClass('active');
            this.$modal.find('[data-tab="' + tab + '"]').addClass('active');
            this.$modal.find('.wpai-tab-panel').removeClass('active');
            this.$modal.find('#wpai-panel-' + tab).addClass('active');
        },

        enableTab: function (tab) {
            this.$modal.find('[data-tab="' + tab + '"]').prop('disabled', false);
        },

        open: function () {
            this.resetAll();
            this.$overlay.addClass('active');
            $('body').css('overflow', 'hidden');
            this.$modal.find('#wpai-title').focus();
        },

        close: function () {
            this.$overlay.removeClass('active');
            $('body').css('overflow', '');
        },

        resetAll: function () {
            this.$modal.find('input[type="text"], textarea').val('');
            this.$modal.find('#wpai-generate-thumbnail').prop('checked', false);
            this.$modal.find('#wpai-thumb-options').removeClass('show');
            this.$modal.find('[data-tab="thumbnail"]').hide();
            this.resetSteps();
            this.generatedData = null;
            this.selectedTitle = null;
            this.generateThumbnail = false;
            this.switchTab('form');

            // Reset tabs
            this.$modal.find('.wpai-tab').not('[data-tab="form"], [data-tab="pipeline"]').prop('disabled', true);
            this.$modal.find('#wpai-result-links').hide();
            this.$modal.find('#wpai-char-count').text('0');
            this.setButtonGenerate();
        },

        resetSteps: function () {
            this.$modal.find('.wpai-step').removeClass('active completed error').addClass('pending');
        },

        setStepActive: function (step) {
            const $step = this.$modal.find('[data-step="' + step + '"]');
            $step.removeClass('pending completed error').addClass('active');

            // Scroll para o step ativo
            const $container = this.$modal.find('.wpai-pipeline-list');
            const stepOffset = $step.offset().left - $container.offset().left;
            $container.animate({ scrollLeft: stepOffset - 100 }, 300);
        },

        setStepCompleted: function (step) {
            const $step = this.$modal.find('[data-step="' + step + '"]');
            $step.removeClass('pending active error').addClass('completed');
        },

        setStepError: function (step) {
            const $step = this.$modal.find('[data-step="' + step + '"]');
            $step.removeClass('pending active completed').addClass('error');
        },

        setButtonGenerate: function () {
            const $btn = $('#wpai-btn-main');
            $btn.removeClass('wpai-btn-save');
            $btn.html('<span class="dashicons dashicons-controls-play"></span><span>Gerar Artigo</span>');
            $btn.prop('disabled', false);
        },

        setButtonSave: function () {
            const $btn = $('#wpai-btn-main');
            $btn.addClass('wpai-btn-save');
            $btn.html('<span class="dashicons dashicons-saved"></span><span>Salvar Post</span>');
            $btn.prop('disabled', false);
        },

        setButtonLoading: function (text) {
            const $btn = $('#wpai-btn-main');
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update wpai-spin"></span><span>' + text + '</span>');
        },

        generate: function () {
            const self = this;
            const title = $('#wpai-title').val().trim();
            const context = $('#wpai-context').val().trim();

            if (!title) {
                this.showToast('Preencha o título desejado.', 'error');
                $('#wpai-title').focus();
                return;
            }

            if (!context) {
                this.showToast('Preencha o assunto/contexto.', 'error');
                $('#wpai-context').focus();
                return;
            }

            this.isGenerating = true;
            this.resetSteps();
            this.switchTab('pipeline');
            this.setButtonLoading('Gerando...');
            $('#wpai-footer-text').text('Processando com IA... Aguarde.');

            // Iniciar simulação de progresso em tempo real
            this.startProgressSimulation();

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_generate_post',
                    nonce: wpaiPostGen.nonce,
                    desired_title: title,
                    subject_context: context,
                    tone: $('#wpai-tone').val(),
                    writing_type: $('#wpai-type').val(),
                    person_type: $('#wpai-person').val(),
                    word_count: $('#wpai-words').val(),
                    publish_mode: 'Rascunho',
                    generate_thumbnail: this.generateThumbnail,
                    thumbnail_format: this.thumbnailFormat,
                    thumbnail_provider: this.thumbnailProvider,
                    text_ai: $('input[name="wpai-text-ai"]:checked').val() || 'openai'
                },
                timeout: 300000,
                success: function (response) {
                    self.stopProgressSimulation();
                    if (response.success) {
                        self.handleGenerateSuccess(response.data);
                    } else {
                        self.handleError(response.data.message);
                    }
                },
                error: function (xhr, status) {
                    self.stopProgressSimulation();
                    let message = status === 'timeout'
                        ? 'Tempo limite excedido.'
                        : 'Erro de conexão.';
                    self.handleError(message);
                },
                complete: function () {
                    self.isGenerating = false;
                }
            });
        },

        // Simulação de progresso em tempo real
        progressTimeouts: [],

        startProgressSimulation: function () {
            const self = this;
            
            // Pegar IA selecionada para texto e imagem
            const textAI = $('input[name="wpai-text-ai"]:checked').val() || 'openai';
            const imageProvider = this.thumbnailProvider || 'dalle';
            
            // Nomes amigáveis das IAs
            const aiNames = {
                openai: 'OpenAI GPT-4',
                gemini: 'Gemini 2.5',
                dalle: 'DALL-E 3',
                gemini_imagen: 'Gemini Imagen'
            };
            
            const textAIName = aiNames[textAI] || textAI.toUpperCase();
            const imageAIName = aiNames[imageProvider] || imageProvider.toUpperCase();

            // Mostrar badge de IA no footer
            $('#wpai-footer-ai').text(`Usando: ${textAIName}`).show();

            // Tempos estimados para cada etapa (em ms)
            const stages = [
                { step: 'interpretation', time: 0, message: `🔍 Analisando com ${textAIName}...` },
                { step: 'first_draft', time: 8000, message: `✍️ Escrevendo com ${textAIName}...` },
                { step: 'review', time: 25000, message: `📝 Revisando com ${textAIName}...` },
                { step: 'titles', time: 40000, message: `🏷️ Gerando títulos com ${textAIName}...` },
                { step: 'seo', time: 50000, message: `🔎 Criando SEO com ${textAIName}...` }
            ];

            // Se thumbnail está habilitado, adicionar etapa
            if (this.generateThumbnail) {
                stages.push({ step: 'thumbnail', time: 60000, message: `🖼️ Gerando imagem com ${imageAIName}...` });
            }

            // Limpar timeouts anteriores
            this.stopProgressSimulation();

            // Iniciar primeiro step imediatamente
            this.setStepActive('interpretation');
            $('#wpai-footer-text').text(stages[0].message);

            // Agendar transições
            stages.forEach((stage, index) => {
                if (index === 0) return; // Primeiro já foi ativado

                const timeout = setTimeout(() => {
                    // Completar step anterior
                    if (index > 0) {
                        self.setStepCompleted(stages[index - 1].step);
                    }
                    // Ativar step atual
                    self.setStepActive(stage.step);
                    $('#wpai-footer-text').text(stage.message);
                }, stage.time);

                this.progressTimeouts.push(timeout);
            });
        },

        stopProgressSimulation: function () {
            this.progressTimeouts.forEach(t => clearTimeout(t));
            this.progressTimeouts = [];
        },

        handleGenerateSuccess: function (data) {
            const self = this;
            const pipeline = data.pipeline;
            this.generatedData = pipeline;

            // Log da IA usada
            console.log('WPAI: Artigo gerado com IA:', data.text_ai_used || 'openai');

            // Animar steps em sequência com delay
            const steps = ['interpretation', 'first_draft', 'review', 'titles', 'seo'];
            let delay = 0;

            steps.forEach((step, index) => {
                setTimeout(() => {
                    self.setStepCompleted(step);
                }, delay);
                delay += 400; // 400ms entre cada step
            });

            // Enable tabs após animação
            setTimeout(() => {
                this.enableTab('titles');
                this.enableTab('publish');
                this.enableTab('preview');
                this.enableTab('seo');

                // Populate content
                this.populateTitles(pipeline.titles);
                this.populateArticle(pipeline.article);
                this.populateSeo(pipeline.seo);

                // Se tem thumbnail ja gerada, exibir direto
                if (this.generateThumbnail && pipeline.thumbnail_data && pipeline.thumbnail_data.data) {
                    this.enableTab('thumbnail');
                    this.$modal.find('[data-tab="thumbnail"]').show();
                    this.$modal.find('[data-step="thumbnail"]').show();
                    
                    // Exibir prompt usado no textarea
                    if (pipeline.thumbnail_prompt) {
                        $('#wpai-thumb-prompt-text').val(pipeline.thumbnail_prompt);
                    }
                    
                    // Exibir imagem ja gerada (otimizada)
                    setTimeout(() => {
                        self.setStepCompleted('thumbnail');
                        self.displayGeneratedThumbnail(pipeline.thumbnail_data);
                        self.switchTab('titles');
                        $('#wpai-footer-text').text('Escolha o título e clique em Salvar.');
                        self.setButtonSave();
                        self.showToast('Artigo e thumbnail gerados!', 'success');
                    }, 500);
                } else if (this.generateThumbnail && pipeline.thumbnail_prompt) {
                    // Fallback: gerar thumbnail se so tem o prompt
                    this.enableTab('thumbnail');
                    this.$modal.find('[data-tab="thumbnail"]').show();
                    this.$modal.find('[data-step="thumbnail"]').show();
                    $('#wpai-thumb-prompt-text').val(pipeline.thumbnail_prompt);
                    setTimeout(() => {
                        self.setStepActive('thumbnail');
                        self.autoGenerateThumbnail(pipeline.thumbnail_prompt);
                    }, 500);
                } else {
                    // Se nao tem thumbnail, ir direto para titulos
                    this.switchTab('titles');
                    $('#wpai-footer-text').text('Escolha o título e clique em Salvar.');
                    this.setButtonSave();
                    this.showToast('Artigo gerado com sucesso!', 'success');
                }
            }, delay + 200);
        },

        autoGenerateThumbnail: function (prompt) {
            const self = this;

            console.log('WPAI Thumbnail: Starting auto generation');
            console.log('WPAI Thumbnail: Prompt:', prompt);
            console.log('WPAI Thumbnail: Provider:', this.thumbnailProvider);
            console.log('WPAI Thumbnail: Format:', this.thumbnailFormat);

            this.switchTab('thumbnail');
            $('#wpai-thumb-loading').show();
            $('#wpai-thumb-image').hide();
            this.updateThumbStep('generate');
            $('#wpai-footer-text').text('Gerando thumbnail com IA...');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_generate_thumbnail',
                    nonce: wpaiPostGen.nonce,
                    prompt: prompt,
                    format: this.thumbnailFormat,
                    provider: this.thumbnailProvider,
                    title: this.generatedData?.titles?.titles?.[0]?.title || 'thumbnail'
                },
                timeout: 120000,
                beforeSend: function () {
                    console.log('WPAI Thumbnail: AJAX request started');
                    setTimeout(() => self.updateThumbStep('optimize'), 10000);
                    setTimeout(() => self.updateThumbStep('save'), 18000);
                },
                success: function (response) {
                    console.log('WPAI Thumbnail: AJAX success response:', response);
                    self.updateThumbStep('done');
                    self.setStepCompleted('thumbnail');

                    setTimeout(() => {
                        $('#wpai-thumb-loading').hide();
                        if (response.success) {
                            console.log('WPAI Thumbnail: Image URL:', response.data.url);
                            $('#wpai-thumb-img').attr('src', response.data.url);
                            $('#wpai-thumb-image').show();
                            self.thumbnailAttachmentId = response.data.attachment_id;
                            self.generatedData.thumbnail_id = response.data.attachment_id;

                            self.switchTab('titles');
                            $('#wpai-footer-text').text('Thumbnail pronta! Escolha o título e salve.');
                            self.setButtonSave();
                            self.showToast('Artigo e thumbnail prontos!', 'success');
                        } else {
                            console.error('WPAI Thumbnail: Generation failed:', response.data);
                            self.setStepError('thumbnail');
                            self.showToast(response.data?.message || 'Erro ao gerar thumbnail', 'error');
                            self.switchTab('titles');
                            self.setButtonSave();
                        }
                    }, 500);
                },
                error: function (xhr, status, error) {
                    console.error('WPAI Thumbnail: AJAX error:', status, error);
                    console.error('WPAI Thumbnail: XHR response:', xhr.responseText);
                    self.setStepError('thumbnail');
                    $('#wpai-thumb-loading').hide();
                    self.showToast('Erro ao gerar thumbnail: ' + error, 'error');
                    self.switchTab('titles');
                    self.setButtonSave();
                }
            });
        },

        // Regenerar thumbnail com prompt editado
        regenerateThumbnail: function (editedPrompt) {
            const self = this;

            console.log('WPAI Thumbnail: Regenerating with edited prompt');
            console.log('WPAI Thumbnail: Prompt:', editedPrompt);

            $('#wpai-thumb-loading').show();
            $('#wpai-thumb-image').hide();
            this.updateThumbStep('generate');
            $('#wpai-footer-text').text('Regenerando thumbnail...');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_generate_thumbnail',
                    nonce: wpaiPostGen.nonce,
                    prompt: editedPrompt,
                    format: this.thumbnailFormat,
                    provider: this.thumbnailProvider,
                    title: this.generatedData?.titles?.titles?.[0]?.title || 'thumbnail'
                },
                timeout: 120000,
                beforeSend: function () {
                    setTimeout(() => self.updateThumbStep('optimize'), 10000);
                    setTimeout(() => self.updateThumbStep('save'), 18000);
                },
                success: function (response) {
                    self.updateThumbStep('done');
                    setTimeout(() => {
                        $('#wpai-thumb-loading').hide();
                        if (response.success) {
                            $('#wpai-thumb-img').attr('src', response.data.url);
                            $('#wpai-thumb-image').show();
                            self.thumbnailAttachmentId = response.data.attachment_id;
                            self.generatedData.thumbnail_id = response.data.attachment_id;
                            // Limpar base64 pois agora temos ID
                            self.generatedData.thumbnail_base64 = null;
                            self.showToast('Thumbnail regenerada!', 'success');
                        } else {
                            self.showToast(response.data?.message || 'Erro ao regenerar', 'error');
                        }
                    }, 500);
                },
                error: function (xhr, status, error) {
                    $('#wpai-thumb-loading').hide();
                    self.showToast('Erro: ' + error, 'error');
                }
            });
        },

        // Exibe thumbnail ja gerada pelo pipeline
        displayGeneratedThumbnail: function (thumbnailData) {
            const self = this;
            
            if (!thumbnailData || !thumbnailData.data) {
                console.error('WPAI: No thumbnail data to display');
                return;
            }
            
            const mimeType = thumbnailData.mime_type || 'image/jpeg';
            const imageUrl = 'data:' + mimeType + ';base64,' + thumbnailData.data;
            
            $('#wpai-thumb-loading').hide();
            $('#wpai-thumb-img').attr('src', imageUrl);
            $('#wpai-thumb-image').show();
            
            // Salvar dados originais para usar ao salvar o post
            this.generatedData.thumbnail_base64 = thumbnailData.data_original || thumbnailData.data;
            this.generatedData.thumbnail_mime = mimeType;
            
            console.log('WPAI: Thumbnail displayed from pipeline data');
        },

        loadCategories: function () {
            const self = this;
            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_get_categories',
                    nonce: wpaiPostGen.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const $select = $('#wpai-category');
                        $select.empty();
                        $select.append('<option value="0">Sem categoria</option>');
                        response.data.forEach(cat => {
                            $select.append(`<option value="${cat.term_id}">${self.escapeHtml(cat.name)}</option>`);
                        });
                    }
                }
            });
        },

        populateTitles: function (titlesData) {
            const $list = $('#wpai-titles-list');
            $list.empty();

            if (!titlesData || !titlesData.titles) return;

            const recommended = titlesData.recommended || 0;

            titlesData.titles.forEach((item, index) => {
                const isRecommended = index === recommended;
                const $option = $(`
                    <div class="wpai-title-option ${isRecommended ? 'recommended' : ''} ${isRecommended ? 'selected' : ''}" data-title="${this.escapeHtml(item.title)}">
                        <div class="wpai-title-radio ${isRecommended ? 'selected' : ''}"></div>
                        <div class="wpai-title-text">
                            ${this.escapeHtml(item.title)}
                            <div class="wpai-title-meta">${this.escapeHtml(item.rationale)}</div>
                        </div>
                    </div>
                `);

                $option.on('click', () => {
                    this.selectTitle($option);
                });

                $list.append($option);

                if (isRecommended) {
                    this.selectTitle($option);
                }
            });

            if (this.generatedData && this.generatedData.seo && this.generatedData.seo.tags) {
                this.populateTags(this.generatedData.seo.tags);
            }
        },

        populateTags: function (tags) {
            const $list = $('#wpai-tags-list');
            $list.empty();

            tags.forEach(tag => {
                const $tag = $(`
                    <div class="wpai-tag-item selected" data-tag="${this.escapeHtml(tag)}">
                        #${this.escapeHtml(tag)}
                        <span class="tag-remove dashicons dashicons-no-alt"></span>
                    </div>
                `);

                $tag.on('click', function () {
                    $(this).toggleClass('selected');
                });

                $list.append($tag);
            });
        },
        populateArticle: function (article) {
            if (!article) {
                console.error('WPAI: Article content is empty');
                return;
            }
            console.log('WPAI: Populating article preview, length:', article.length);
            $('#wpai-article-content').html(article);
        },

        populateSeo: function (seoData) {
            if (!seoData) return;

            const siteUrl = window.location.hostname || 'seusite.com.br';
            $('#wpai-serp-url').text(siteUrl + ' › artigo');
            $('#wpai-serp-title').text(seoData.meta_title || 'Título SEO');
            $('#wpai-serp-desc').text(seoData.meta_description || 'Descrição...');
            $('#wpai-seo-focus').text(seoData.focus_keyword || '-');

            const $keywords = $('#wpai-seo-keywords');
            $keywords.empty();
            if (seoData.secondary_keywords && Array.isArray(seoData.secondary_keywords)) {
                seoData.secondary_keywords.forEach(kw => {
                    $keywords.append('<span class="wpai-keyword">' + this.escapeHtml(kw) + '</span>');
                });
            }
        },

        selectTitle: function ($option) {
            this.$modal.find('.wpai-title-option').removeClass('selected');
            $option.addClass('selected');
            this.selectedTitle = $option.data('title');
            $('#wpai-serp-title').text(this.selectedTitle);
        },

        generateThumbnailImage: function () {
            const self = this;
            const prompt = this.generatedData.thumbnail_prompt;

            $('#wpai-thumb-loading').show();
            $('#wpai-thumb-image').hide();

            // Reset etapas
            this.updateThumbStep('generate');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_generate_thumbnail',
                    nonce: wpaiPostGen.nonce,
                    prompt: prompt,
                    format: this.thumbnailFormat,
                    provider: this.thumbnailProvider,
                    title: this.selectedTitle || 'thumbnail'
                },
                timeout: 120000,
                beforeSend: function () {
                    // Simular progresso das etapas
                    setTimeout(() => self.updateThumbStep('optimize'), 8000);
                    setTimeout(() => self.updateThumbStep('save'), 15000);
                },
                success: function (response) {
                    self.updateThumbStep('done');
                    setTimeout(() => {
                        $('#wpai-thumb-loading').hide();
                        if (response.success) {
                            $('#wpai-thumb-img').attr('src', response.data.url);
                            $('#wpai-thumb-image').show();
                            self.showToast('Thumbnail gerada e otimizada!', 'success');
                            self.generatedData.thumbnail_id = response.data.attachment_id;
                        } else {
                            self.showToast(response.data.message, 'error');
                        }
                    }, 500);
                },
                error: function () {
                    $('#wpai-thumb-loading').hide();
                    self.showToast('Erro ao gerar thumbnail.', 'error');
                }
            });
        },

        updateThumbStep: function (step) {
            const steps = ['generate', 'optimize', 'save'];
            const messages = {
                'generate': 'Gerando imagem com IA...',
                'optimize': 'Otimizando e comprimindo...',
                'save': 'Salvando na biblioteca...',
                'done': 'Concluído!'
            };

            $('.wpai-process-step').removeClass('active done');

            if (step === 'done') {
                $('.wpai-process-step').addClass('done');
            } else {
                const stepIndex = steps.indexOf(step);
                steps.forEach((s, i) => {
                    const $step = $(`.wpai-process-step[data-step="${s}"]`);
                    if (i < stepIndex) {
                        $step.addClass('done');
                    } else if (i === stepIndex) {
                        $step.addClass('active');
                    }
                });
            }

            $('#wpai-thumb-status').text(messages[step] || 'Processando...');
        },

        getSelectedTags: function () {
            const tags = [];
            $('.wpai-tag-item.selected').each(function () {
                tags.push($(this).data('tag'));
            });
            return tags;
        },

        savePost: function () {
            const self = this;

            if (!this.selectedTitle) {
                this.showToast('Selecione um título primeiro.', 'error');
                this.switchTab('titles');
                return;
            }

            this.setButtonLoading('Salvando...');
            $('#wpai-footer-text').text('Salvando post...');

            // Preparar dados do post
            const postData = {
                action: 'wpai_save_post',
                nonce: wpaiPostGen.nonce,
                title: this.selectedTitle,
                content: this.generatedData.article,
                status: $('#wpai-publish-status').val(),
                category: $('#wpai-category').val(),
                tags: this.getSelectedTags(),
                thumbnail_id: this.generatedData.thumbnail_id || 0,
                seo: this.generatedData.seo,
                post_type: wpaiPostGen.currentPostType || 'post'
            };
            
            // Se tem thumbnail em base64 (gerada pelo pipeline), enviar
            if (this.generatedData.thumbnail_base64 && !this.generatedData.thumbnail_id) {
                postData.thumbnail_base64 = this.generatedData.thumbnail_base64;
                postData.thumbnail_mime = this.generatedData.thumbnail_mime || 'image/jpeg';
            }

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: postData,
                success: function (response) {
                    if (response.success) {
                        self.handleSaveSuccess(response.data);
                    } else {
                        self.showToast(response.data.message, 'error');
                        self.setButtonSave();
                    }
                },
                error: function () {
                    self.showToast('Erro ao salvar.', 'error');
                    self.setButtonSave();
                }
            });
        },

        handleSaveSuccess: function (data) {
            // Mostrar links de resultado
            this.$modal.find('.wpai-link-edit').attr('href', data.edit_url);
            this.$modal.find('.wpai-link-view').attr('href', data.view_url);
            $('#wpai-result-links').show();

            $('#wpai-footer-text').html('<strong>Post #' + data.post_id + ' salvo!</strong>');

            // Alterar botão principal para "Novo Post"
            const $btn = $('#wpai-btn-main');
            $btn.prop('disabled', false);
            $btn.removeClass('wpai-btn-save wpai-btn-primary').addClass('wpai-btn-success');
            $btn.html('<span class="dashicons dashicons-yes-alt"></span> Novo Post');
            
            // Rebind click para recarregar
            $btn.off('click').on('click', function () {
                location.reload();
            });

            this.showToast('Post salvo com sucesso!', 'success');
        },


        handleError: function (message) {
            this.setStepError('interpretation');
            this.setButtonGenerate();
            $('#wpai-footer-text').text('Erro: ' + message);
            this.showToast(message, 'error');
        },

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showToast: function (message, type) {
            const $toast = $('<div class="wpai-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);
            setTimeout(() => $toast.fadeOut(300, function () { $(this).remove(); }), 4000);
        }
    };

    $(document).ready(function () {
        WPAI_Modal.init();
    });

    window.WPAI_Modal = WPAI_Modal;

})(jQuery);
