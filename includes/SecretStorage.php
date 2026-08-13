<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class SecretStorage
{
    public const OPTION = 'wpnb_openai_credentials';
    private const VERSION = 1;

    public function __construct(
        private readonly ?string $authSalt = null,
        private readonly ?string $secureSalt = null,
        private readonly ?string $forcedAlgorithm = null,
        private readonly mixed $reader = null,
        private readonly mixed $writer = null,
        private readonly mixed $deleter = null
    ) {}

    public function isSupported(): bool
    {
        return $this->algorithm() !== null;
    }

    public function hasStoredSecret(): bool
    {
        return is_array($this->read());
    }

    public function store(string $secret): void
    {
        $secret = trim($secret);
        if ($secret === '') {
            throw new \RuntimeException(__('API key cannot be empty.', 'wordpress-news-bot'));
        }
        $payload = $this->encrypt($secret);
        $result = $this->writer
            ? ($this->writer)($payload)
            : update_option(self::OPTION, $payload, false);
        if ($result === false && !$this->hasStoredSecret()) {
            throw new \RuntimeException(__('The encrypted API key could not be saved.', 'wordpress-news-bot'));
        }
    }

    public function retrieve(): string
    {
        $payload = $this->read();
        if ($payload === null) {
            return '';
        }
        try {
            return $this->decrypt($payload);
        } catch (\Throwable) {
            throw new \RuntimeException(__('The saved API key could not be decrypted. Please enter the key again.', 'wordpress-news-bot'));
        }
    }

    public function delete(): void
    {
        $this->deleter ? ($this->deleter)() : delete_option(self::OPTION);
    }

    /** @return array{version:int,algorithm:string,nonce?:string,iv?:string,tag?:string,ciphertext:string} */
    public function encrypt(string $secret): array
    {
        $algorithm = $this->algorithm();
        if ($algorithm === null) {
            throw new \RuntimeException(__('Secure encryption is unavailable on this server. The API key was not saved.', 'wordpress-news-bot'));
        }
        $key = $this->key();
        if ($algorithm === 'sodium') {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return ['version' => self::VERSION, 'algorithm' => 'sodium', 'nonce' => base64_encode($nonce), 'ciphertext' => base64_encode(sodium_crypto_secretbox($secret, $nonce, $key))];
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException(__('The API key could not be encrypted.', 'wordpress-news-bot'));
        }
        return ['version' => self::VERSION, 'algorithm' => 'openssl-aes-256-gcm', 'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'ciphertext' => base64_encode($ciphertext)];
    }

    public function decrypt(mixed $payload): string
    {
        if (!is_array($payload) || ($payload['version'] ?? null) !== self::VERSION || !is_string($payload['algorithm'] ?? null) || !is_string($payload['ciphertext'] ?? null)) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }
        $ciphertext = base64_decode($payload['ciphertext'], true);
        if ($ciphertext === false) {
            throw new \RuntimeException('Invalid ciphertext.');
        }
        if ($payload['algorithm'] === 'sodium') {
            if (!function_exists('sodium_crypto_secretbox_open') || !is_string($payload['nonce'] ?? null)) {
                throw new \RuntimeException('Sodium is unavailable.');
            }
            $nonce = base64_decode($payload['nonce'], true);
            $plain = $nonce === false ? false : sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());
        } elseif ($payload['algorithm'] === 'openssl-aes-256-gcm') {
            if (!function_exists('openssl_decrypt') || !is_string($payload['iv'] ?? null) || !is_string($payload['tag'] ?? null)) {
                throw new \RuntimeException('OpenSSL is unavailable.');
            }
            $iv = base64_decode($payload['iv'], true);
            $tag = base64_decode($payload['tag'], true);
            $plain = ($iv === false || $tag === false) ? false : openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        } else {
            throw new \RuntimeException('Unknown encryption algorithm.');
        }
        if (!is_string($plain) || $plain === '') {
            throw new \RuntimeException('Decryption failed.');
        }
        return $plain;
    }

    private function read(): ?array
    {
        $value = $this->reader ? ($this->reader)() : get_option(self::OPTION, null);
        return is_array($value) ? $value : null;
    }

    private function algorithm(): ?string
    {
        if ($this->forcedAlgorithm === 'none') return null;
        if (($this->forcedAlgorithm === null || $this->forcedAlgorithm === 'sodium') && function_exists('sodium_crypto_secretbox')) return 'sodium';
        if (($this->forcedAlgorithm === null || $this->forcedAlgorithm === 'openssl') && function_exists('openssl_encrypt')) return 'openssl-aes-256-gcm';
        return null;
    }

    private function key(): string
    {
        $auth = $this->authSalt ?? wp_salt('auth');
        $secure = $this->secureSalt ?? wp_salt('secure_auth');
        return hash_hkdf('sha256', $auth . "\0" . $secure, 32, 'wordpress-news-bot/openai-credentials');
    }
}
