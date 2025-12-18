/**
 * WP AI Post Generator - Admin JavaScript
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @created 2025-12-11 09:19
 */

(function ($) {
    'use strict';

    const WPAI_Admin = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Admin Tabs Navigation
            $('.wpai-admin-tab').on('click', function () {
                const tab = $(this).data('tab');
                $('.wpai-admin-tab').removeClass('active');
                $(this).addClass('active');
                $('.wpai-admin-panel').removeClass('active');
                $('#wpai-panel-' + tab).addClass('active');
            });

            // Toggle OpenAI API key visibility
            $('#toggle-api-key').on('click', function () {
                WPAI_Admin.toggleKeyVisibility('#openai_api_key', $(this), 'openai');
            });

            // Toggle Gemini API key visibility
            $('#toggle-gemini-key').on('click', function () {
                WPAI_Admin.toggleKeyVisibility('#gemini_api_key', $(this), 'gemini');
            });

            // Copy OpenAI API key
            $('#copy-api-key').on('click', function () {
                WPAI_Admin.copyApiKey('#openai_api_key', $(this), 'openai');
            });

            // Copy Gemini API key
            $('#copy-gemini-key').on('click', function () {
                WPAI_Admin.copyApiKey('#gemini_api_key', $(this), 'gemini');
            });

            // Test OpenAI connection
            $('#test-openai').on('click', function () {
                WPAI_Admin.testAPI('openai', $(this));
            });

            // Test Gemini connection
            $('#test-gemini').on('click', function () {
                WPAI_Admin.testAPI('gemini', $(this));
            });

            // Test OpenRouter connection
            $('#test-openrouter').on('click', function () {
                WPAI_Admin.testAPI('openrouter', $(this));
            });

            // Toggle OpenRouter API key visibility
            $('#toggle-openrouter-key').on('click', function () {
                WPAI_Admin.toggleKeyVisibility('#openrouter_api_key', $(this), 'openrouter');
            });
        },

        toggleKeyVisibility: function (inputSelector, $button, keyType) {
            const $input = $(inputSelector);
            const $icon = $button.find('.dashicons');

            if ($input.attr('type') === 'password') {
                // Se o campo está vazio ou tem placeholder, carregar a key salva
                if (!$input.val() || $input.val().indexOf('•') !== -1) {
                    WPAI_Admin.loadSavedKey(inputSelector, $button, keyType);
                } else {
                    $input.attr('type', 'text');
                    $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                }
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        loadSavedKey: function (inputSelector, $button, keyType) {
            const $input = $(inputSelector);
            const $icon = $button.find('.dashicons');
            
            $button.prop('disabled', true);
            $icon.removeClass('dashicons-visibility dashicons-hidden').addClass('dashicons-update wpai-spin');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_reveal_api_key',
                    nonce: wpaiPostGen.nonce,
                    key_type: keyType
                },
                success: function (response) {
                    if (response.success) {
                        $input.val(response.data.key);
                        $input.attr('type', 'text');
                        $icon.removeClass('dashicons-update wpai-spin').addClass('dashicons-hidden');
                        WPAI_Admin.showToast('Chave carregada!', 'success');
                    } else {
                        $icon.removeClass('dashicons-update wpai-spin').addClass('dashicons-visibility');
                        WPAI_Admin.showToast(response.data.message, 'error');
                    }
                },
                error: function () {
                    $icon.removeClass('dashicons-update wpai-spin').addClass('dashicons-visibility');
                    WPAI_Admin.showToast('Erro ao carregar chave.', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        },

        copyApiKey: function (inputSelector, $button, keyType) {
            const $input = $(inputSelector);
            const $icon = $button.find('.dashicons');
            const currentValue = $input.val();
            
            // Funcao auxiliar para copiar e dar feedback
            const doCopy = function(text) {
                // Fallback para navegadores antigos
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        WPAI_Admin.showCopySuccess($button, $icon);
                    }).catch(function() {
                        WPAI_Admin.fallbackCopy(text, $button, $icon);
                    });
                } else {
                    WPAI_Admin.fallbackCopy(text, $button, $icon);
                }
            };
            
            // Se o campo tem valor visivel, copiar direto
            if (currentValue && currentValue.length > 5 && !currentValue.includes('•')) {
                doCopy(currentValue);
            } else {
                // Carregar a chave do servidor primeiro
                $button.prop('disabled', true);
                $icon.removeClass('dashicons-admin-page').addClass('dashicons-update wpai-spin');
                
                $.ajax({
                    url: wpaiPostGen.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpai_reveal_api_key',
                        nonce: wpaiPostGen.nonce,
                        key_type: keyType
                    },
                    success: function (response) {
                        if (response.success && response.data.key) {
                            doCopy(response.data.key);
                        } else {
                            $icon.removeClass('dashicons-update wpai-spin').addClass('dashicons-admin-page');
                            WPAI_Admin.showToast(response.data?.message || 'Nenhuma chave salva.', 'error');
                        }
                    },
                    error: function () {
                        $icon.removeClass('dashicons-update wpai-spin').addClass('dashicons-admin-page');
                        WPAI_Admin.showToast('Erro ao carregar chave.', 'error');
                    },
                    complete: function () {
                        $button.prop('disabled', false);
                    }
                });
            }
        },

        showCopySuccess: function($button, $icon) {
            $button.addClass('copied');
            $icon.removeClass('dashicons-update wpai-spin dashicons-admin-page').addClass('dashicons-yes');
            WPAI_Admin.showToast('Chave copiada!', 'success');
            setTimeout(function() {
                $button.removeClass('copied');
                $icon.removeClass('dashicons-yes').addClass('dashicons-admin-page');
            }, 2000);
        },

        fallbackCopy: function(text, $button, $icon) {
            // Fallback usando textarea temporario
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            try {
                document.execCommand('copy');
                WPAI_Admin.showCopySuccess($button, $icon);
            } catch (e) {
                WPAI_Admin.showToast('Erro ao copiar. Selecione manualmente.', 'error');
                $icon.removeClass('dashicons-update wpai-spin').addClass('dashicons-admin-page');
            }
            $temp.remove();
        },

        testAPI: function (apiType, $btn) {
            const originalHtml = $btn.html();
            const actions = { 'openai': 'wpai_test_connection', 'gemini': 'wpai_test_gemini', 'openrouter': 'wpai_test_openrouter' };
            const names = { 'openai': 'OpenAI', 'gemini': 'Gemini', 'openrouter': 'OpenRouter' };
            const action = actions[apiType] || 'wpai_test_connection';
            const apiName = names[apiType] || 'API';

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update wpai-spin"></span> Testando...');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: wpaiPostGen.nonce
                },
                success: function (response) {
                    if (response.success) {
                        WPAI_Admin.showToast(response.data.message + ' (' + response.data.model + ')', 'success');
                    } else {
                        WPAI_Admin.showToast(apiName + ': ' + response.data.message, 'error');
                    }
                },
                error: function () {
                    WPAI_Admin.showToast('Erro de conexão com ' + apiName + '.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        showToast: function (message, type) {
            const $toast = $('<div class="wpai-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);

            setTimeout(function () {
                $toast.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 4000);
        },

        // === MAPEAMENTO DE CAMPOS CPT ===
        currentMappingPT: null,
        currentFields: null,
        currentMappings: null,

        initFieldMapping: function() {
            const self = this;

            // Abrir modal de mapeamento
            $(document).on('click', '.wpai-pt-config-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const pt = $(this).data('pt');
                const label = $(this).data('label');
                self.openMappingModal(pt, label);
            });

            // Fechar modal
            $(document).on('click', '.wpai-mapping-close, .wpai-mapping-overlay, #wpai-mapping-cancel', function() {
                self.closeMappingModal();
            });

            // Salvar mapeamentos
            $(document).on('click', '#wpai-mapping-save', function() {
                self.saveMappings();
            });
        },

        openMappingModal: function(postType, label) {
            const self = this;
            this.currentMappingPT = postType;

            $('#wpai-mapping-pt-name').text(label);
            $('#wpai-mapping-modal').show();
            $('#wpai-mapping-loading').show();
            $('#wpai-mapping-grid').hide();

            // Escanear campos do CPT
            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_scan_cpt_fields',
                    nonce: wpaiPostGen.nonce,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        self.currentFields = response.data.fields;
                        self.currentMappings = response.data.mappings;
                        self.renderMappingGrid(response.data);
                    } else {
                        self.showToast(response.data.message, 'error');
                        self.closeMappingModal();
                    }
                },
                error: function() {
                    self.showToast('Erro ao escanear campos.', 'error');
                    self.closeMappingModal();
                }
            });
        },

        closeMappingModal: function() {
            $('#wpai-mapping-modal').hide();
            this.currentMappingPT = null;
            this.currentFields = null;
            this.currentMappings = null;
        },

        renderMappingGrid: function(data) {
            const fields = data.fields;
            const mappings = data.mappings || {};
            const generatedFields = data.generated_fields;
            const $grid = $('#wpai-mapping-grid');

            // Construir opções de destino
            let targetOptions = '<option value="">-- Não mapear --</option>';
            
            // Campos nativos
            if (Object.keys(fields.native).length > 0) {
                targetOptions += '<optgroup label="📄 Campos Nativos">';
                for (const [key, field] of Object.entries(fields.native)) {
                    targetOptions += `<option value="native:${key}">${field.label}</option>`;
                }
                targetOptions += '</optgroup>';
            }

            // Meta fields
            if (Object.keys(fields.meta).length > 0) {
                targetOptions += '<optgroup label="🔧 Meta Fields (ACF/Pods/etc)">';
                for (const [key, field] of Object.entries(fields.meta)) {
                    const badge = field.type.toUpperCase();
                    targetOptions += `<option value="meta:${key}">${field.label} [${badge}]</option>`;
                }
                targetOptions += '</optgroup>';
            }

            // Taxonomias
            if (Object.keys(fields.taxonomies).length > 0) {
                targetOptions += '<optgroup label="🏷️ Taxonomias">';
                for (const [key, tax] of Object.entries(fields.taxonomies)) {
                    targetOptions += `<option value="taxonomy:${key}">${tax.label}</option>`;
                }
                targetOptions += '</optgroup>';
            }

            // Construir grid de mapeamento
            let html = '<div class="wpai-mapping-table">';
            html += '<div class="wpai-mapping-row wpai-mapping-header">';
            html += '<div class="wpai-mapping-col">Campo Gerado pela IA</div>';
            html += '<div class="wpai-mapping-col">→</div>';
            html += '<div class="wpai-mapping-col">Salvar em</div>';
            html += '</div>';

            for (const [genKey, genField] of Object.entries(generatedFields)) {
                const savedMapping = mappings[genKey] || {};
                const savedValue = savedMapping.field ? `${savedMapping.type}:${savedMapping.field}` : '';

                html += `<div class="wpai-mapping-row" data-gen-field="${genKey}">`;
                html += `<div class="wpai-mapping-col">
                    <span class="wpai-gen-field-label">${genField.label}</span>
                    <span class="wpai-gen-field-desc">${genField.description}</span>
                </div>`;
                html += '<div class="wpai-mapping-col wpai-mapping-arrow">→</div>';
                html += `<div class="wpai-mapping-col">
                    <select class="wpai-mapping-select" data-gen-field="${genKey}">
                        ${targetOptions.replace(`value="${savedValue}"`, `value="${savedValue}" selected`)}
                    </select>
                </div>`;
                html += '</div>';
            }

            html += '</div>';

            // Info sobre campos detectados
            const nativeCount = Object.keys(fields.native).length;
            const metaCount = Object.keys(fields.meta).length;
            const taxCount = Object.keys(fields.taxonomies).length;

            html += `<div class="wpai-mapping-info">
                <span class="dashicons dashicons-info"></span>
                Detectados: ${nativeCount} campos nativos, ${metaCount} meta fields, ${taxCount} taxonomias
            </div>`;

            $grid.html(html);
            $('#wpai-mapping-loading').hide();
            $grid.show();
        },

        saveMappings: function() {
            const self = this;
            const mappings = {};

            $('.wpai-mapping-select').each(function() {
                const genField = $(this).data('gen-field');
                const value = $(this).val();

                if (value) {
                    const [type, field] = value.split(':');
                    mappings[genField] = { type: type, field: field };
                }
            });

            const $btn = $('#wpai-mapping-save');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update wpai-spin"></span> Salvando...');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_save_field_mappings',
                    nonce: wpaiPostGen.nonce,
                    post_type: this.currentMappingPT,
                    mappings: mappings
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast('Mapeamento salvo!', 'success');
                        self.closeMappingModal();
                    } else {
                        self.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    self.showToast('Erro ao salvar mapeamento.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salvar Mapeamento');
                }
            });
        }
    };

    $(document).ready(function () {
        WPAI_Admin.init();
        WPAI_Admin.initFieldMapping();
    });

    // Expose for other modules
    window.WPAI_Admin = WPAI_Admin;

})(jQuery);
