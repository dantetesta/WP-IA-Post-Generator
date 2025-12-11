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
        ];

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

        // Retornar dados para o usuário escolher o título
        wp_send_json_success([
            'message' => __('Artigo gerado! Escolha o título.', 'wp-ai-post-generator'),
            'pipeline' => [
                'briefing' => $result['briefing'],
                'article' => $result['article'],
                'reviews' => $result['reviews'],
                'iterations' => $result['iterations'],
                'titles' => $result['titles'],
                'seo' => $result['seo'],
                'thumbnail_prompt' => $result['thumbnail_prompt'] ?? null,
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
        $status = sanitize_text_field($_POST['status'] ?? 'Rascunho');
        $seo_data = isset($_POST['seo']) ? $this->sanitize_seo_data($_POST['seo']) : [];

        if (empty($title) || empty($content)) {
            wp_send_json_error(['message' => __('Título e conteúdo são obrigatórios.', 'wp-ai-post-generator')]);
        }

        $multi_agent = new WPAI_Multi_Agent();
        $post_id = $multi_agent->create_post($title, $content, $status, $seo_data);

        if (is_wp_error($post_id)) {
            wp_send_json_error([
                'message' => $post_id->get_error_message(),
            ]);
        }

        wp_send_json_success([
            'message' => __('Post salvo com sucesso!', 'wp-ai-post-generator'),
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'view_url' => get_permalink($post_id),
        ]);
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

        if (empty($prompt)) {
            wp_send_json_error(['message' => __('Prompt é obrigatório.', 'wp-ai-post-generator')]);
        }

        // Escolher provider
        if ($provider === 'gemini') {
            $result = $this->generate_with_gemini($prompt, $format);
        } else {
            $result = $this->generate_with_dalle($prompt, $format);
        }

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ]);
        }

        // Salvar na biblioteca e definir como thumbnail
        $filename = sanitize_title($title);

        if ($provider === 'gemini') {
            $gemini = new WPAI_Gemini_Client();
            $attach_id = $gemini->save_to_media_library($result['data'], $filename, $post_id);
        } else {
            $openai = new WPAI_OpenAI_Client();
            $attach_id = $openai->save_image_to_media_library($result['data'], $filename, $post_id);
        }

        if (is_wp_error($attach_id)) {
            wp_send_json_error([
                'message' => $attach_id->get_error_message(),
            ]);
        }

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
            'message' => __('Conexão bem-sucedida!', 'wp-ai-post-generator'),
            'model' => $client->get_model(),
        ]);
    }

    /**
     * Transcreve áudio usando OpenAI Whisper
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
        if (empty($settings['openai_api_key'])) {
            wp_send_json_error(['message' => __('API Key não configurada.', 'wp-ai-post-generator')]);
        }

        $encryption = new WPAI_Encryption();
        $api_key = $encryption->decrypt($settings['openai_api_key']);

        $file = $_FILES['audio'];
        $file_path = $file['tmp_name'];

        // Converter webm para mp3 se necessário (Whisper aceita webm)
        $curl_file = new CURLFile($file_path, $file['type'], 'audio.webm');

        $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => [
                'file' => $curl_file,
                'model' => 'whisper-1',
                'language' => 'pt',
            ],
            'timeout' => 60,
        ]);

        // Fallback: usar curl diretamente
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
        ]);
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
            wp_send_json_error([
                'message' => $error['error']['message'] ?? 'Erro na transcrição',
            ]);
        }

        $data = json_decode($result, true);

        wp_send_json_success([
            'text' => $data['text'] ?? '',
        ]);
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
}
