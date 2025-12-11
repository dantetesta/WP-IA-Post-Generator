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
                            <h2><span class="dashicons dashicons-superhero-alt"></span> Criar Post com IA</h2>
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
                                            <button type="button" class="wpai-icon-btn" id="wpai-voice-btn" title="Gravar voz">
                                                <span class="dashicons dashicons-microphone"></span>
                                            </button>
                                            <button type="button" class="wpai-icon-btn" id="wpai-improve-btn" title="Melhorar com IA">
                                                <span class="dashicons dashicons-admin-generic"></span>
                                            </button>
                                        </div>
                                    </div>
                                    <textarea id="wpai-context" class="wpai-textarea" placeholder="Descreva o tema, público-alvo, palavras-chave... ou use o microfone para ditar"></textarea>
                                    <div class="wpai-context-info">
                                        <span id="wpai-char-count">0</span>/500 caracteres
                                    </div>
                                </div>

                                <div class="wpai-form-row-compact">
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Tom</label>
                                        <select id="wpai-tone" class="wpai-select-compact">
                                            <option value="Neutro" selected>🎯 Neutro</option>
                                            <option value="Profissional">💼 Profissional</option>
                                            <option value="Humanizado">💬 Humanizado</option>
                                            <option value="Jornalístico">📰 Jornalístico</option>
                                            <option value="Marketing">🚀 Marketing</option>
                                        </select>
                                    </div>
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Tipo</label>
                                        <select id="wpai-type" class="wpai-select-compact">
                                            <option value="Notícia">📢 Notícia</option>
                                            <option value="Artigo" selected>📝 Artigo</option>
                                            <option value="Tutorial">📚 Tutorial</option>
                                            <option value="Review">⭐ Review</option>
                                        </select>
                                    </div>
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Pessoa</label>
                                        <select id="wpai-person" class="wpai-select-compact">
                                            <option value="Primeira pessoa">1ª</option>
                                            <option value="Segunda pessoa">2ª</option>
                                            <option value="Terceira pessoa" selected>3ª</option>
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
                                    <div class="wpai-form-item">
                                        <label class="wpai-label">Status</label>
                                        <select id="wpai-status" class="wpai-select-compact">
                                            <option value="Rascunho" selected>📄 Rascunho</option>
                                            <option value="Publicado">🌐 Publicado</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Seção de Thumbnail Compacta -->
                                <div class="wpai-thumb-section">
                                    <div class="wpai-thumb-toggle">
                                        <label class="wpai-switch">
                                            <input type="checkbox" id="wpai-generate-thumbnail">
                                            <span class="wpai-slider"></span>
                                        </label>
                                        <span class="wpai-thumb-label">Gerar thumbnail com IA</span>
                                    </div>
                                    
                                    <div class="wpai-thumb-options" id="wpai-thumb-options">
                                        <!-- Provider Selection -->
                                        <div class="wpai-provider-row">
                                            <label class="wpai-provider-option ${hasOpenAI ? 'available' : 'disabled'}" data-provider="dalle">
                                                <input type="radio" name="wpai-provider" value="dalle" ${hasOpenAI ? 'checked' : 'disabled'}>
                                                <span class="wpai-provider-icon">🎨</span>
                                                <span class="wpai-provider-name">DALL-E 3</span>
                                            </label>
                                            <label class="wpai-provider-option ${hasGemini ? 'available' : 'disabled'}" data-provider="gemini">
                                                <input type="radio" name="wpai-provider" value="gemini" ${!hasOpenAI && hasGemini ? 'checked' : ''} ${hasGemini ? '' : 'disabled'}>
                                                <span class="wpai-provider-icon">✨</span>
                                                <span class="wpai-provider-name">Gemini</span>
                                            </label>
                                        </div>
                                        
                                        <!-- Format Selection DALL-E -->
                                        <div class="wpai-format-row" id="wpai-dalle-formats">
                                            <label class="wpai-format-radio" data-format="1024x1024">
                                                <input type="radio" name="wpai-format-dalle" value="1024x1024">
                                                <span class="wpai-format-box wpai-ratio-1-1"></span>
                                                <span>1:1</span>
                                            </label>
                                            <label class="wpai-format-radio selected" data-format="1792x1024">
                                                <input type="radio" name="wpai-format-dalle" value="1792x1024" checked>
                                                <span class="wpai-format-box wpai-ratio-16-9"></span>
                                                <span>16:9</span>
                                            </label>
                                            <label class="wpai-format-radio" data-format="1024x1792">
                                                <input type="radio" name="wpai-format-dalle" value="1024x1792">
                                                <span class="wpai-format-box wpai-ratio-9-16"></span>
                                                <span>9:16</span>
                                            </label>
                                        </div>
                                        
                                        <!-- Format Selection Gemini -->
                                        <div class="wpai-format-row" id="wpai-gemini-formats" style="display: none;">
                                            <label class="wpai-format-radio" data-format="1:1">
                                                <input type="radio" name="wpai-format-gemini" value="1:1">
                                                <span class="wpai-format-box wpai-ratio-1-1"></span>
                                                <span>1:1</span>
                                            </label>
                                            <label class="wpai-format-radio selected" data-format="4:3">
                                                <input type="radio" name="wpai-format-gemini" value="4:3" checked>
                                                <span class="wpai-format-box wpai-ratio-4-3"></span>
                                                <span>4:3</span>
                                            </label>
                                            <label class="wpai-format-radio" data-format="16:9">
                                                <input type="radio" name="wpai-format-gemini" value="16:9">
                                                <span class="wpai-format-box wpai-ratio-16-9"></span>
                                                <span>16:9</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aba: Pipeline -->
                            <div class="wpai-tab-panel" id="wpai-panel-pipeline">
                                <div class="wpai-pipeline-list">
                                    <div class="wpai-step" data-step="interpretation">
                                        <div class="wpai-step-num">1</div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Interpretação</div>
                                            <div class="wpai-step-desc">Analisando requisitos SEO</div>
                                        </div>
                                        <div class="wpai-step-status"></div>
                                    </div>
                                    <div class="wpai-step" data-step="first_draft">
                                        <div class="wpai-step-num">2</div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Escrita</div>
                                            <div class="wpai-step-desc">Criando artigo otimizado</div>
                                        </div>
                                        <div class="wpai-step-status"></div>
                                    </div>
                                    <div class="wpai-step" data-step="review">
                                        <div class="wpai-step-num">3</div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Revisão</div>
                                            <div class="wpai-step-desc">Avaliando qualidade E-E-A-T</div>
                                        </div>
                                        <div class="wpai-step-status"></div>
                                    </div>
                                    <div class="wpai-step" data-step="titles">
                                        <div class="wpai-step-num">4</div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Títulos SEO</div>
                                            <div class="wpai-step-desc">Gerando 5 opções</div>
                                        </div>
                                        <div class="wpai-step-status"></div>
                                    </div>
                                    <div class="wpai-step" data-step="seo">
                                        <div class="wpai-step-num">5</div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">SEO & Rank Math</div>
                                            <div class="wpai-step-desc">Meta tags e keywords</div>
                                        </div>
                                        <div class="wpai-step-status"></div>
                                    </div>
                                    <div class="wpai-step" data-step="thumbnail" style="display: none;">
                                        <div class="wpai-step-num">6</div>
                                        <div class="wpai-step-info">
                                            <div class="wpai-step-title">Thumbnail</div>
                                            <div class="wpai-step-desc">Prompt de imagem</div>
                                        </div>
                                        <div class="wpai-step-status"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aba: Títulos -->
                            <div class="wpai-tab-panel" id="wpai-panel-titles">
                                <div class="wpai-panel-header">
                                    <h3>Escolha o Título</h3>
                                    <span class="wpai-badge">5 sugestões SEO</span>
                                </div>
                                <div class="wpai-titles-list" id="wpai-titles-list"></div>
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
                                    <button type="button" class="wpai-btn-small" id="wpai-generate-thumb-btn">
                                        <span class="dashicons dashicons-images-alt2"></span> Gerar
                                    </button>
                                </div>
                                <div class="wpai-thumb-prompt" id="wpai-thumb-prompt">
                                    <label>Prompt:</label>
                                    <p id="wpai-thumb-prompt-text">-</p>
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
                $('#wpai-thumb-options').toggle(self.generateThumbnail);
                $('[data-step="thumbnail"]').toggle(self.generateThumbnail);
                $('[data-tab="thumbnail"]').toggle(self.generateThumbnail);
            });

            // Provider selection
            this.$overlay.on('change', 'input[name="wpai-provider"]', function () {
                self.thumbnailProvider = $(this).val();
                $('.wpai-provider-option').removeClass('selected');
                $(this).closest('.wpai-provider-option').addClass('selected');

                if (self.thumbnailProvider === 'dalle') {
                    $('#wpai-dalle-formats').show();
                    $('#wpai-gemini-formats').hide();
                    self.thumbnailFormat = $('input[name="wpai-format-dalle"]:checked').val();
                } else {
                    $('#wpai-dalle-formats').hide();
                    $('#wpai-gemini-formats').show();
                    self.thumbnailFormat = $('input[name="wpai-format-gemini"]:checked').val();
                }
            });

            // Format selection
            this.$overlay.on('change', 'input[name="wpai-format-dalle"], input[name="wpai-format-gemini"]', function () {
                $(this).closest('.wpai-format-row').find('.wpai-format-radio').removeClass('selected');
                $(this).closest('.wpai-format-radio').addClass('selected');
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

            // Generate thumbnail button
            this.$overlay.on('click', '#wpai-generate-thumb-btn', function () {
                if (self.generatedData && self.generatedData.thumbnail_prompt) {
                    self.generateThumbnailImage();
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
            this.$modal.find('#wpai-thumb-options').hide();
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
            this.$modal.find('.wpai-step').removeClass('active completed error');
        },

        setStepActive: function (step) {
            this.$modal.find('[data-step="' + step + '"]').removeClass('completed error').addClass('active');
        },

        setStepCompleted: function (step) {
            this.$modal.find('[data-step="' + step + '"]').removeClass('active error').addClass('completed');
        },

        setStepError: function (step) {
            this.$modal.find('[data-step="' + step + '"]').removeClass('active completed').addClass('error');
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

            this.setStepActive('interpretation');

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
                    publish_mode: $('#wpai-status').val(),
                    generate_thumbnail: this.generateThumbnail,
                    thumbnail_format: this.thumbnailFormat,
                    thumbnail_provider: this.thumbnailProvider
                },
                timeout: 300000,
                success: function (response) {
                    if (response.success) {
                        self.handleGenerateSuccess(response.data);
                    } else {
                        self.handleError(response.data.message);
                    }
                },
                error: function (xhr, status) {
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

        handleGenerateSuccess: function (data) {
            const pipeline = data.pipeline;
            this.generatedData = pipeline;

            // Update steps
            this.setStepCompleted('interpretation');
            this.setStepCompleted('first_draft');
            this.setStepCompleted('review');
            this.setStepCompleted('titles');
            this.setStepCompleted('seo');

            if (pipeline.thumbnail_prompt) {
                this.setStepCompleted('thumbnail');
            }

            // Enable tabs
            this.enableTab('titles');
            this.enableTab('preview');
            this.enableTab('seo');

            if (this.generateThumbnail && pipeline.thumbnail_prompt) {
                this.enableTab('thumbnail');
                this.$modal.find('[data-tab="thumbnail"]').show();
                $('#wpai-thumb-prompt-text').text(pipeline.thumbnail_prompt);
            }

            // Populate content
            this.populateTitles(pipeline.titles);
            this.populateArticle(pipeline.article);
            this.populateSeo(pipeline.seo);

            // Switch to titles tab
            this.switchTab('titles');

            // Update UI
            $('#wpai-footer-text').text('Escolha o título e clique em Salvar.');
            this.setButtonSave();

            this.showToast('Artigo gerado com sucesso!', 'success');
        },

        populateTitles: function (titlesData) {
            const $list = $('#wpai-titles-list');
            $list.empty();

            if (!titlesData || !titlesData.titles) return;

            const recommended = titlesData.recommended || 0;

            titlesData.titles.forEach((item, index) => {
                const isRecommended = index === recommended;
                const $option = $(`
                    <div class="wpai-title-option ${isRecommended ? 'recommended' : ''}" data-title="${this.escapeHtml(item.title)}">
                        <div class="wpai-title-radio"></div>
                        <div class="wpai-title-text">${this.escapeHtml(item.title)}</div>
                        <div class="wpai-title-meta">${item.characters || item.title.length} chars</div>
                    </div>
                `);
                $list.append($option);

                if (isRecommended) {
                    this.selectTitle($option);
                }
            });
        },

        populateArticle: function (article) {
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

        savePost: function () {
            const self = this;

            if (!this.selectedTitle) {
                this.showToast('Selecione um título primeiro.', 'error');
                this.switchTab('titles');
                return;
            }

            this.setButtonLoading('Salvando...');
            $('#wpai-footer-text').text('Salvando post...');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_save_post',
                    nonce: wpaiPostGen.nonce,
                    title: this.selectedTitle,
                    content: this.generatedData.article,
                    status: $('#wpai-status').val(),
                    seo: JSON.stringify(this.generatedData.seo)
                },
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
            const self = this;

            // Show result links
            this.$modal.find('.wpai-link-edit').attr('href', data.edit_url);
            this.$modal.find('.wpai-link-view').attr('href', data.view_url);
            $('#wpai-result-links').show();

            $('#wpai-footer-text').html('<strong>Post #' + data.post_id + ' salvo!</strong>');

            // Generate thumbnail if enabled
            if (this.generateThumbnail && this.generatedData.thumbnail_prompt) {
                this.switchTab('thumbnail');

                $.ajax({
                    url: wpaiPostGen.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_generate_thumbnail',
                        nonce: wpaiPostGen.nonce,
                        prompt: this.generatedData.thumbnail_prompt,
                        format: this.thumbnailFormat,
                        provider: this.thumbnailProvider,
                        post_id: data.post_id,
                        title: this.selectedTitle
                    },
                    beforeSend: function () {
                        $('#wpai-thumb-loading').show();
                        $('#wpai-thumb-image').hide();
                    },
                    success: function (response) {
                        $('#wpai-thumb-loading').hide();
                        if (response.success) {
                            $('#wpai-thumb-img').attr('src', response.data.url);
                            $('#wpai-thumb-image').show();
                            self.showToast('Thumbnail anexada!', 'success');
                        } else {
                            self.showToast('Erro: ' + response.data.message, 'error');
                        }
                    },
                    error: function () {
                        $('#wpai-thumb-loading').hide();
                        self.showToast('Erro ao gerar thumbnail.', 'error');
                    }
                });
            }

            // Update button
            const $btn = $('#wpai-btn-main');
            $btn.removeClass('wpai-btn-save');
            $btn.html('<span class="dashicons dashicons-plus-alt"></span><span>Novo Artigo</span>');
            $btn.prop('disabled', false);
            $btn.off('click').on('click', () => this.resetAll());

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
