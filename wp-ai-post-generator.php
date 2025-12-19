<?php
/**
 * Plugin Name: WP Multi-Agent AI Post Generator
 * Plugin URI: https://dantetesta.com.br
 * Description: Gerador de artigos profissionais usando sistema multi-agente da OpenAI com interface visual avançada.
 * Version: 3.0.0
 * Author: Dante Testa
 * Author URI: https://dantetesta.com.br
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-ai-post-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
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

// Constantes do plugin
define('WPAI_POST_GEN_VERSION', '3.0.0');
define('WPAI_POST_GEN_PLUGIN_FILE', __FILE__);
define('WPAI_POST_GEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAI_POST_GEN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAI_POST_GEN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoload de classes do plugin
 */
spl_autoload_register(function ($class) {
    $prefix = 'WPAI_Post_Generator\\';
    $base_dir = WPAI_POST_GEN_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Classe principal do plugin
 */
final class WP_AI_Post_Generator
{

    /**
     * Instância única (Singleton)
     *
     * @var WP_AI_Post_Generator
     */
    private static $instance = null;

    /**
     * Array de instâncias de classes
     *
     * @var array
     */
    private $classes = [];

    /**
     * Obtém a instância única do plugin
     *
     * @return WP_AI_Post_Generator
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Carrega dependências
     */
    private function load_dependencies()
    {
        require_once WPAI_POST_GEN_PLUGIN_DIR . 'includes/class-encryption.php';
        require_once WPAI_POST_GEN_PLUGIN_DIR . 'includes/class-openai-client.php';
        require_once WPAI_POST_GEN_PLUGIN_DIR . 'includes/class-gemini-client.php';
        require_once WPAI_POST_GEN_PLUGIN_DIR . 'includes/class-openrouter-client.php';
        require_once WPAI_POST_GEN_PLUGIN_DIR . 'includes/class-multi-agent.php';
        require_once WPAI_POST_GEN_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WPAI_POST_GEN_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    }

    /**
     * Inicializa hooks do WordPress
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init']);
        add_filter('plugin_action_links_' . WPAI_POST_GEN_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }

    /**
     * Ativação do plugin
     */
    public function activate()
    {
        // Criar opções padrão
        if (!get_option('wpai_post_gen_settings')) {
            add_option('wpai_post_gen_settings', [
                'openai_api_key' => '',
                'openai_model' => 'gpt-4.1-mini',
            ]);
        }

        // Gerar chave de criptografia se não existir
        if (!get_option('wpai_post_gen_encryption_key')) {
            $encryption = new WPAI_Encryption();
            add_option('wpai_post_gen_encryption_key', $encryption->generate_key());
        }

        // Limpar cache de rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Desativação do plugin
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Inicialização do plugin
     */
    public function init()
    {
        // Verificar requisitos mínimos
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('WP AI Post Generator requer PHP 7.4 ou superior.', 'wp-ai-post-generator');
                echo '</p></div>';
            });
            return;
        }

        // Inicializar classes
        $this->classes['admin'] = new WPAI_Admin();
        $this->classes['ajax'] = new WPAI_Ajax_Handler();
    }

    /**
     * Links de ação do plugin
     *
     * @param array $links
     * @return array
     */
    public function plugin_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wp-ai-post-generator'),
            esc_html__('Configurações', 'wp-ai-post-generator')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Obtém uma classe específica
     *
     * @param string $key
     * @return object|null
     */
    public function get_class($key)
    {
        return $this->classes[$key] ?? null;
    }
}

/**
 * Função para obter a instância do plugin
 *
 * @return WP_AI_Post_Generator
 */
function wpai_post_generator()
{
    return WP_AI_Post_Generator::get_instance();
}

// Inicializar o plugin
wpai_post_generator();
