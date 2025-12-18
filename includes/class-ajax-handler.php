<?php
/**
 * Handler AJAX com Suporte a SEO e Thumbnails
 * @package WP_AI_Post_Generator
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @updated 2025-12-11 10:30
 */

if (!defined('ABSPATH'))
    exit;

class WPAI_Ajax_Handler
{

    public function __construct()
    {
        add_action('wp_ajax_wpai_generate_post', [$this, 'generate_post']);
        add_action('wp_ajax_wpai_save_post', [$this, 'save_post']);
        add_action('wp_ajax_wpai_generate_thumbnail', [$this, 'generate_thumbnail']);
        add_action('wp_ajax_wpai_test_connection', [$this, 'test_connection']);
        add_action('wp_ajax_wpai_transcribe_audio', [$this, 'transcribe_audio']);
        add_action('wp_ajax_wpai_improve_description', [$this, 'improve_description']);
        add_action('wp_ajax_wpai_get_categories', [$this, 'get_categories']);
        add_action('wp_ajax_wpai_reveal_api_key', [$this, 'reveal_api_key']);
        add_action('wp_ajax_wpai_test_gemini', [$this, 'test_gemini']);
        add_action('wp_ajax_wpai_test_openrouter', [$this, 'test_openrouter']);
        add_action('wp_ajax_wpai_scan_cpt_fields', [$this, 'scan_cpt_fields']);
        add_action('wp_ajax_wpai_save_field_mappings', [$this, 'save_field_mappings']);
    }

    // Escaneia campos de um CPT para mapeamento
    public function scan_cpt_fields()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $post_type = sanitize_key($_POST['post_type'] ?? '');
        
        if (empty($post_type) || !post_type_exists($post_type)) {
            wp_send_json_error(['message' => __('Post type inválido.', 'wp-ai-post-generator')]);
        }

        $admin = new WPAI_Admin();
        $fields = $admin->scan_post_type_fields($post_type);
        $mappings = $admin->get_field_mappings($post_type);
        $generated_fields = $admin->get_generated_fields();

