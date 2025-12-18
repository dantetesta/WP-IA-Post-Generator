<?php
/**
 * Cliente Gemini - Texto, Imagens e Transcricao
 * @package WP_AI_Post_Generator
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @updated 2025-12-12 00:00
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

    // Modelos disponiveis
    public static function get_text_models()
    {
        return [
            'gemini-2.5-flash' => 'Gemini 2.5 Flash (Recomendado)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
        ];
    }

    public function get_text_model()
    {
        $settings = get_option('wpai_post_gen_settings', []);
        return $settings['gemini_text_model'] ?? 'gemini-2.5-flash';
    }

    // Gera texto usando Gemini
    public function chat_completion($messages, $options = [])
    {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key do Gemini.', 'wp-ai-post-generator'));
        }

        $model = $this->get_text_model();
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        // Converte formato OpenAI para Gemini
        $contents = [];
        $system_instruction = $options['system'] ?? '';
        
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 4096,
            ]
        ];

        if (!empty($system_instruction)) {
            $body['systemInstruction'] = ['parts' => [['text' => $system_instruction]]];
        }

        $response = wp_remote_post($api_url . '?key=' . $this->api_key, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $message = $body_response['error']['message'] ?? 'Erro Gemini';
            return new WP_Error('gemini_error', $message);
        }

        $content = $body_response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        return [
            'content' => $content,
            'model' => $model,
            'usage' => [
                'total_tokens' => $body_response['usageMetadata']['totalTokenCount'] ?? 0
            ]
        ];
    }

    // Transcreve audio usando Gemini
    public function transcribe_audio($audio_path)
    {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('Configure a API Key do Gemini.', 'wp-ai-post-generator'));
        }

        $model = 'gemini-2.5-flash';
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        // Le o arquivo de audio
        $audio_data = file_get_contents($audio_path);
        $audio_base64 = base64_encode($audio_data);
        $mime_type = mime_content_type($audio_path) ?: 'audio/webm';

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Transcreva este áudio para texto em português. Retorne apenas o texto transcrito, sem explicações.'],
                        [
                            'inlineData' => [
                                'mimeType' => $mime_type,
                                'data' => $audio_base64
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = wp_remote_post($api_url . '?key=' . $this->api_key, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $message = $body_response['error']['message'] ?? 'Erro na transcrição';
            return new WP_Error('transcription_error', $message);
        }

        return $body_response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // Testa conexao com a API
    public function test_connection()
    {
        if (!$this->has_api_key()) {
            return new WP_Error('no_api_key', __('API Key não configurada.', 'wp-ai-post-generator'));
        }

        $model = $this->get_text_model();
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = wp_remote_post($api_url . '?key=' . $this->api_key, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'contents' => [['parts' => [['text' => 'Say OK']]]],
                'generationConfig' => ['maxOutputTokens' => 5]
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return new WP_Error('gemini_error', $body['error']['message'] ?? 'Erro');
        }

        return true;
    }

    /**
     * Gera uma imagem usando Gemini 2.0 Flash (suporta geração de imagens)
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

        // Usar Gemini 2.0 Flash Experimental (suporta geração de imagens)
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp-image-generation:generateContent';

        // Prompt otimizado com aspect ratio
        $full_prompt = $this->build_image_prompt($prompt) . "\n\nGenerate a high-quality image in {$aspect_ratio} aspect ratio.";

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

        error_log('Gemini Request URL: ' . $api_url);
        error_log('Gemini Request Prompt: ' . substr($full_prompt, 0, 200));

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

        if ($code !== 200) {
            $message = $body_response['error']['message'] ?? 'Erro na API Gemini (código ' . $code . ')';
            error_log('Gemini Error: ' . $message);
            return new WP_Error('gemini_error', $message);
        }

        // Extrair imagem da resposta
        return $this->extract_image_from_gemini_response($body_response);
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
