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
            // Toggle API key visibility
            $('#toggle-api-key').on('click', function () {
                WPAI_Admin.toggleKeyVisibility('#openai_api_key', $(this));
            });

            // Toggle Gemini API key visibility
            $('#toggle-gemini-key').on('click', function () {
                WPAI_Admin.toggleKeyVisibility('#gemini_api_key', $(this));
            });

            // Test connection
            $('#test-connection').on('click', this.testConnection);
        },

        toggleKeyVisibility: function (inputSelector, $button) {
            const $input = $(inputSelector);
            const $icon = $button.find('.dashicons');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        testConnection: function () {
            const $btn = $(this);
            const originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update wpai-spin"></span> Testando...');

            $.ajax({
                url: wpaiPostGen.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpai_test_connection',
                    nonce: wpaiPostGen.nonce
                },
                success: function (response) {
                    if (response.success) {
                        WPAI_Admin.showToast(response.data.message + ' Modelo: ' + response.data.model, 'success');
                    } else {
                        WPAI_Admin.showToast(response.data.message, 'error');
                    }
                },
                error: function () {
                    WPAI_Admin.showToast('Erro de conexão. Tente novamente.', 'error');
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
        }
    };

    $(document).ready(function () {
        WPAI_Admin.init();
    });

    // Expose for other modules
    window.WPAI_Admin = WPAI_Admin;

})(jQuery);