        wp_send_json_success([
            'post_type' => $post_type,
            'fields' => $fields,
            'mappings' => $mappings,
            'generated_fields' => $generated_fields
        ]);
    }

    // Salva mapeamentos de campos para um CPT
    public function save_field_mappings()
    {
        // Log para debug
        error_log('WPAI: save_field_mappings chamado');
        error_log('WPAI: POST data: ' . print_r($_POST, true));

        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            error_log('WPAI: Permissão negada');
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $post_type = sanitize_key($_POST['post_type'] ?? '');
        $mappings_raw = isset($_POST['mappings']) ? $_POST['mappings'] : '{}';
        
        // Decodifica JSON se for string
        if (is_string($mappings_raw)) {
            $mappings = json_decode(stripslashes($mappings_raw), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('WPAI: Erro ao decodificar JSON: ' . json_last_error_msg());
                $mappings = [];
            }
        } else {
            $mappings = $mappings_raw;
        }
        
        error_log('WPAI: post_type = ' . $post_type);
        error_log('WPAI: mappings recebidos = ' . print_r($mappings, true));

        if (empty($post_type)) {
            wp_send_json_error(['message' => __('Post type inválido.', 'wp-ai-post-generator')]);
        }

        // Sanitiza os mapeamentos
        $sanitized_mappings = [];
        if (is_array($mappings)) {
            foreach ($mappings as $generated_field => $target) {
                $gen_field = sanitize_key($generated_field);
                
                // Verifica se target é array ou string
                if (is_array($target)) {
                    $target_field = sanitize_text_field($target['field'] ?? '');
                    $target_type = sanitize_key($target['type'] ?? 'native');
                } else {
                    // Se for string, pula
                    continue;
                }
                
                if (!empty($target_field) && !empty($gen_field)) {
                    $sanitized_mappings[$gen_field] = [
                        'field' => $target_field,
                        'type' => $target_type
                    ];
                }
            }
        }

        error_log('WPAI: mappings sanitizados = ' . print_r($sanitized_mappings, true));

        // Salva nas configurações
        $settings = get_option('wpai_post_gen_settings', []);
        if (!isset($settings['field_mappings'])) {
            $settings['field_mappings'] = [];
        }
        $settings['field_mappings'][$post_type] = $sanitized_mappings;
        
        $saved = update_option('wpai_post_gen_settings', $settings);
        error_log('WPAI: update_option resultado = ' . ($saved ? 'true' : 'false'));

        wp_send_json_success([
            'message' => __('Mapeamentos salvos com sucesso!', 'wp-ai-post-generator'),
            'mappings' => $sanitized_mappings,
            'post_type' => $post_type,
            'saved' => $saved
        ]);
    }

    // Revela a API key descriptografada (apenas para admins)
    public function reveal_api_key()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $key_type = sanitize_text_field($_POST['key_type'] ?? '');
        
        if (!in_array($key_type, ['openai', 'gemini', 'openrouter'])) {
            wp_send_json_error(['message' => __('Tipo de chave inválido.', 'wp-ai-post-generator')]);
        }

        $settings = get_option('wpai_post_gen_settings', []);
        $encryption = new WPAI_Encryption();
        
        $key_fields = ['openai' => 'openai_api_key', 'gemini' => 'gemini_api_key', 'openrouter' => 'openrouter_api_key'];
        $key_field = $key_fields[$key_type];
        $encrypted_key = $settings[$key_field] ?? '';
        
        if (empty($encrypted_key)) {
            wp_send_json_error(['message' => __('Chave não configurada.', 'wp-ai-post-generator')]);
        }

        $decrypted_key = $encryption->decrypt($encrypted_key);
        
        if (empty($decrypted_key)) {
            wp_send_json_error(['message' => __('Erro ao descriptografar chave.', 'wp-ai-post-generator')]);
        }

        wp_send_json_success(['key' => $decrypted_key]);
    }

    public function get_categories()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $categories = get_categories(['hide_empty' => false]);
        $data = [];

        foreach ($categories as $cat) {
            $data[] = [
                'term_id' => $cat->term_id,
                'name' => $cat->name
            ];
        }

        wp_send_json_success($data);
    }

    public function generate_post()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $params = [
            'desired_title' => sanitize_text_field($_POST['desired_title'] ?? ''),
            'subject_context' => sanitize_textarea_field($_POST['subject_context'] ?? ''),
            'tone' => sanitize_text_field($_POST['tone'] ?? 'Neutro'),
            'writing_type' => sanitize_text_field($_POST['writing_type'] ?? 'Artigo'),
            'person_type' => sanitize_text_field($_POST['person_type'] ?? 'Terceira pessoa'),
            'word_count' => absint($_POST['word_count'] ?? 1500),
            'publish_mode' => sanitize_text_field($_POST['publish_mode'] ?? 'Rascunho'),
            'generate_thumbnail' => filter_var($_POST['generate_thumbnail'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'thumbnail_format' => sanitize_text_field($_POST['thumbnail_format'] ?? '3:2'),
            'text_ai' => sanitize_text_field($_POST['text_ai'] ?? 'openai'),
        ];

        // Log para debug da IA selecionada
        error_log('WPAI Debug - Text AI recebida: ' . $params['text_ai']);

        if (empty($params['desired_title']) || empty($params['subject_context'])) {
            wp_send_json_error(['message' => __('Título e contexto são obrigatórios.', 'wp-ai-post-generator')]);
        }

        $multi_agent = new WPAI_Multi_Agent();
        $result = $multi_agent->run_pipeline($params);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ]);
        }

        // Retornar dados para o usuario escolher o titulo
        wp_send_json_success([
            'message' => __('Artigo gerado! Escolha o título.', 'wp-ai-post-generator'),
            'text_ai_used' => $params['text_ai'],
            'pipeline' => [
                'briefing' => $result['briefing'],
                'article' => $result['article'],
                'reviews' => $result['reviews'],
                'iterations' => $result['iterations'],
                'titles' => $result['titles'],
                'seo' => $result['seo'],
                'thumbnail_prompt' => $result['thumbnail_prompt'] ?? null,
                'thumbnail_data' => $result['thumbnail_data'] ?? null,
                'execution_log' => $result['execution_log'],
            ],
        ]);
    }

    public function save_post()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'draft');
        $category_id = absint($_POST['category'] ?? 0);
        $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', (array) $_POST['tags']) : [];
        $thumbnail_id = absint($_POST['thumbnail_id'] ?? 0);
        $seo_data = isset($_POST['seo']) ? $this->sanitize_seo_data($_POST['seo']) : [];
        $post_type = sanitize_key($_POST['post_type'] ?? 'post');

        // Valida se o post type está habilitado e existe
        $settings = get_option('wpai_post_gen_settings', []);
        $enabled_post_types = $settings['enabled_post_types'] ?? ['post'];
        $valid_post_types = array_keys(get_post_types(['show_ui' => true]));
        
        if (!in_array($post_type, $enabled_post_types) || !in_array($post_type, $valid_post_types)) {
            $post_type = 'post';
        }

        // Carregar mapeamentos de campos para este post type
        $field_mappings = $settings['field_mappings'][$post_type] ?? [];

        // Preparar dados gerados pela IA
        $generated_data = [
            'title' => $title,
            'content' => $content,
            'excerpt' => sanitize_textarea_field($_POST['excerpt'] ?? ''),
            'meta_title' => $seo_data['meta_title'] ?? '',
            'meta_description' => $seo_data['meta_description'] ?? '',
            'focus_keyword' => $seo_data['focus_keyword'] ?? '',
            'secondary_keywords' => $seo_data['secondary_keywords'] ?? [],
            'tags' => $tags,
            'thumbnail' => $thumbnail_id
        ];

        // Converter status
        $post_status = ($status === 'publish' || $status === 'Publicado') ? 'publish' : 'draft';

        // Iniciar dados do post com campos nativos ou mapeados
        $post_data = [
            'post_status' => $post_status,
            'post_type' => $post_type,
            'post_author' => get_current_user_id(),
        ];

        // Aplicar mapeamentos para campos nativos do post
        $title_mapped = $this->apply_native_mapping('title', $generated_data, $field_mappings, $post_data);
        $content_mapped = $this->apply_native_mapping('content', $generated_data, $field_mappings, $post_data);
        
        // Se não mapeado, usar padrão
        if (!$title_mapped) {
            $post_data['post_title'] = $title;
        }
        if (!$content_mapped) {
            $post_data['post_content'] = $content;
        }

        // Excerpt
        $this->apply_native_mapping('excerpt', $generated_data, $field_mappings, $post_data);

        // Verificar se tem pelo menos título
        if (empty($post_data['post_title']) && empty($title)) {
            wp_send_json_error(['message' => __('Título é obrigatório.', 'wp-ai-post-generator')]);
        }
        if (empty($post_data['post_title'])) {
            $post_data['post_title'] = $title;
        }

        // Adicionar categoria (apenas para post types que suportam e sem mapeamento customizado)
        if ($category_id > 0 && is_object_in_taxonomy($post_type, 'category')) {
            $post_data['post_category'] = [$category_id];
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }

        // Adicionar tags (apenas para post types que suportam)
        if (!empty($tags) && is_object_in_taxonomy($post_type, 'post_tag')) {
            wp_set_post_tags($post_id, $tags, false);
        }

        // Aplicar mapeamentos para meta fields
        $this->apply_meta_mappings($post_id, $generated_data, $field_mappings);

        // Aplicar mapeamentos para taxonomias customizadas
        $this->apply_taxonomy_mappings($post_id, $generated_data, $field_mappings);

        // Extrair focus keyword para uso em SEO da imagem
        $focus_keyword = $generated_data['focus_keyword'];

        // Definir thumbnail (ID ou base64)
        $thumbnail_mapping = $field_mappings['thumbnail'] ?? null;
        if ($thumbnail_mapping && $thumbnail_mapping['type'] === 'meta') {
            // Thumbnail mapeado para meta field
            if ($thumbnail_id > 0) {
                $this->save_to_meta_field($post_id, $thumbnail_mapping['field'], $thumbnail_id, 'acf');
            }
        } else {
            // Thumbnail nativo
            if ($thumbnail_id > 0) {
                set_post_thumbnail($post_id, $thumbnail_id);
                if (!empty($focus_keyword)) {
                    $this->update_attachment_seo($thumbnail_id, $title, $focus_keyword);
                }
            } elseif (!empty($_POST['thumbnail_base64'])) {
                $attach_id = $this->save_thumbnail_from_base64(
                    $_POST['thumbnail_base64'],
                    $_POST['thumbnail_mime'] ?? 'image/jpeg',
                    $title,
                    $post_id,
                    $focus_keyword
                );
                if ($attach_id && !is_wp_error($attach_id)) {
                    set_post_thumbnail($post_id, $attach_id);
                }
            }
        }

        // Salvar SEO (Rank Math) - apenas se não houver mapeamentos customizados
        if (!empty($seo_data) && class_exists('RankMath')) {
            $all_keywords = [];
            if (!empty($seo_data['focus_keyword'])) {
                $all_keywords[] = sanitize_text_field($seo_data['focus_keyword']);
            }
            if (!empty($seo_data['secondary_keywords']) && is_array($seo_data['secondary_keywords'])) {
                foreach (array_slice($seo_data['secondary_keywords'], 0, 4) as $kw) {
                    $all_keywords[] = sanitize_text_field($kw);
                }
            }
            if (!empty($all_keywords)) {
                update_post_meta($post_id, 'rank_math_focus_keyword', implode(',', $all_keywords));
            }

            $seo_title = !empty($seo_data['meta_title']) ? $seo_data['meta_title'] : $title;
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_title));

            if (!empty($seo_data['meta_description'])) {
                update_post_meta($post_id, 'rank_math_description', sanitize_text_field($seo_data['meta_description']));
            }
        }

        wp_send_json_success([
            'message' => $post_status === 'publish'
                ? __('Post publicado com sucesso!', 'wp-ai-post-generator')
                : __('Rascunho salvo com sucesso!', 'wp-ai-post-generator'),
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
        ]);
    }

    // Aplica mapeamento para campos nativos do post
    private function apply_native_mapping($field_key, $generated_data, $mappings, &$post_data)
    {
        if (!isset($mappings[$field_key])) {
            return false;
        }

        $mapping = $mappings[$field_key];
        if ($mapping['type'] !== 'native') {
            return false;
        }

        $value = $generated_data[$field_key] ?? '';
        if (empty($value)) {
            return false;
        }

        $native_fields = [
            'title' => 'post_title',
            'content' => 'post_content',
            'excerpt' => 'post_excerpt'
        ];

        $target_field = $mapping['field'];
        if (isset($native_fields[$target_field])) {
            $post_data[$native_fields[$target_field]] = $value;
            return true;
        }

        return false;
    }

    // Aplica mapeamentos para meta fields
    private function apply_meta_mappings($post_id, $generated_data, $mappings)
    {
        foreach ($mappings as $gen_field => $mapping) {
            if ($mapping['type'] !== 'meta') {
                continue;
            }

            $value = $generated_data[$gen_field] ?? '';
            if (empty($value)) {
                continue;
            }

            // Converter array para string se necessário
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $this->save_to_meta_field($post_id, $mapping['field'], $value, 'meta');
        }
    }

    // Salva valor em meta field (compatível com ACF, Pods, Meta Box)
    private function save_to_meta_field($post_id, $field_key, $value, $type = 'meta')
    {
        // ACF
        if (function_exists('update_field')) {
            update_field($field_key, $value, $post_id);
            return;
        }

        // Pods
        if (function_exists('pods')) {
            $pod = pods(get_post_type($post_id), $post_id);
            if ($pod && $pod->valid()) {
                $pod->save([$field_key => $value]);
                return;
            }
        }

        // Meta Box ou padrão WP
        update_post_meta($post_id, $field_key, $value);
    }

    // Aplica mapeamentos para taxonomias
    private function apply_taxonomy_mappings($post_id, $generated_data, $mappings)
    {
        foreach ($mappings as $gen_field => $mapping) {
            if ($mapping['type'] !== 'taxonomy') {
                continue;
            }

            $value = $generated_data[$gen_field] ?? '';
            if (empty($value)) {
                continue;
            }

            $taxonomy = $mapping['field'];
            
            // Converter para array se for string
            if (is_string($value)) {
                $terms = array_map('trim', explode(',', $value));
            } else {
                $terms = (array) $value;
            }

            // Adicionar termos à taxonomia
            wp_set_object_terms($post_id, $terms, $taxonomy, true);
        }
    }

    /**
     * Gera thumbnail usando DALL-E 3 ou Gemini
     */
    public function generate_thumbnail()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? '1792x1024');
        $provider = sanitize_text_field($_POST['provider'] ?? 'dalle');
        $post_id = absint($_POST['post_id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? 'thumbnail-' . time());

        error_log('WPAI Thumbnail Request: provider=' . $provider . ', format=' . $format);
        error_log('WPAI Thumbnail Prompt: ' . substr($prompt, 0, 200));

        if (empty($prompt)) {
            wp_send_json_error(['message' => __('Prompt é obrigatório.', 'wp-ai-post-generator')]);
        }

        // Escolher provider
        if ($provider === 'gemini') {
            error_log('WPAI: Using Gemini for thumbnail generation');
            $result = $this->generate_with_gemini($prompt, $format);
        } elseif ($provider === 'openrouter') {
            error_log('WPAI: Using OpenRouter for thumbnail generation');
            $result = $this->generate_with_openrouter($prompt, $format);
        } else {
            error_log('WPAI: Using DALL-E for thumbnail generation');
            $result = $this->generate_with_dalle($prompt, $format);
        }

        if (is_wp_error($result)) {
            error_log('WPAI Thumbnail Error: ' . $result->get_error_message());
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ]);
        }

        error_log('WPAI Thumbnail: Image generated successfully, saving to media library');

        // Salvar na biblioteca e definir como thumbnail
        $filename = sanitize_title($title);

        if ($provider === 'gemini') {
            $gemini = new WPAI_Gemini_Client();
            $attach_id = $gemini->save_to_media_library($result['data'], $filename, $post_id);
        } elseif ($provider === 'openrouter') {
            $openrouter = new WPAI_OpenRouter_Client();
            $attach_id = $openrouter->save_to_media_library($result['data'], $filename, $post_id);
        } else {
            $openai = new WPAI_OpenAI_Client();
            $attach_id = $openai->save_image_to_media_library($result['data'], $filename, $post_id);
        }

        if (is_wp_error($attach_id)) {
            wp_send_json_error([
                'message' => $attach_id->get_error_message(),
            ]);
        }

        // Definir alt e title da imagem
        $alt_text = !empty($title) ? $title : 'Imagem gerada por IA';
        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));

        // Atualizar título do attachment
        wp_update_post([
            'ID' => $attach_id,
            'post_title' => sanitize_text_field($title),
            'post_excerpt' => '', // Caption
        ]);

        wp_send_json_success([
            'message' => __('Thumbnail gerada com sucesso!', 'wp-ai-post-generator'),
            'attachment_id' => $attach_id,
            'url' => wp_get_attachment_url($attach_id),
            'thumbnail_url' => wp_get_attachment_image_url($attach_id, 'medium'),
            'provider' => $provider,
        ]);
    }

    /**
     * Gera imagem com DALL-E 3
     */
    private function generate_with_dalle($prompt, $format)
    {
        $openai = new WPAI_OpenAI_Client();

        if (!$openai->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key da OpenAI.', 'wp-ai-post-generator'));
        }

        return $openai->generate_image($prompt, $format);
    }

    /**
     * Gera imagem com Gemini
     */
    private function generate_with_gemini($prompt, $format)
    {
        $gemini = new WPAI_Gemini_Client();

        if (!$gemini->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key do Gemini.', 'wp-ai-post-generator'));
        }

        return $gemini->generate_image($prompt, $format);
    }

    // Gera imagem com OpenRouter
    private function generate_with_openrouter($prompt, $format)
    {
        $openrouter = new WPAI_OpenRouter_Client();

        if (!$openrouter->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key do OpenRouter.', 'wp-ai-post-generator'));
        }

        return $openrouter->generate_image($prompt, $format);
    }

    private function sanitize_seo_data($seo)
    {
        $sanitized = [];

        if (!is_array($seo)) {
            $seo = json_decode(stripslashes($seo), true) ?: [];
        }

        $sanitized['meta_title'] = sanitize_text_field($seo['meta_title'] ?? '');
        $sanitized['meta_description'] = sanitize_text_field($seo['meta_description'] ?? '');
        $sanitized['focus_keyword'] = sanitize_text_field($seo['focus_keyword'] ?? '');
        $sanitized['slug'] = sanitize_title($seo['slug'] ?? '');

        $sanitized['secondary_keywords'] = [];
        if (!empty($seo['secondary_keywords']) && is_array($seo['secondary_keywords'])) {
            $sanitized['secondary_keywords'] = array_map('sanitize_text_field', $seo['secondary_keywords']);
        }

        $sanitized['faq'] = [];
        if (!empty($seo['faq']) && is_array($seo['faq'])) {
            foreach ($seo['faq'] as $faq_item) {
                if (!empty($faq_item['question']) && !empty($faq_item['answer'])) {
                    $sanitized['faq'][] = [
                        'question' => sanitize_text_field($faq_item['question']),
                        'answer' => sanitize_text_field($faq_item['answer']),
                    ];
                }
            }
        }

        return $sanitized;
    }

    public function test_connection()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $client = new WPAI_OpenAI_Client();

        if (!$client->has_api_key()) {
            wp_send_json_error(['message' => __('API Key não configurada.', 'wp-ai-post-generator')]);
        }

        $result = $client->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ]);
        }

        wp_send_json_success([
            'message' => __('Conexão OpenAI OK!', 'wp-ai-post-generator'),
            'model' => $client->get_model(),
        ]);
    }

    // Testa conexão com Gemini
    public function test_gemini()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $client = new WPAI_Gemini_Client();

        if (!$client->has_api_key()) {
            wp_send_json_error(['message' => __('API Key do Gemini não configurada.', 'wp-ai-post-generator')]);
        }

        // Testar com uma requisição simples de texto
        $settings = get_option('wpai_post_gen_settings', []);
        $encryption = new WPAI_Encryption();
        $api_key = $encryption->decrypt($settings['gemini_api_key']);

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';
        
        $response = wp_remote_post($api_url . '?key=' . $api_key, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'contents' => [['parts' => [['text' => 'Responda apenas: OK']]]]
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $message = $body['error']['message'] ?? 'Erro na API Gemini (código ' . $code . ')';
            wp_send_json_error(['message' => $message]);
        }

        wp_send_json_success([
            'message' => __('Conexão Gemini OK!', 'wp-ai-post-generator'),
            'model' => 'gemini-2.0-flash-exp',
        ]);
    }

    // Testa conexão com OpenRouter
    public function test_openrouter()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $client = new WPAI_OpenRouter_Client();

        if (!$client->has_api_key()) {
            wp_send_json_error(['message' => __('API Key do OpenRouter não configurada.', 'wp-ai-post-generator')]);
        }

        $result = $client->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Conexão OpenRouter OK!', 'wp-ai-post-generator'),
            'model' => $client->get_model(),
        ]);
    }

    /**
     * Transcreve audio usando OpenAI Whisper ou Gemini
     */
    public function transcribe_audio()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        if (empty($_FILES['audio'])) {
            wp_send_json_error(['message' => __('Nenhum áudio enviado.', 'wp-ai-post-generator')]);
        }

        $settings = get_option('wpai_post_gen_settings', []);
        $file = $_FILES['audio'];
        $file_path = $file['tmp_name'];

        // Tenta usar Gemini primeiro (se disponivel)
        if (!empty($settings['gemini_api_key'])) {
            $gemini = new WPAI_Gemini_Client();
            $result = $gemini->transcribe_audio($file_path);
            
            if (!is_wp_error($result)) {
                wp_send_json_success(['text' => $result]);
                return;
            }
        }

        // Fallback para OpenAI Whisper
        if (empty($settings['openai_api_key'])) {
            wp_send_json_error(['message' => __('Configure a API Key do Gemini ou OpenAI.', 'wp-ai-post-generator')]);
        }

        $encryption = new WPAI_Encryption();
        $api_key = $encryption->decrypt($settings['openai_api_key']);

        $curl_file = new CURLFile($file_path, $file['type'], 'audio.webm');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => $curl_file,
            'model' => 'whisper-1',
            'language' => 'pt',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            $error = json_decode($result, true);
            wp_send_json_error(['message' => $error['error']['message'] ?? 'Erro na transcrição']);
        }

        $data = json_decode($result, true);
        wp_send_json_success(['text' => $data['text'] ?? '']);
    }

    /**
     * Melhora a descrição usando GPT
     */
    public function improve_description()
    {
        check_ajax_referer('wpai_post_gen_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-ai-post-generator')]);
        }

        $text = sanitize_textarea_field($_POST['text'] ?? '');

        if (empty($text) || strlen($text) < 10) {
            wp_send_json_error(['message' => __('Texto muito curto.', 'wp-ai-post-generator')]);
        }

        $client = new WPAI_OpenAI_Client();

        if (!$client->has_api_key()) {
            wp_send_json_error(['message' => __('API Key não configurada.', 'wp-ai-post-generator')]);
        }

        $prompt = "Melhore e expanda o seguinte texto/descrição para ser mais claro e detalhado, mantendo o mesmo significado. O texto deve ter no máximo 500 caracteres. Responda APENAS com o texto melhorado, sem explicações.\n\nTexto original:\n{$text}";

        $result = $client->chat_completion([
            ['role' => 'user', 'content' => $prompt]
        ], [
            'max_tokens' => 300,
            'temperature' => 0.7,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        $improved = trim($result['content']);

        // Limitar a 500 caracteres
        if (strlen($improved) > 500) {
            $improved = substr($improved, 0, 497) . '...';
        }

        wp_send_json_success([
            'improved' => $improved,
            'original_length' => strlen($text),
            'improved_length' => strlen($improved),
        ]);
    }

    // Salva thumbnail de base64 na biblioteca de midia com SEO otimizado
    private function save_thumbnail_from_base64($base64_data, $mime_type, $title, $post_id, $focus_keyword = '')
    {
        $image_data = base64_decode($base64_data);
        if ($image_data === false) {
            return new WP_Error('decode_error', 'Erro ao decodificar imagem');
        }

        $ext = ($mime_type === 'image/png') ? 'png' : 'jpg';
        $upload_dir = wp_upload_dir();
        
        // Nome do arquivo otimizado para SEO
        $seo_filename = !empty($focus_keyword) ? sanitize_title($focus_keyword) : sanitize_title($title);
        $filename = $seo_filename . '-' . time() . '.' . $ext;
        $filepath = $upload_dir['path'] . '/' . $filename;

        if (file_put_contents($filepath, $image_data) === false) {
            return new WP_Error('save_error', 'Erro ao salvar imagem');
        }

        // Gerar alt text e descricao SEO
        $alt_text = $this->generate_image_alt($title, $focus_keyword);
        $description = $this->generate_image_description($title, $focus_keyword);

        $attachment = [
            'post_mime_type' => $mime_type,
            'post_title' => sanitize_text_field($title),
            'post_content' => $description,
            'post_excerpt' => $alt_text,
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        // Salvar alt text como meta
        update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    // Atualiza SEO de imagem existente
    private function update_attachment_seo($attach_id, $title, $focus_keyword)
    {
        $alt_text = $this->generate_image_alt($title, $focus_keyword);
        $description = $this->generate_image_description($title, $focus_keyword);

        wp_update_post([
            'ID' => $attach_id,
            'post_title' => sanitize_text_field($title),
            'post_content' => $description,
            'post_excerpt' => $alt_text
        ]);

        update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
    }

    // Gera alt text otimizado para SEO
    private function generate_image_alt($title, $focus_keyword)
    {
        if (!empty($focus_keyword)) {
            return ucfirst($focus_keyword) . ' - ' . $title;
        }
        return $title;
    }

    // Gera descricao otimizada para SEO
    private function generate_image_description($title, $focus_keyword)
    {
        if (!empty($focus_keyword)) {
            return 'Imagem ilustrativa sobre ' . $focus_keyword . '. ' . $title . '. Saiba mais sobre ' . $focus_keyword . ' neste artigo completo.';
        }
        return 'Imagem ilustrativa: ' . $title;
    }
}
