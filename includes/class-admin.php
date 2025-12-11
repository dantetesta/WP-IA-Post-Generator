<?php
/**
 * Classe Admin
 * @package WP_AI_Post_Generator
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @updated 2025-12-11 10:25
 */

if (!defined('ABSPATH'))
    exit;

class WPAI_Admin
{
    private $encryption;

    public function __construct()
    {
        $this->encryption = new WPAI_Encryption();
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_footer-edit.php', [$this, 'add_generate_button']);
    }

    public function add_menu_page()
    {
        add_menu_page(
            __('AI Post Generator', 'wp-ai-post-generator'),
            __('AI Post Gen', 'wp-ai-post-generator'),
            'manage_options',
            'wp-ai-post-generator',
            [$this, 'render_settings_page'],
            'dashicons-edit-large',
            30
        );
    }

    public function enqueue_scripts($hook)
    {
        if ($hook !== 'toplevel_page_wp-ai-post-generator' && $hook !== 'edit.php')
            return;

        wp_enqueue_style('wpai-admin', WPAI_POST_GEN_PLUGIN_URL . 'assets/css/admin.css', [], WPAI_POST_GEN_VERSION);
        wp_enqueue_style('wpai-modal', WPAI_POST_GEN_PLUGIN_URL . 'assets/css/modal.css', [], WPAI_POST_GEN_VERSION);
        wp_enqueue_script('wpai-admin', WPAI_POST_GEN_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WPAI_POST_GEN_VERSION, true);
        wp_enqueue_script('wpai-modal', WPAI_POST_GEN_PLUGIN_URL . 'assets/js/modal.js', ['jquery'], WPAI_POST_GEN_VERSION, true);

        // Verificar APIs disponíveis
        $settings = get_option('wpai_post_gen_settings', []);
        $has_openai_key = !empty($settings['openai_api_key']);
        $has_gemini_key = !empty($settings['gemini_api_key']);

        wp_localize_script('wpai-modal', 'wpaiPostGen', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpai_post_gen_nonce'),
            'hasImageGeneration' => $has_openai_key,
            'hasGeminiKey' => $has_gemini_key,
            'imageFormats' => WPAI_OpenAI_Client::get_image_sizes(),
            'strings' => [
                'generating' => __('Gerando artigo...', 'wp-ai-post-generator'),
                'success' => __('Artigo gerado com sucesso!', 'wp-ai-post-generator'),
                'error' => __('Erro ao gerar artigo.', 'wp-ai-post-generator'),
            ]
        ]);
    }

    public function register_settings()
    {
        register_setting('wpai_post_gen_settings_group', 'wpai_post_gen_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];
        $settings = get_option('wpai_post_gen_settings', []);

        // OpenAI API Key
        if (!empty($input['openai_api_key'])) {
            $api_key = sanitize_text_field($input['openai_api_key']);
            if (strpos($api_key, 'sk-') === 0) {
                $sanitized['openai_api_key'] = $this->encryption->encrypt($api_key);
            } else {
                $sanitized['openai_api_key'] = $settings['openai_api_key'] ?? '';
            }
        } else {
            $sanitized['openai_api_key'] = $settings['openai_api_key'] ?? '';
        }

        // Gemini API Key
        if (!empty($input['gemini_api_key'])) {
            $gemini_key = sanitize_text_field($input['gemini_api_key']);
            // Gemini keys começam com "AI" geralmente
            if (strlen($gemini_key) > 10 && $gemini_key !== '••••••••••••••••') {
                $sanitized['gemini_api_key'] = $this->encryption->encrypt($gemini_key);
            } else {
                $sanitized['gemini_api_key'] = $settings['gemini_api_key'] ?? '';
            }
        } else {
            $sanitized['gemini_api_key'] = $settings['gemini_api_key'] ?? '';
        }

        // OpenAI Model
        $valid_models = array_keys(WPAI_OpenAI_Client::get_available_models());
        $sanitized['openai_model'] = in_array($input['openai_model'], $valid_models) ? $input['openai_model'] : 'gpt-4.1-mini';

        // Default thumbnail format
        $valid_formats = array_keys(WPAI_Gemini_Client::get_image_formats());
        $sanitized['default_thumbnail_format'] = in_array($input['default_thumbnail_format'] ?? '', $valid_formats)
            ? $input['default_thumbnail_format']
            : '3:2';

        return $sanitized;
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options'))
            return;

        $settings = get_option('wpai_post_gen_settings', []);
        $has_openai_key = !empty($settings['openai_api_key']);
        $has_gemini_key = !empty($settings['gemini_api_key']);
        $model = $settings['openai_model'] ?? 'gpt-4.1-mini';
        $models = WPAI_OpenAI_Client::get_available_models();
        $thumbnail_format = $settings['default_thumbnail_format'] ?? '3:2';
        $image_formats = WPAI_Gemini_Client::get_image_formats();
        ?>
        <div class="wrap wpai-settings-wrap">
            <div class="wpai-settings-header">
                <h1><span class="dashicons dashicons-edit-large"></span>
                    <?php esc_html_e('WP AI Post Generator', 'wp-ai-post-generator'); ?></h1>
                <p class="wpai-subtitle">
                    <?php esc_html_e('Geração de artigos profissionais com IA e thumbnails automáticas.', 'wp-ai-post-generator'); ?>
                </p>
            </div>

            <form method="post" action="options.php" class="wpai-settings-form">
                <?php settings_fields('wpai_post_gen_settings_group'); ?>

                <!-- OpenAI Settings -->
                <div class="wpai-card">
                    <div class="wpai-card-header">
                        <h2><span class="dashicons dashicons-admin-network"></span>
                            <?php esc_html_e('OpenAI - Geração de Texto', 'wp-ai-post-generator'); ?></h2>
                    </div>
                    <div class="wpai-card-body">
                        <div class="wpai-field">
                            <label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'wp-ai-post-generator'); ?></label>
                            <div class="wpai-input-group">
                                <input type="password" id="openai_api_key" name="wpai_post_gen_settings[openai_api_key]"
                                    placeholder="<?php echo $has_openai_key ? '••••••••••••••••' : 'sk-...'; ?>"
                                    class="wpai-input" autocomplete="off">
                                <button type="button" class="wpai-btn-icon" id="toggle-api-key">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <?php if ($has_openai_key): ?>
                                <p class="wpai-field-hint wpai-success"><span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('API Key configurada e criptografada.', 'wp-ai-post-generator'); ?></p>
                            <?php else: ?>
                                <p class="wpai-field-hint">
                                    <?php esc_html_e('Obtenha em platform.openai.com', 'wp-ai-post-generator'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="wpai-field">
                            <label for="openai_model"><?php esc_html_e('Modelo da OpenAI', 'wp-ai-post-generator'); ?></label>
                            <select id="openai_model" name="wpai_post_gen_settings[openai_model]" class="wpai-select">
                                <?php foreach ($models as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($model, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Gemini Settings -->
                <div class="wpai-card">
                    <div class="wpai-card-header" style="background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);">
                        <h2><span class="dashicons dashicons-format-image"></span>
                            <?php esc_html_e('Google Gemini - Geração de Thumbnails', 'wp-ai-post-generator'); ?></h2>
                    </div>
                    <div class="wpai-card-body">
                        <div class="wpai-field">
                            <label for="gemini_api_key"><?php esc_html_e('Gemini API Key', 'wp-ai-post-generator'); ?></label>
                            <div class="wpai-input-group">
                                <input type="password" id="gemini_api_key" name="wpai_post_gen_settings[gemini_api_key]"
                                    placeholder="<?php echo $has_gemini_key ? '••••••••••••••••' : 'AIza...'; ?>"
                                    class="wpai-input" autocomplete="off">
                                <button type="button" class="wpai-btn-icon" id="toggle-gemini-key">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <?php if ($has_gemini_key): ?>
                                <p class="wpai-field-hint wpai-success"><span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('API Key do Gemini configurada.', 'wp-ai-post-generator'); ?></p>
                            <?php else: ?>
                                <p class="wpai-field-hint">
                                    <?php esc_html_e('Obtenha em aistudio.google.com/apikey', 'wp-ai-post-generator'); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="wpai-field">
                            <label
                                for="default_thumbnail_format"><?php esc_html_e('Formato Padrão de Thumbnail', 'wp-ai-post-generator'); ?></label>
                            <select id="default_thumbnail_format" name="wpai_post_gen_settings[default_thumbnail_format]"
                                class="wpai-select">
                                <?php foreach ($image_formats as $value => $format): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($thumbnail_format, $value); ?>>
                                        <?php echo esc_html($format['label']); ?> - <?php echo esc_html($format['description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="wpai-actions">
                    <?php submit_button(__('Salvar Configurações', 'wp-ai-post-generator'), 'primary wpai-btn', 'submit', false); ?>
                    <button type="button" id="test-connection" class="wpai-btn wpai-btn-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Testar Conexão', 'wp-ai-post-generator'); ?>
                    </button>
                </div>
            </form>

            <div class="wpai-card wpai-info-card">
                <div class="wpai-card-header">
                    <h2><span class="dashicons dashicons-info"></span>
                        <?php esc_html_e('Pipeline Multi-Agente', 'wp-ai-post-generator'); ?>
                    </h2>
                </div>
                <div class="wpai-card-body">
                    <ol class="wpai-steps">
                        <li><strong>InterpreterAgent</strong> - Analisa o briefing e cria estrutura SEO</li>
                        <li><strong>WriterAgent</strong> - Escreve o artigo otimizado (E-E-A-T)</li>
                        <li><strong>ReviewerAgent</strong> - Avalia qualidade e humanização</li>
                        <li><strong>TitleAgent</strong> - Gera 5 títulos profissionais</li>
                        <li><strong>SEOAgent</strong> - Cria metadados para Rank Math</li>
                        <li><strong>ThumbnailAgent</strong> - Cria prompt e gera thumbnail (Gemini)</li>
                    </ol>
                </div>
            </div>

            <p class="wpai-credits"><?php esc_html_e('Desenvolvido por', 'wp-ai-post-generator'); ?> <a
                    href="https://dantetesta.com.br" target="_blank">Dante Testa</a> &bull;
                v<?php echo WPAI_POST_GEN_VERSION; ?></p>
        </div>
        <?php
    }

    public function add_generate_button()
    {
        global $typenow;
        if ($typenow !== 'post')
            return;

        if (!current_user_can('edit_posts'))
            return;
        ?>
        <style>
            .wpai-fab {
                position: fixed;
                bottom: 30px;
                right: 30px;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 14px 24px;
                background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
                color: #fff;
                font-size: 14px;
                font-weight: 600;
                border: none;
                border-radius: 50px;
                box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4), 0 2px 8px rgba(0, 0, 0, 0.15);
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
            }

            .wpai-fab:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 25px rgba(99, 102, 241, 0.5), 0 4px 12px rgba(0, 0, 0, 0.2);
                color: #fff;
            }

            .wpai-fab:active {
                transform: translateY(0);
            }

            .wpai-fab .dashicons {
                width: 20px;
                height: 20px;
                font-size: 20px;
            }

            @media (max-width: 782px) {
                .wpai-fab {
                    bottom: 20px;
                    right: 20px;
                    padding: 12px 16px;
                    font-size: 13px;
                }

                .wpai-fab span:last-child {
                    display: none;
                }

                .wpai-fab {
                    border-radius: 50%;
                    width: 56px;
                    height: 56px;
                    padding: 0;
                    justify-content: center;
                }
            }
        </style>
        <button type="button" class="wpai-fab wpai-generate-btn">
            <span class="dashicons dashicons-superhero-alt"></span>
            <span>Criar Post com IA</span>
        </button>
        <?php
    }
}
