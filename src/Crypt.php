<?php

namespace Aurora;

final class Crypt
{
    /**
     * The encryption cipher.
     */
    private static string $cipher;

    /**
     * The encryption key.
     */
    private static string $key;

    /**
     * Verify that the encryption payload is valid.
     *
     * @param array|mixed $data
     */
    public static function invalidPayload(mixed $data): bool
    {
        return !\is_array($data) || !isset($data['iv']) || !isset($data['value']) || !isset($data['mac']);
    }

    /**
     * Encrypt a string without serialization.
     */
    public static function encryptString(string $value): string
    {
        return self::encrypt($value, false);
    }

    /**
     * Encrypt the given value.
     *
     * The string will be encrypted using the AES-128-CBC scheme and will be base64 encoded.
     */
    public static function encrypt(string $value, bool $serialize = true): string
    {
        $iv = self::generateKey();

        // First we will encrypt the value using OpenSSL. After this is encrypted we
        // will proceed to calculating a MAC for the encrypted value so that this
        // value can be verified later as not having been changed by the users.
        $value = openssl_encrypt(
            $serialize ? serialize($value) : $value,
            self::$cipher,
            self::$key,
            0,
            $iv
        );

        if (false === $value) {
            throw new \Exception('Could not encrypt the data.');
        }

        // Once we have the encrypted value we will go ahead base64_encode the input
        // vector and create the MAC for the encrypted value so we can verify its
        // authenticity. Then, we'll JSON encode the data in a "payload" array.
        $mac = self::hash($iv = base64_encode($iv), $value, self::$key);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \Exception('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * Create a new encryption key.
     */
    private static function generateKey(): string
    {
        $random = openssl_random_pseudo_bytes(self::keyLength(), $crypto_strong);
        if (false === $crypto_strong || !$random) {
            throw new \RuntimeException('IV generation failed');
        }

        return $random;
    }

    /**
     * Get the input vector size for the cipher and mode.
     */
    private static function keyLength(): int
    {
        return openssl_cipher_iv_length(self::$cipher);
    }

    /**
     * Create a MAC for the given value.
     */
    public static function hash(string $iv, string $value, $key): string
    {
        return hash_hmac('sha256', $iv . $value, $key);
    }

    /**
     * Decrypt a string without serialization.
     */
    public static function decryptString(string $value): string
    {
        return self::decrypt($value, false);
    }

    /**
     * Decrypt a string.
     */
    public static function decrypt(string $payload, bool $unserialize = true): string
    {
        $payload = self::getJsonPayload($payload);

        $iv = base64_decode($payload['iv'], true);

        $decrypted = openssl_decrypt(
            $payload['value'],
            self::$cipher,
            self::$key,
            0,
            $iv
        );

        if (false === $decrypted) {
            throw new \Exception('Could not decrypt the data.');
        }

        return $unserialize ? unserialize($decrypted) : $decrypted;
    }

    /**
     * Get the JSON array from the given payload.
     */
    public static function getJsonPayload(string $payload): array
    {
        $payload = json_decode(base64_decode($payload, true), true);

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if (!$payload || !self::validPayload($payload)) {
            throw new \Exception('The payload is invalid.');
        }

        if (!self::validMac($payload)) {
            throw new \Exception('The MAC is invalid.');
        }

        return $payload;
    }

    /**
     * Verify that the encryption payload is valid.
     */
    public static function validPayload(mixed $payload): bool
    {
        return \is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac'])
            && mb_strlen(base64_decode($payload['iv'], true)) === self::keyLength();
    }

    /**
     * Determine if the MAC for the given payload is valid.
     */
    public static function validMac(array $payload): bool
    {
        $iv = self::generateKey();
        $calculated = hash_hmac(
            'sha256',
            self::hash($payload['iv'], $payload['value'], self::$key),
            $iv,
            true
        );

        return hash_equals(hash_hmac('sha256', $payload['mac'], $iv, true), $calculated);
    }

    public static function boot(): void
    {
        $key = Config::get('app.key');
        $cipher = Config::get('app.cipher');

        if (self::supported($key, $cipher)) {
            self::$key = $key;
            self::$cipher = $cipher;
        } else {
            throw new \RuntimeException('The only supported ciphers are AES-128-CBC and AES-256-CBC with the correct key lengths.');
        }
    }

    /**
     * Determine if the given key and cipher combination is valid.
     */
    public static function supported(string $key, string $cipher): bool
    {
        $length = mb_strlen($key, '8bit');

        return ('AES-128-CBC' === $cipher && 16 === $length)
            || ('AES-256-CBC' === $cipher && 32 === $length);
    }
}
