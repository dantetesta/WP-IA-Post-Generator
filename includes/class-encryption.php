<?php
/**
 * Classe de Criptografia
 *
 * Gerencia a criptografia segura da API Key da OpenAI
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
 * Classe WPAI_Encryption
 */
class WPAI_Encryption
{

    /**
     * Método de criptografia
     */
    private const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Tamanho do IV
     */
    private const IV_LENGTH = 16;

    /**
     * Chave de criptografia
     *
     * @var string
     */
    private $encryption_key;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->encryption_key = $this->get_encryption_key();
    }

    /**
     * Gera uma nova chave de criptografia
     *
     * @return string
     */
    public function generate_key()
    {
        return base64_encode(openssl_random_pseudo_bytes(32));
    }

    /**
     * Obtém a chave de criptografia do banco
     *
     * @return string
     */
    private function get_encryption_key()
    {
        $key = get_option('wpai_post_gen_encryption_key');

        if (!$key) {
            $key = $this->generate_key();
            update_option('wpai_post_gen_encryption_key', $key);
        }

        return base64_decode($key);
    }

    /**
     * Criptografa uma string
     *
     * @param string $plaintext Texto a ser criptografado
     * @return string Texto criptografado em base64
     */
    public function encrypt($plaintext)
    {
        if (empty($plaintext)) {
            return '';
        }

        // Gerar IV aleatório
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);

        // Criptografar
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_METHOD,
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            return '';
        }

        // Combinar IV + ciphertext e codificar em base64
        return base64_encode($iv . $ciphertext);
    }

    /**
     * Descriptografa uma string
     *
     * @param string $ciphertext Texto criptografado em base64
     * @return string Texto descriptografado
     */
    public function decrypt($ciphertext)
    {
        if (empty($ciphertext)) {
            return '';
        }

        // Decodificar base64
        $data = base64_decode($ciphertext);

        if ($data === false || strlen($data) < self::IV_LENGTH) {
            return '';
        }

        // Extrair IV e ciphertext
        $iv = substr($data, 0, self::IV_LENGTH);
        $encrypted = substr($data, self::IV_LENGTH);

        // Descriptografar
        $plaintext = openssl_decrypt(
            $encrypted,
            self::CIPHER_METHOD,
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plaintext !== false ? $plaintext : '';
    }

    /**
     * Verifica se uma string está criptografada
     *
     * @param string $string String a verificar
     * @return bool
     */
    public function is_encrypted($string)
    {
        if (empty($string)) {
            return false;
        }

        // Tentar decodificar base64
        $decoded = base64_decode($string, true);

        if ($decoded === false) {
            return false;
        }

        // Verificar se tem tamanho mínimo (IV + algum conteúdo)
        return strlen($decoded) > self::IV_LENGTH;
    }
}
