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
        add_action('admin_footer', [$this, 'add_generate_button_footer']);
    }

    // Adiciona botão no footer de todas as páginas de listagem de post types
    public function add_generate_button_footer()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'edit') {
            return;
        }
        $this->add_generate_button();
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
        // Página de configurações do plugin
        if ($hook === 'toplevel_page_wp-ai-post-generator') {
            wp_enqueue_style('wpai-admin', WPAI_POST_GEN_PLUGIN_URL . 'assets/css/admin.css', [], WPAI_POST_GEN_VERSION);
            wp_enqueue_script('wpai-admin', WPAI_POST_GEN_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WPAI_POST_GEN_VERSION, true);
            
            // Localize para página de admin (mapeamento de campos)
            wp_localize_script('wpai-admin', 'wpaiPostGen', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpai_post_gen_nonce')
            ]);
            return;
        }

        // Verifica se está em uma página de listagem de post type habilitado
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'edit') {
            return;
        }
        
        $enabled_post_types = $this->get_enabled_post_types();
        if (!in_array($screen->post_type, $enabled_post_types)) {
            return;
        }

        wp_enqueue_style('wpai-admin', WPAI_POST_GEN_PLUGIN_URL . 'assets/css/admin.css', [], WPAI_POST_GEN_VERSION);
        wp_enqueue_style('wpai-modal', WPAI_POST_GEN_PLUGIN_URL . 'assets/css/modal.css', [], WPAI_POST_GEN_VERSION);
        wp_enqueue_script('wpai-admin', WPAI_POST_GEN_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WPAI_POST_GEN_VERSION, true);
        wp_enqueue_script('wpai-modal', WPAI_POST_GEN_PLUGIN_URL . 'assets/js/modal.js', ['jquery'], WPAI_POST_GEN_VERSION, true);

        // Verificar APIs disponíveis
        $settings = get_option('wpai_post_gen_settings', []);
        $has_openai_key = !empty($settings['openai_api_key']);
        $has_gemini_key = !empty($settings['gemini_api_key']);
        $has_openrouter_key = !empty($settings['openrouter_api_key']);

        // Obtém informações do post type atual
        $post_type_obj = get_post_type_object($screen->post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : 'Post';
        
        // Verifica quais taxonomias o post type suporta
        $has_categories = is_object_in_taxonomy($screen->post_type, 'category');
        $has_tags = is_object_in_taxonomy($screen->post_type, 'post_tag');

        wp_localize_script('wpai-modal', 'wpaiPostGen', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpai_post_gen_nonce'),
            'hasImageGeneration' => $has_openai_key,
            'hasGeminiKey' => $has_gemini_key,
            'hasOpenRouterKey' => $has_openrouter_key,
            'imageFormats' => WPAI_OpenAI_Client::get_image_sizes(),
            'currentPostType' => $screen->post_type,
            'currentPostTypeLabel' => $post_type_label,
            'hasCategories' => $has_categories,
            'hasTags' => $has_tags,
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
            // Só salva se não for placeholder e tiver conteúdo válido
            if (
                strlen($gemini_key) > 10 &&
                strpos($gemini_key, '•') === false &&
                strpos($gemini_key, '*') === false
            ) {
                $sanitized['gemini_api_key'] = $this->encryption->encrypt($gemini_key);
            } else {
                $sanitized['gemini_api_key'] = $settings['gemini_api_key'] ?? '';
            }
        } else {
            $sanitized['gemini_api_key'] = $settings['gemini_api_key'] ?? '';
        }

        // OpenAI Model
        $valid_models = array_keys(WPAI_OpenAI_Client::get_available_models());
        $sanitized['openai_model'] = in_array($input['openai_model'], $valid_models) ? $input['openai_model'] : 'gpt-4o-mini';

        // Default thumbnail format
        $valid_formats = array_keys(WPAI_Gemini_Client::get_image_formats());
        $sanitized['default_thumbnail_format'] = in_array($input['default_thumbnail_format'] ?? '', $valid_formats)
            ? $input['default_thumbnail_format']
            : '3:2';

        // Post Types habilitados
        $enabled_post_types = $input['enabled_post_types'] ?? ['post'];
        if (!is_array($enabled_post_types)) {
            $enabled_post_types = ['post'];
        }
        // Sanitiza cada valor e garante que são post types válidos (com show_ui)
        $valid_post_types = array_keys(get_post_types(['show_ui' => true]));
        $sanitized['enabled_post_types'] = array_filter(
            array_map('sanitize_key', $enabled_post_types),
            function ($pt) use ($valid_post_types) {
                return in_array($pt, $valid_post_types);
            }
        );
        // Se nenhum selecionado, mantém 'post' como padrão
        if (empty($sanitized['enabled_post_types'])) {
            $sanitized['enabled_post_types'] = ['post'];
        }

        return $sanitized;
    }

    // Retorna os post types disponíveis para seleção
    public function get_available_post_types()
    {
        // Busca todos os post types que têm interface de admin (show_ui = true)
        $post_types = get_post_types(['show_ui' => true], 'objects');
        $available = [];
        
        // Lista de post types de sistema para ignorar
        $excluded = [
            'attachment', 
            'revision', 
            'nav_menu_item', 
            'custom_css', 
            'customize_changeset', 
            'oembed_cache', 
            'wp_block', 
            'wp_template', 
            'wp_template_part', 
            'wp_global_styles', 
            'wp_navigation',
            'user_request',
            'wp_font_family',
            'wp_font_face'
        ];
        
        foreach ($post_types as $pt) {
            // Ignora post types de sistema
            if (in_array($pt->name, $excluded)) {
                continue;
            }
            
            // Só inclui se tiver suporte a editor (conteúdo)
            // Mas também inclui se não tiver definido supports (para não excluir CPTs mal configurados)
            $supports = get_all_post_type_supports($pt->name);
            $has_editor = empty($supports) || isset($supports['editor']) || isset($supports['title']);
            
            if ($has_editor) {
                $available[$pt->name] = $pt->label;
            }
        }
        
        // Ordena alfabeticamente pelo label
        asort($available);
        
        return $available;
    }

    // Retorna os post types habilitados para o gerador
    public function get_enabled_post_types()
    {
        $settings = get_option('wpai_post_gen_settings', []);
        $enabled = $settings['enabled_post_types'] ?? ['post'];
        
        // Garante que sempre retorne um array
        if (!is_array($enabled)) {
            $enabled = ['post'];
        }
        
        return $enabled;
    }

    // Escaneia campos disponíveis de um CPT
    public function scan_post_type_fields($post_type)
    {
        $fields = [
            'native' => [],
            'meta' => [],
            'taxonomies' => []
        ];

        // 1. Campos nativos suportados
        $supports = get_all_post_type_supports($post_type);
        
        if (isset($supports['title'])) {
            $fields['native']['title'] = ['label' => 'Título', 'type' => 'native'];
        }
        if (isset($supports['editor'])) {
            $fields['native']['content'] = ['label' => 'Conteúdo', 'type' => 'native'];
        }
        if (isset($supports['excerpt'])) {
            $fields['native']['excerpt'] = ['label' => 'Resumo (Excerpt)', 'type' => 'native'];
        }
        if (isset($supports['thumbnail'])) {
            $fields['native']['thumbnail'] = ['label' => 'Imagem Destacada', 'type' => 'native'];
        }

        // 2. Taxonomias associadas
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $tax) {
            if ($tax->public && $tax->show_ui) {
                $fields['taxonomies'][$tax->name] = [
                    'label' => $tax->label,
                    'type' => 'taxonomy',
                    'hierarchical' => $tax->hierarchical
                ];
            }
        }

        // 3. Meta fields registrados (detecta ACF, Meta Box, Pods, CMB2)
        $fields['meta'] = $this->detect_meta_fields($post_type);

        return $fields;
    }

    // Detecta meta fields de plugins populares
    private function detect_meta_fields($post_type)
    {
        $meta_fields = [];

        // ACF (Advanced Custom Fields) - Versão melhorada
        if (function_exists('acf_get_field_groups')) {
            $groups = acf_get_field_groups(['post_type' => $post_type]);
            foreach ($groups as $group) {
                $acf_fields = acf_get_fields($group['key']);
                if ($acf_fields) {
                    $this->extract_acf_fields($acf_fields, $meta_fields);
                }
            }
        }

        // Meta Box
        if (function_exists('rwmb_get_registry')) {
            $registry = rwmb_get_registry('meta_box');
            if ($registry) {
                $meta_boxes = $registry->all();
                foreach ($meta_boxes as $meta_box) {
                    if (isset($meta_box['post_types']) && in_array($post_type, (array)$meta_box['post_types'])) {
                        foreach ($meta_box['fields'] as $field) {
                            $allowed_types = ['text', 'textarea', 'wysiwyg', 'url', 'email', 'number', 'oembed', 'slider', 'range'];
                            if (in_array($field['type'], $allowed_types)) {
                                $meta_fields[$field['id']] = [
                                    'label' => $field['name'] ?? $field['id'],
                                    'type' => 'metabox',
                                    'field_type' => $field['type']
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Pods
        if (function_exists('pods')) {
            $pod = pods($post_type, null, false);
            if ($pod && $pod->valid()) {
                $pod_fields = $pod->fields();
                foreach ($pod_fields as $field_name => $field) {
                    $allowed_types = ['text', 'paragraph', 'wysiwyg', 'website', 'email', 'number', 'code', 'phone'];
                    if (in_array($field['type'], $allowed_types)) {
                        $meta_fields[$field_name] = [
                            'label' => $field['label'],
                            'type' => 'pods',
                            'field_type' => $field['type']
                        ];
                    }
                }
            }
        }

        // Fallback: Meta keys registradas via register_meta
        $registered_meta = get_registered_meta_keys('post', $post_type);
        foreach ($registered_meta as $key => $args) {
            if (!isset($meta_fields[$key]) && strpos($key, '_') !== 0) {
                $meta_fields[$key] = [
                    'label' => $args['description'] ?? ucfirst(str_replace(['_', '-'], ' ', $key)),
                    'type' => 'registered',
                    'field_type' => $args['type'] ?? 'string'
                ];
            }
        }

        // Fallback final: Busca meta keys existentes no banco de dados
        $db_meta_keys = $this->get_meta_keys_from_database($post_type);
        foreach ($db_meta_keys as $key) {
            if (!isset($meta_fields[$key])) {
                $meta_fields[$key] = [
                    'label' => ucfirst(str_replace(['_', '-'], ' ', $key)),
                    'type' => 'database',
                    'field_type' => 'string'
                ];
            }
        }

        return $meta_fields;
    }

    // Extrai campos ACF recursivamente (inclui campos de grupos e repeaters)
    private function extract_acf_fields($fields, &$meta_fields, $prefix = '')
    {
        $allowed_types = ['text', 'textarea', 'wysiwyg', 'url', 'email', 'number', 'oembed', 'range', 'password'];
        
        foreach ($fields as $field) {
            $field_name = $prefix ? $prefix . '_' . $field['name'] : $field['name'];
            
            // Campos de texto compatíveis
            if (in_array($field['type'], $allowed_types)) {
                $meta_fields[$field['name']] = [
                    'label' => $field['label'],
                    'type' => 'acf',
                    'field_type' => $field['type'],
                    'key' => $field['key']
                ];
            }
            
            // Campos de imagem (para thumbnail)
            if ($field['type'] === 'image') {
                $meta_fields[$field['name']] = [
                    'label' => $field['label'] . ' (Imagem)',
                    'type' => 'acf',
                    'field_type' => 'image',
                    'key' => $field['key']
                ];
            }
            
            // Se for grupo, extrai campos internos
            if ($field['type'] === 'group' && !empty($field['sub_fields'])) {
                $this->extract_acf_fields($field['sub_fields'], $meta_fields, $field['name']);
            }
        }
    }

    // Busca meta keys existentes no banco de dados para o post type
    private function get_meta_keys_from_database($post_type)
    {
        global $wpdb;
        
        // Prefixos de meta keys internas do WordPress para ignorar
        $excluded_prefixes = [
            '_edit_lock', '_edit_last', '_wp_page_template', '_wp_trash_',
            '_wp_desired_post_slug', '_thumbnail_id', '_wp_attached_file',
            '_wp_attachment_metadata', '_menu_item_', '_pingme', '_encloseme',
            '_trackbackme', '_wp_old_slug', '_wp_old_date', '_oembed_',
            '_transient_', 'rank_math_', '_yoast_', '_aioseo_', '_elementor_',
            '_wpml_', '_icl_', 'wpcf-', '_wc_', '_billing_', '_shipping_',
            '_order_', '_customer_', '_product_', '_stock_', '_price',
            '_sku', '_virtual', '_downloadable', '_sold_individually'
        ];
        
        // Busca todas as meta keys do post type (sem filtrar por underscore)
        $query = $wpdb->prepare("
            SELECT DISTINCT pm.meta_key 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key != ''
            ORDER BY pm.meta_key ASC
            LIMIT 100
        ", $post_type);
        
        $results = $wpdb->get_col($query);
        
        // Filtra meta keys internas
        $filtered = [];
        foreach ($results as $key) {
            if (empty($key)) continue;
            
            $skip = false;
            foreach ($excluded_prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }
            
            // Ignora meta keys que são referências internas do ACF (começam com _ e têm field_)
            if (!$skip && strpos($key, '_') === 0 && strpos($key, 'field_') !== false) {
                $skip = true;
            }
            
            if (!$skip) {
                $filtered[] = $key;
            }
        }
        
        return array_slice($filtered, 0, 50);
    }

    // Retorna os mapeamentos salvos para um post type
    public function get_field_mappings($post_type)
    {
        $settings = get_option('wpai_post_gen_settings', []);
        $mappings = $settings['field_mappings'] ?? [];
        return $mappings[$post_type] ?? [];
    }

    // Campos que o plugin gera
    public function get_generated_fields()
    {
        return [
            'title' => ['label' => 'Título do Artigo', 'description' => 'Título SEO otimizado'],
            'content' => ['label' => 'Conteúdo', 'description' => 'Artigo completo em HTML'],
            'excerpt' => ['label' => 'Resumo', 'description' => 'Resumo curto para excerpt'],
            'meta_title' => ['label' => 'Meta Title (SEO)', 'description' => 'Título para mecanismos de busca'],
            'meta_description' => ['label' => 'Meta Description (SEO)', 'description' => 'Descrição para SERP'],
            'focus_keyword' => ['label' => 'Focus Keyword', 'description' => 'Palavra-chave principal'],
            'secondary_keywords' => ['label' => 'Keywords Secundárias', 'description' => 'Lista de palavras-chave'],
            'thumbnail' => ['label' => 'Thumbnail', 'description' => 'Imagem destacada gerada'],
            'tags' => ['label' => 'Tags', 'description' => 'Tags sugeridas pela IA']
        ];
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options'))
            return;

        $settings = get_option('wpai_post_gen_settings', []);
        $has_openai_key = !empty($settings['openai_api_key']);
        $has_gemini_key = !empty($settings['gemini_api_key']);
        $model = $settings['openai_model'] ?? 'gpt-4o-mini';
        $models = WPAI_OpenAI_Client::get_available_models();
        $thumbnail_format = $settings['default_thumbnail_format'] ?? '3:2';
        $image_formats = WPAI_Gemini_Client::get_image_formats();
        $available_post_types = $this->get_available_post_types();
        $enabled_post_types = $this->get_enabled_post_types();
        ?>
        <div class="wrap wpai-settings-wrap">
            <div class="wpai-settings-header">
                <h1><span class="dashicons dashicons-edit-large"></span>
                    <?php esc_html_e('WP AI Post Generator', 'wp-ai-post-generator'); ?></h1>
                <p class="wpai-subtitle">
                    <?php esc_html_e('Geração de artigos profissionais com IA e thumbnails automáticas.', 'wp-ai-post-generator'); ?>
                </p>
            </div>

            <!-- Tabs Navigation -->
            <div class="wpai-admin-tabs">
                <button class="wpai-admin-tab active" data-tab="openai">
                    <span class="dashicons dashicons-admin-network"></span> OpenAI
                    <?php if ($has_openai_key): ?><span class="wpai-tab-badge success">✓</span><?php endif; ?>
                </button>
                <button class="wpai-admin-tab" data-tab="gemini">
                    <span class="dashicons dashicons-cloud"></span> Gemini
                    <?php if ($has_gemini_key): ?><span class="wpai-tab-badge success">✓</span><?php endif; ?>
                </button>
                <button class="wpai-admin-tab" data-tab="pipeline">
                    <span class="dashicons dashicons-randomize"></span> Pipeline
                </button>
                <button class="wpai-admin-tab" data-tab="posttypes">
                    <span class="dashicons dashicons-admin-post"></span> Post Types
                </button>
            </div>

            <form method="post" action="options.php" class="wpai-settings-form">
                <?php settings_fields('wpai_post_gen_settings_group'); ?>

                <!-- Tab: OpenAI -->
                <div class="wpai-admin-panel active" id="wpai-panel-openai">
                    <div class="wpai-panel-header purple">
                        <h2>OpenAI - Geração de Texto</h2>
                        <p>Configure sua API Key da OpenAI para gerar artigos com GPT-4</p>
                    </div>
                    <div class="wpai-panel-body">
                        <div class="wpai-field">
                            <label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'wp-ai-post-generator'); ?></label>
                            <div class="wpai-api-key-wrapper">
                                <div class="wpai-api-key-input">
                                    <span class="wpai-key-icon">🔑</span>
                                    <input type="password" id="openai_api_key" name="wpai_post_gen_settings[openai_api_key]"
                                        placeholder="<?php echo $has_openai_key ? '••••••••••••••••••••••••••••••••' : 'sk-...'; ?>"
                                        class="wpai-input-key" autocomplete="off" data-has-key="<?php echo $has_openai_key ? '1' : '0'; ?>">
                                </div>
                                <div class="wpai-api-key-actions">
                                    <button type="button" class="wpai-key-btn" id="toggle-api-key" title="Mostrar/Ocultar">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <button type="button" class="wpai-key-btn" id="copy-api-key" title="Copiar" <?php echo !$has_openai_key ? 'disabled' : ''; ?>>
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </div>
                            </div>
                            <?php if ($has_openai_key): ?>
                                <p class="wpai-field-hint wpai-success"><span class="dashicons dashicons-yes-alt"></span> API Key configurada e criptografada.</p>
                            <?php else: ?>
                                <p class="wpai-field-hint">Obtenha em <a href="https://platform.openai.com" target="_blank">platform.openai.com</a></p>
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

                        <div class="wpai-field-actions">
                            <button type="button" id="test-openai" class="wpai-btn-small wpai-btn-outline">
                                <span class="dashicons dashicons-update"></span> Testar Conexão
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab: Gemini -->
                <div class="wpai-admin-panel" id="wpai-panel-gemini">
                    <div class="wpai-panel-header blue">
                        <h2>Google Gemini - Texto, Imagens e Áudio</h2>
                        <p>Configure sua API Key do Gemini para usar como alternativa ou para thumbnails</p>
                    </div>
                    <div class="wpai-panel-body">
                        <div class="wpai-field">
                            <label for="gemini_api_key"><?php esc_html_e('Gemini API Key', 'wp-ai-post-generator'); ?></label>
                            <div class="wpai-api-key-wrapper">
                                <div class="wpai-api-key-input">
                                    <span class="wpai-key-icon">🔑</span>
                                    <input type="password" id="gemini_api_key" name="wpai_post_gen_settings[gemini_api_key]"
                                        placeholder="<?php echo $has_gemini_key ? '••••••••••••••••••••••••••••••••' : 'AIza...'; ?>"
                                        class="wpai-input-key" autocomplete="off" data-has-key="<?php echo $has_gemini_key ? '1' : '0'; ?>">
                                </div>
                                <div class="wpai-api-key-actions">
                                    <button type="button" class="wpai-key-btn" id="toggle-gemini-key" title="Mostrar/Ocultar">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <button type="button" class="wpai-key-btn" id="copy-gemini-key" title="Copiar" <?php echo !$has_gemini_key ? 'disabled' : ''; ?>>
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </div>
                            </div>
                            <?php if ($has_gemini_key): ?>
                                <p class="wpai-field-hint wpai-success"><span class="dashicons dashicons-yes-alt"></span> API Key do Gemini configurada.</p>
                            <?php else: ?>
                                <p class="wpai-field-hint">Obtenha em <a href="https://aistudio.google.com/apikey" target="_blank">aistudio.google.com/apikey</a></p>
                            <?php endif; ?>
                        </div>

                        <div class="wpai-gemini-features">
                            <h4>Recursos disponíveis com Gemini:</h4>
                            <ul>
                                <li><span class="dashicons dashicons-yes"></span> Geração de texto (alternativa à OpenAI)</li>
                                <li><span class="dashicons dashicons-yes"></span> Geração de thumbnails (gratuito)</li>
                                <li><span class="dashicons dashicons-yes"></span> Transcrição de áudio</li>
                            </ul>
                        </div>

                        <div class="wpai-field-actions">
                            <button type="button" id="test-gemini" class="wpai-btn-small wpai-btn-outline">
                                <span class="dashicons dashicons-update"></span> Testar Conexão
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab: Post Types -->
                <div class="wpai-admin-panel" id="wpai-panel-posttypes">
                    <div class="wpai-panel-header orange">
                        <h2>Post Types Habilitados</h2>
                        <p>Selecione em quais tipos de conteúdo o gerador de IA estará disponível</p>
                    </div>
                    <div class="wpai-panel-body">
                        <div class="wpai-field">
                            <label class="wpai-label-main"><?php esc_html_e('Tipos de Conteúdo', 'wp-ai-post-generator'); ?></label>
                            <p class="wpai-field-desc">O botão "Criar com IA" aparecerá na listagem dos post types selecionados abaixo. Clique em ⚙️ para mapear campos.</p>
                            
                            <div class="wpai-post-types-grid">
                                <?php foreach ($available_post_types as $pt_slug => $pt_label): ?>
                                    <div class="wpai-post-type-item-wrapper">
                                        <label class="wpai-post-type-item">
                                            <input type="checkbox" 
                                                   name="wpai_post_gen_settings[enabled_post_types][]" 
                                                   value="<?php echo esc_attr($pt_slug); ?>"
                                                   <?php checked(in_array($pt_slug, $enabled_post_types)); ?>
                                                   data-pt="<?php echo esc_attr($pt_slug); ?>">
                                            <span class="wpai-pt-checkbox">
                                                <span class="dashicons dashicons-yes"></span>
                                            </span>
                                            <span class="wpai-pt-info">
                                                <span class="wpai-pt-label"><?php echo esc_html($pt_label); ?></span>
                                                <span class="wpai-pt-slug"><?php echo esc_html($pt_slug); ?></span>
                                            </span>
                                        </label>
                                        <button type="button" class="wpai-pt-config-btn" 
                                                data-pt="<?php echo esc_attr($pt_slug); ?>" 
                                                data-label="<?php echo esc_attr($pt_label); ?>"
                                                title="Configurar mapeamento de campos">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <p class="wpai-field-hint">
                                <span class="dashicons dashicons-info"></span>
                                CPTs (Custom Post Types) criados por plugins ou temas também aparecem aqui automaticamente.
                            </p>
                        </div>

                        <!-- Modal de Mapeamento de Campos -->
                        <div class="wpai-mapping-modal" id="wpai-mapping-modal" style="display: none;">
                            <div class="wpai-mapping-overlay"></div>
                            <div class="wpai-mapping-content">
                                <div class="wpai-mapping-header">
                                    <h3><span class="dashicons dashicons-randomize"></span> Mapeamento de Campos: <span id="wpai-mapping-pt-name"></span></h3>
                                    <button type="button" class="wpai-mapping-close">&times;</button>
                                </div>
                                <div class="wpai-mapping-body">
                                    <p class="wpai-mapping-desc">Configure como os campos gerados pela IA serão salvos no seu CPT.</p>
                                    
                                    <div class="wpai-mapping-loading" id="wpai-mapping-loading">
                                        <span class="dashicons dashicons-update wpai-spin"></span> Escaneando campos...
                                    </div>
                                    
                                    <div class="wpai-mapping-grid" id="wpai-mapping-grid" style="display: none;">
                                        <!-- Preenchido via JavaScript -->
                                    </div>
                                </div>
                                <div class="wpai-mapping-footer">
                                    <button type="button" class="wpai-btn-secondary" id="wpai-mapping-cancel">Cancelar</button>
                                    <button type="button" class="wpai-btn-primary" id="wpai-mapping-save">
                                        <span class="dashicons dashicons-saved"></span> Salvar Mapeamento
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Pipeline -->
                <div class="wpai-admin-panel" id="wpai-panel-pipeline">
                    <div class="wpai-panel-header green">
                        <h2>Pipeline Multi-Agente</h2>
                        <p>Conheça os agentes que trabalham para criar seu conteúdo</p>
                    </div>
                    <div class="wpai-panel-body">
                        <div class="wpai-pipeline-grid">
                            <div class="wpai-agent-card">
                                <div class="wpai-agent-icon">🔍</div>
                                <h4>InterpreterAgent</h4>
                                <p>Analisa o briefing e cria estrutura SEO otimizada</p>
                            </div>
                            <div class="wpai-agent-card">
                                <div class="wpai-agent-icon">✍️</div>
                                <h4>WriterAgent</h4>
                                <p>Escreve o artigo otimizado seguindo E-E-A-T</p>
                            </div>
                            <div class="wpai-agent-card">
                                <div class="wpai-agent-icon">🔎</div>
                                <h4>ReviewerAgent</h4>
                                <p>Avalia qualidade, SEO e humanização</p>
                            </div>
                            <div class="wpai-agent-card">
                                <div class="wpai-agent-icon">🏷️</div>
                                <h4>TitleAgent</h4>
                                <p>Gera 4 títulos profissionais otimizados</p>
                            </div>
                            <div class="wpai-agent-card">
                                <div class="wpai-agent-icon">📊</div>
                                <h4>SEOAgent</h4>
                                <p>Cria metadados para Rank Math SEO</p>
                            </div>
                            <div class="wpai-agent-card">
                                <div class="wpai-agent-icon">🖼️</div>
                                <h4>ThumbnailAgent</h4>
                                <p>Cria prompt e gera thumbnail com IA</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wpai-actions">
                    <?php submit_button(__('Salvar Configurações', 'wp-ai-post-generator'), 'primary wpai-btn', 'submit', false); ?>
                </div>
            </form>

            <p class="wpai-credits"><?php esc_html_e('Desenvolvido por', 'wp-ai-post-generator'); ?> <a
                    href="https://dantetesta.com.br" target="_blank">Dante Testa</a> &bull;
                v<?php echo WPAI_POST_GEN_VERSION; ?></p>
        </div>
        <?php
    }

    public function add_generate_button()
    {
        global $typenow;
        
        // Verifica se o post type atual está habilitado
        $enabled_post_types = $this->get_enabled_post_types();
        if (!in_array($typenow, $enabled_post_types)) {
            return;
        }

        // Verifica permissão para o post type
        $post_type_obj = get_post_type_object($typenow);
        if (!$post_type_obj || !current_user_can($post_type_obj->cap->edit_posts)) {
            return;
        }
        ?>
        <style>
            /* Botão no topo - discreto */
            .wpai-top-btn {
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
                padding: 6px 12px !important;
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
                color: #fff !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                border: none !important;
                border-radius: 6px !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                text-decoration: none !important;
                margin-left: 8px !important;
                vertical-align: middle !important;
            }
            .wpai-top-btn:hover {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%) !important;
                color: #fff !important;
                transform: translateY(-1px) !important;
            }
            .wpai-top-btn .dashicons {
                width: 14px !important;
                height: 14px !important;
                font-size: 14px !important;
            }

            /* Botão flutuante - FAB */
            .wpai-fab {
                position: fixed !important;
                bottom: 30px !important;
                right: 30px !important;
                z-index: 9999 !important;
                display: flex !important;
                align-items: center !important;
                gap: 10px !important;
                padding: 14px 28px !important;
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
                color: #fff !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                border: none !important;
                border-radius: 50px !important;
                box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4), 0 2px 8px rgba(0, 0, 0, 0.15) !important;
                cursor: pointer !important;
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
                text-decoration: none !important;
            }

            .wpai-fab:hover {
                transform: translateY(-3px) scale(1.02) !important;
                box-shadow: 0 8px 30px rgba(99, 102, 241, 0.5), 0 4px 12px rgba(0, 0, 0, 0.2) !important;
                color: #fff !important;
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%) !important;
            }

            .wpai-fab:active {
                transform: translateY(-1px) scale(1) !important;
            }

            .wpai-fab .dashicons {
                width: 20px !important;
                height: 20px !important;
                font-size: 20px !important;
            }

            @media (max-width: 782px) {
                .wpai-fab {
                    bottom: 20px !important;
                    right: 20px !important;
                    padding: 0 !important;
                    border-radius: 50% !important;
                    width: 56px !important;
                    height: 56px !important;
                    justify-content: center !important;
                }
                .wpai-fab span:last-child {
                    display: none !important;
                }
                .wpai-top-btn span:last-child {
                    display: none !important;
                }
            }
        </style>
        
        <!-- Botão flutuante -->
        <button type="button" class="wpai-fab wpai-generate-btn">
            <span class="dashicons dashicons-superhero-alt"></span>
            <span>Criar Post com IA</span>
        </button>

        <script>
        jQuery(document).ready(function($) {
            // Adiciona botão discreto no topo ao lado do título
            var $title = $('.wp-heading-inline');
            if ($title.length && !$('.wpai-top-btn').length) {
                $title.after('<button type="button" class="wpai-top-btn wpai-generate-btn"><span class="dashicons dashicons-superhero-alt"></span><span>IA</span></button>');
            }
        });
        </script>
        <?php
    }
}
