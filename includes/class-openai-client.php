<?php
/**
 * Cliente da API OpenAI
 *
 * Gerencia as requisições para a API da OpenAI
 *
 * @package WP_AI_Post_Generator
 * @author Dante Testa <contato@dantetesta.com.br>
 * @since 1.0.0
 * @created 2025-12-11 09:19
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe WPAI_OpenAI_Client
 */
class WPAI_OpenAI_Client
{

    /**
     * URL base da API
     */
    private const API_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Timeout padrão em segundos
     */
    private const DEFAULT_TIMEOUT = 120;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * Modelo da OpenAI
     *
     * @var string
     */
    private $model;

    /**
     * Instância de criptografia
     *
     * @var WPAI_Encryption
     */
    private $encryption;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->encryption = new WPAI_Encryption();
        $this->load_settings();
    }

    /**
     * Carrega as configurações do banco
     */
    private function load_settings()
    {
        $settings = get_option('wpai_post_gen_settings', []);

        $encrypted_key = $settings['openai_api_key'] ?? '';
        $this->api_key = $this->encryption->decrypt($encrypted_key);

        $this->model = $settings['openai_model'] ?? 'gpt-4o-mini';
    }

    /**
     * Obtém o modelo atual
     *
     * @return string
     */
    public function get_model()
    {
        return $this->model;
    }

    /**
     * Verifica se a API Key está configurada
     *
     * @return bool
     */
    public function has_api_key()
    {
        return !empty($this->api_key);
    }

    /**
     * Faz uma requisição de chat completion
     *
     * @param array $messages Array de mensagens
     * @param array $options Opções adicionais
     * @return array|WP_Error
     */
    public function chat_completion($messages, $options = [])
    {
        if (!$this->has_api_key()) {
            return new WP_Error(
                'no_api_key',
                __('API Key da OpenAI não configurada.', 'wp-ai-post-generator')
            );
        }

        $body = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        // Adicionar system message se fornecida
        if (!empty($options['system'])) {
            array_unshift($body['messages'], [
                'role' => 'system',
                'content' => $options['system']
            ]);
        }

        $response = $this->make_request('/chat/completions', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Extrair o conteúdo da resposta
        if (isset($response['choices'][0]['message']['content'])) {
            return [
                'content' => $response['choices'][0]['message']['content'],
                'usage' => $response['usage'] ?? [],
                'model' => $response['model'] ?? $this->model,
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? 'unknown',
            ];
        }

        return new WP_Error(
            'invalid_response',
            __('Resposta inválida da API OpenAI.', 'wp-ai-post-generator')
        );
    }

    /**
     * Faz uma requisição HTTP para a API
     *
     * @param string $endpoint Endpoint da API
     * @param array $body Corpo da requisição
     * @param string $method Método HTTP
     * @return array|WP_Error
     */
    private function make_request($endpoint, $body = [], $method = 'POST')
    {
        $url = self::API_BASE_URL . $endpoint;

        $args = [
            'method' => $method,
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Verificar erros da API
        if ($status_code >= 400) {
            $error_message = $data['error']['message'] ?? __('Erro desconhecido da API.', 'wp-ai-post-generator');
            $error_code = $data['error']['code'] ?? 'api_error';

            return new WP_Error($error_code, $error_message, [
                'status_code' => $status_code,
                'response' => $data,
            ]);
        }

        return $data;
    }

    /**
     * Testa a conexão com a API
     *
     * @return bool|WP_Error
     */
    public function test_connection()
    {
        if (!$this->has_api_key()) {
            return new WP_Error(
                'no_api_key',
                __('API Key não configurada.', 'wp-ai-post-generator')
            );
        }

        $response = $this->chat_completion([
            ['role' => 'user', 'content' => 'Responda apenas "ok"']
        ], [
            'max_tokens' => 10,
            'temperature' => 0,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Lista os modelos disponíveis
     *
     * @return array
     */
    public static function get_available_models()
    {
        return [
            'gpt-4o' => 'GPT-4o (Mais recente e multimodal)',
            'gpt-4o-mini' => 'GPT-4o Mini (Rápido e econômico)',
            'gpt-4-turbo' => 'GPT-4 Turbo (Poderoso)',
            'o1' => 'o1 (Raciocínio avançado)',
            'o1-mini' => 'o1 Mini (Raciocínio econômico)',
        ];
    }

    // Modelos de imagem disponiveis
    public static function get_image_models()
    {
        return [
            'gpt-image-1' => 'GPT Image 1 (Mais recente - Nativo GPT-4o)',
            'dall-e-3' => 'DALL-E 3 (Alta qualidade)',
        ];
    }

    /**
     * Gera uma imagem usando DALL-E 3
     *
     * @param string $prompt Descrição da imagem
     * @param string $size Tamanho (1024x1024, 1792x1024, 1024x1792)
     * @return array|WP_Error
     */
    public function generate_image($prompt, $size = '1792x1024')
    {
        if (!$this->has_api_key()) {
            return new WP_Error(
                'no_api_key',
                __('API Key da OpenAI não configurada.', 'wp-ai-post-generator')
            );
        }

        // Otimizar prompt para DALL-E 3
        $full_prompt = $this->build_image_prompt($prompt);

        $body = [
            'model' => 'dall-e-3',
            'prompt' => $full_prompt,
            'n' => 1,
            'size' => $size,
            'quality' => 'standard',
            'response_format' => 'b64_json'
        ];

        $response = $this->make_request('/images/generations', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        if (!empty($response['data'][0]['b64_json'])) {
            return [
                'success' => true,
                'data' => $response['data'][0]['b64_json'],
                'revised_prompt' => $response['data'][0]['revised_prompt'] ?? ''
            ];
        }

        return new WP_Error(
            'image_generation_failed',
            __('Falha ao gerar imagem com DALL-E.', 'wp-ai-post-generator')
        );
    }

    /**
     * Constrói prompt otimizado para DALL-E 3
     */
    private function build_image_prompt($prompt)
    {
        return "Professional blog thumbnail image: {$prompt}. 
Style: High-quality, modern digital art, vibrant colors, clean composition, professional, visually striking. 
Important: NO text, NO watermarks, NO logos, NO words in the image.";
    }

    /**
     * Formatos de imagem disponíveis para DALL-E 3
     */
    public static function get_image_sizes()
    {
        return [
            '1024x1024' => [
                'label' => '1:1',
                'description' => 'Quadrada'
            ],
            '1792x1024' => [
                'label' => '16:9',
                'description' => 'Paisagem (Blog)'
            ],
            '1024x1792' => [
                'label' => '9:16',
                'description' => 'Retrato (Stories)'
            ]
        ];
    }

    /**
     * Salva a imagem na biblioteca de mídia do WordPress
     * Com otimização: max 1000px largura, WebP 80% qualidade
     */
    public function save_image_to_media_library($base64_data, $filename, $post_id = 0)
    {
        $image_data = base64_decode($base64_data);

        if ($image_data === false) {
            return new WP_Error('decode_error', __('Erro ao decodificar imagem.', 'wp-ai-post-generator'));
        }

        // Criar imagem temporária para otimização
        $upload_dir = wp_upload_dir();
        $temp_filename = 'temp-' . time() . '.png';
        $temp_filepath = $upload_dir['path'] . '/' . $temp_filename;

        // Salvar PNG temporário
        $bytes_written = file_put_contents($temp_filepath, $image_data);

        if ($bytes_written === false) {
            return new WP_Error('save_error', __('Erro ao salvar imagem temporária.', 'wp-ai-post-generator'));
        }

        // Otimizar imagem
        $optimized_result = $this->optimize_image($temp_filepath, $filename, $upload_dir);

        // Deletar arquivo temporário
        @unlink($temp_filepath);

        if (is_wp_error($optimized_result)) {
            return $optimized_result;
        }

        // Criar attachment WordPress
        $attachment = [
            'post_mime_type' => $optimized_result['mime_type'],
            'post_title' => sanitize_file_name(pathinfo($optimized_result['filename'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $optimized_result['filepath'], $post_id);

        if (is_wp_error($attach_id)) {
            @unlink($optimized_result['filepath']);
            return $attach_id;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $optimized_result['filepath']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        if ($post_id > 0) {
            set_post_thumbnail($post_id, $attach_id);
        }

        return $attach_id;
    }

    /**
     * Otimiza a imagem: redimensiona e converte para WebP
     * 
     * @param string $source_path Caminho da imagem original
     * @param string $filename Nome base do arquivo final
     * @param array $upload_dir Diretório de upload do WordPress
     * @return array|WP_Error
     */
    private function optimize_image($source_path, $filename, $upload_dir)
    {
        // Configurações de otimização
        $max_width = 1000;
        $quality = 80;
        $format = 'webp'; // Pode ser 'webp' ou 'jpeg'

        // Verificar se GD está disponível
        if (!function_exists('imagecreatefromstring')) {
            return new WP_Error('gd_missing', __('Biblioteca GD não disponível.', 'wp-ai-post-generator'));
        }

        // Carregar imagem original
        $image_data = file_get_contents($source_path);
        $source_image = @imagecreatefromstring($image_data);

        if (!$source_image) {
            return new WP_Error('image_load_error', __('Erro ao carregar imagem para otimização.', 'wp-ai-post-generator'));
        }

        // Obter dimensões originais
        $orig_width = imagesx($source_image);
        $orig_height = imagesy($source_image);

        // Calcular novas dimensões (max width = 1000px)
        if ($orig_width > $max_width) {
            $ratio = $max_width / $orig_width;
            $new_width = $max_width;
            $new_height = (int) round($orig_height * $ratio);
        } else {
            // Manter tamanho original se já for menor
            $new_width = $orig_width;
            $new_height = $orig_height;
        }

        // Criar nova imagem redimensionada
        $new_image = imagecreatetruecolor($new_width, $new_height);

        // Preservar transparência para PNG/WebP
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);

        // Redimensionar com alta qualidade
        imagecopyresampled(
            $new_image,
            $source_image,
            0,
            0,
            0,
            0,
            $new_width,
            $new_height,
            $orig_width,
            $orig_height
        );

        // Definir nome e caminho do arquivo final
        $final_filename = sanitize_file_name($filename . '-' . time());

        // Salvar no formato escolhido
        if ($format === 'webp' && function_exists('imagewebp')) {
            $final_filename .= '.webp';
            $final_filepath = $upload_dir['path'] . '/' . $final_filename;
            $success = imagewebp($new_image, $final_filepath, $quality);
            $mime_type = 'image/webp';
        } else {
            // Fallback para JPEG
            $final_filename .= '.jpg';
            $final_filepath = $upload_dir['path'] . '/' . $final_filename;

            // Converter para fundo branco (JPEG não suporta transparência)
            $jpeg_image = imagecreatetruecolor($new_width, $new_height);
            $white = imagecolorallocate($jpeg_image, 255, 255, 255);
            imagefilledrectangle($jpeg_image, 0, 0, $new_width, $new_height, $white);
            imagecopy($jpeg_image, $new_image, 0, 0, 0, 0, $new_width, $new_height);

            $success = imagejpeg($jpeg_image, $final_filepath, $quality);
            imagedestroy($jpeg_image);
            $mime_type = 'image/jpeg';
        }

        // Liberar memória
        imagedestroy($source_image);
        imagedestroy($new_image);

        if (!$success) {
            return new WP_Error('save_optimized_error', __('Erro ao salvar imagem otimizada.', 'wp-ai-post-generator'));
        }

        // Calcular economia de tamanho
        $original_size = filesize($source_path);
        $optimized_size = filesize($final_filepath);
        $savings = round((1 - ($optimized_size / $original_size)) * 100);

        error_log(sprintf(
            'WPAI Image Optimization: %dx%d -> %dx%d, %s -> %s (%d%% reduction)',
            $orig_width,
            $orig_height,
            $new_width,
            $new_height,
            size_format($original_size),
            size_format($optimized_size),
            $savings
        ));

        return [
            'filepath' => $final_filepath,
            'filename' => $final_filename,
            'mime_type' => $mime_type,
            'width' => $new_width,
            'height' => $new_height,
            'original_size' => $original_size,
            'optimized_size' => $optimized_size,
            'savings_percent' => $savings
        ];
    }
}
