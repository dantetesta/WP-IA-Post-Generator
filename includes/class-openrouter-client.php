<?php
/**
 * Cliente OpenRouter para Geração de Imagens
 * @package WP_AI_Post_Generator
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 2.3.0
 * @created 2025-12-11 22:59
 */

if (!defined('ABSPATH'))
    exit;

class WPAI_OpenRouter_Client
{
    private $api_key = '';
    private $api_url = 'https://openrouter.ai/api/v1/chat/completions';

    // Modelos gratuitos que GERAM imagens
    public static function get_available_models()
    {
        return [
            'google/gemini-2.0-flash-exp:free' => 'Gemini 2.0 Flash (Free)',
        ];
    }

    public function __construct()
    {
        $this->load_api_key();
    }

    private function load_api_key()
    {
        $settings = get_option('wpai_post_gen_settings', []);
        if (!empty($settings['openrouter_api_key'])) {
            $encryption = new WPAI_Encryption();
            $this->api_key = $encryption->decrypt($settings['openrouter_api_key']);
        }
    }

    public function has_api_key()
    {
        return !empty($this->api_key);
    }

    public function get_model()
    {
        $settings = get_option('wpai_post_gen_settings', []);
        $saved_model = $settings['openrouter_model'] ?? '';
        $valid_models = array_keys(self::get_available_models());
        
        // Se o modelo salvo nao existe mais na lista, usa o padrao
        if (empty($saved_model) || !in_array($saved_model, $valid_models)) {
            return 'google/gemini-2.0-flash-exp:free';
        }
        
        return $saved_model;
    }

    // Gera imagem via OpenRouter
    public function generate_image($prompt, $aspect_ratio = '4:3')
    {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key do OpenRouter.', 'wp-ai-post-generator'));
        }

        $full_prompt = $this->build_image_prompt($prompt, $aspect_ratio);
        $model = $this->get_model();

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $full_prompt
                ]
            ],
            'modalities' => ['image', 'text'],
        ];

        // Adicionar aspect ratio se suportado
        if (strpos($model, 'gemini') !== false) {
            $body['image_config'] = ['aspect_ratio' => $aspect_ratio];
        }

        error_log('OpenRouter Request Model: ' . $model);
        error_log('OpenRouter Request Prompt: ' . substr($full_prompt, 0, 200));

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name'),
            ],
            'body' => wp_json_encode($body),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            error_log('OpenRouter WP Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        error_log('OpenRouter Response Code: ' . $code);

        if ($code !== 200) {
            $message = $body_response['error']['message'] ?? 'Erro na API OpenRouter (código ' . $code . ')';
            error_log('OpenRouter Error: ' . $message);
            return new WP_Error('openrouter_error', $message);
        }

        return $this->extract_image_from_response($body_response);
    }

    // Constrói o prompt otimizado para thumbnail
    private function build_image_prompt($prompt, $aspect_ratio)
    {
        return "Generate a professional blog thumbnail image: {$prompt}. 
Style: High-quality, modern, vibrant colors, clean composition, photorealistic, sharp focus, professional lighting, visually striking.
Aspect ratio: {$aspect_ratio}
Restrictions: NO text, NO watermarks, NO logos, NO recognizable faces.";
    }

    // Extrai a imagem da resposta do OpenRouter
    private function extract_image_from_response($response)
    {
        if (empty($response['choices'][0]['message'])) {
            error_log('OpenRouter: No message in response');
            return new WP_Error('no_image', __('Nenhuma resposta recebida.', 'wp-ai-post-generator'));
        }

        $message = $response['choices'][0]['message'];

        // Verificar campo images
        if (!empty($message['images'])) {
            foreach ($message['images'] as $image) {
                if (!empty($image['image_url']['url'])) {
                    $url = $image['image_url']['url'];
                    
                    // Se for base64
                    if (strpos($url, 'data:image') === 0) {
                        $parts = explode(',', $url);
                        if (count($parts) === 2) {
                            preg_match('/data:image\/(\w+);base64/', $parts[0], $matches);
                            $mime_type = isset($matches[1]) ? 'image/' . $matches[1] : 'image/png';
                            
                            return [
                                'success' => true,
                                'mime_type' => $mime_type,
                                'data' => $parts[1],
                            ];
                        }
                    }
                }
            }
        }

        error_log('OpenRouter Response Structure: ' . print_r($response, true));
        return new WP_Error('no_image', __('Nenhuma imagem encontrada na resposta.', 'wp-ai-post-generator'));
    }

    // Salva a imagem na biblioteca de mídia do WordPress
    public function save_to_media_library($base64_data, $filename, $post_id = 0)
    {
        $image_data = base64_decode($base64_data);

        if ($image_data === false) {
            return new WP_Error('decode_error', __('Erro ao decodificar imagem.', 'wp-ai-post-generator'));
        }

        $upload_dir = wp_upload_dir();
        $filename = sanitize_file_name($filename . '-' . time() . '.png');
        $filepath = $upload_dir['path'] . '/' . $filename;

        $bytes_written = file_put_contents($filepath, $image_data);

        if ($bytes_written === false) {
            return new WP_Error('save_error', __('Erro ao salvar imagem.', 'wp-ai-post-generator'));
        }

        $attachment = [
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);

        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);

        if ($post_id > 0) {
            set_post_thumbnail($post_id, $attach_id);
        }

        return $attach_id;
    }

    // Testa conexão com a API
    public function test_connection()
    {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('API Key não configurada.', 'wp-ai-post-generator'));
        }

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
            ],
            'body' => wp_json_encode([
                'model' => $this->get_model(),
                'messages' => [['role' => 'user', 'content' => 'Say OK']],
                'max_tokens' => 5,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $message = $body['error']['message'] ?? 'Erro na API OpenRouter';
            return new WP_Error('openrouter_error', $message);
        }

        return true;
    }
}
