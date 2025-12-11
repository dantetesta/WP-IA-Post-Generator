<?php
/**
 * Cliente Gemini/Imagen para Geração de Imagens
 * @package WP_AI_Post_Generator
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @updated 2025-12-11 10:50
 */

if (!defined('ABSPATH'))
    exit;

class WPAI_Gemini_Client
{
    private $api_key = '';

    /**
     * Formatos de imagem disponíveis
     */
    public static function get_image_formats()
    {
        return [
            '1:1' => [
                'label' => '1:1',
                'description' => 'Quadrada'
            ],
            '3:4' => [
                'label' => '3:4',
                'description' => 'Retrato'
            ],
            '4:3' => [
                'label' => '4:3',
                'description' => 'Paisagem'
            ],
            '16:9' => [
                'label' => '16:9',
                'description' => 'Widescreen'
            ],
            '9:16' => [
                'label' => '9:16',
                'description' => 'Stories'
            ]
        ];
    }

    public function __construct()
    {
        $this->load_api_key();
    }

    private function load_api_key()
    {
        $settings = get_option('wpai_post_gen_settings', []);
        if (!empty($settings['gemini_api_key'])) {
            $encryption = new WPAI_Encryption();
            $this->api_key = $encryption->decrypt($settings['gemini_api_key']);
        }
    }

    public function has_api_key()
    {
        return !empty($this->api_key);
    }

    /**
     * Gera uma imagem usando Imagen 3 via Gemini API
     * 
     * @param string $prompt Descrição da imagem
     * @param string $aspect_ratio Aspect ratio (1:1, 3:4, 4:3, 16:9, 9:16)
     * @return array|WP_Error
     */
    public function generate_image($prompt, $aspect_ratio = '4:3')
    {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key do Gemini.', 'wp-ai-post-generator'));
        }

        // Usar Imagen 3 para geração de imagens (mais estável)
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict';

        // Prompt otimizado
        $full_prompt = $this->build_image_prompt($prompt);

        // Estrutura para Imagen 3
        $body = [
            'instances' => [
                ['prompt' => $full_prompt]
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => $aspect_ratio,
                'personGeneration' => 'DONT_ALLOW',
                'safetySetting' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ];

        error_log('Gemini Request URL: ' . $api_url);
        error_log('Gemini Request Body: ' . wp_json_encode($body));

        $response = wp_remote_post($api_url . '?key=' . $this->api_key, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            error_log('Gemini WP Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        error_log('Gemini Response Code: ' . $code);
        error_log('Gemini Response: ' . wp_remote_retrieve_body($response));

        if ($code !== 200) {
            $message = $body_response['error']['message'] ?? 'Erro na API Gemini (código ' . $code . ')';
            return new WP_Error('gemini_error', $message);
        }

        // Extrair imagem da resposta Imagen
        return $this->extract_image_from_imagen_response($body_response);
    }

    /**
     * Alternativa: Usar Gemini 2.0 Flash para imagens
     */
    public function generate_image_gemini($prompt, $aspect_ratio = '4:3')
    {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key do Gemini.', 'wp-ai-post-generator'));
        }

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';

        $full_prompt = $this->build_image_prompt($prompt) . "\n\nGenerate an image in {$aspect_ratio} aspect ratio.";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $full_prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE']
            ]
        ];

        $response = wp_remote_post($api_url . '?key=' . $this->api_key, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $message = $body_response['error']['message'] ?? 'Erro na API Gemini';
            return new WP_Error('gemini_error', $message);
        }

        return $this->extract_image_from_gemini_response($body_response);
    }

    /**
     * Constrói o prompt otimizado para geração de thumbnail
     */
    private function build_image_prompt($prompt)
    {
        return "Professional blog thumbnail image: {$prompt}. 
Style: High-quality, modern, vibrant colors, clean composition, photorealistic, sharp focus, professional lighting, visually striking. 
Restrictions: NO text, NO watermarks, NO logos, NO recognizable faces.";
    }

    /**
     * Extrai a imagem da resposta do Imagen
     */
    private function extract_image_from_imagen_response($response)
    {
        if (!empty($response['predictions'][0]['bytesBase64Encoded'])) {
            return [
                'success' => true,
                'mime_type' => 'image/png',
                'data' => $response['predictions'][0]['bytesBase64Encoded'],
            ];
        }

        error_log('Imagen Response Structure: ' . print_r($response, true));
        return new WP_Error('no_image', __('Nenhuma imagem foi gerada pelo Imagen.', 'wp-ai-post-generator'));
    }

    /**
     * Extrai a imagem da resposta do Gemini
     */
    private function extract_image_from_gemini_response($response)
    {
        if (empty($response['candidates'][0]['content']['parts'])) {
            return new WP_Error('no_image', __('Nenhuma imagem foi gerada.', 'wp-ai-post-generator'));
        }

        foreach ($response['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData'])) {
                return [
                    'success' => true,
                    'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    'data' => $part['inlineData']['data'],
                ];
            }
        }

        return new WP_Error('no_image', __('Nenhuma imagem encontrada na resposta.', 'wp-ai-post-generator'));
    }

    /**
     * Salva a imagem na biblioteca de mídia do WordPress
     */
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
}
