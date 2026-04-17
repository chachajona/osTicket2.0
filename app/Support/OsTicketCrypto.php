<?php

declare(strict_types=1);

namespace App\Support;

final class OsTicketCrypto
{
    public function encrypt(string $plaintext, string $masterKey, string $subKey = 'encryption'): ?string
    {
        if ($plaintext === '' || $masterKey === '') {
            return null;
        }

        $method = 'aes-128-cbc';
        $cipherId = '1';
        $ivLength = openssl_cipher_iv_length($method);
        $iv = random_bytes($ivLength);
        $key = $this->deriveKey($masterKey, $subKey, $iv, $ivLength);
        $options = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : 0;

        $ciphertext = openssl_encrypt($plaintext, $method, $key, $options, $iv);
        if ($ciphertext === false) {
            return null;
        }

        return '$2$'.base64_encode('$'.$cipherId.'$'.$iv.$ciphertext);
    }

    public function decrypt(string $ciphertext, string $masterKey, string $subKey = 'encryption'): ?string
    {
        if ($ciphertext === '' || $masterKey === '' || ! str_starts_with($ciphertext, '$')) {
            return null;
        }

        [$outerTag, $payload] = $this->splitCiphertext($ciphertext);

        if ($outerTag === null || $payload === null) {
            return null;
        }

        $decodedPayload = base64_decode($payload, true);
        if ($decodedPayload === false) {
            return null;
        }

        return match ($outerTag) {
            '1' => $this->decryptLegacyMcryptPayload($decodedPayload, $masterKey, $subKey),
            '2' => $this->decryptOpenSslPayload($decodedPayload, $masterKey, $subKey),
            default => null,
        };
    }

    private function decryptOpenSslPayload(string $payload, string $masterKey, string $subKey): ?string
    {
        [$cipherId, $ciphertext] = $this->splitCiphertext($payload);

        if ($cipherId !== '1' || $ciphertext === null) {
            return null;
        }

        $method = 'aes-128-cbc';
        $ivLength = openssl_cipher_iv_length($method);

        if (strlen($ciphertext) <= $ivLength) {
            return null;
        }

        $iv = substr($ciphertext, 0, $ivLength);
        $encrypted = substr($ciphertext, $ivLength);
        $key = $this->deriveKey($masterKey, $subKey, $iv, $ivLength);
        $options = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : 0;
        $plaintext = openssl_decrypt($encrypted, $method, $key, $options, $iv);

        return $plaintext === false ? null : $plaintext;
    }

    private function decryptLegacyMcryptPayload(string $payload, string $masterKey, string $subKey): ?string
    {
        [$cipherId, $ciphertext] = $this->splitCiphertext($payload);

        if ($cipherId !== '1' || $ciphertext === null) {
            return null;
        }

        $method = 'aes-128-cbc';
        $ivLength = openssl_cipher_iv_length($method);

        if (strlen($ciphertext) <= $ivLength) {
            return null;
        }

        $iv = substr($ciphertext, 0, $ivLength);
        $encrypted = substr($ciphertext, $ivLength);
        $key = $this->deriveKey($masterKey, $subKey, $iv, $ivLength);
        $options = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : 0;
        $plaintext = openssl_decrypt($encrypted, $method, $key, $options, $iv);

        return $plaintext === false ? null : $plaintext;
    }

    private function deriveKey(string $masterKey, string $subKey, string $seed, int $length): string
    {
        return substr(hash_hmac('sha512', $masterKey.md5($subKey), $seed, true), 0, $length);
    }

    private function splitCiphertext(string $ciphertext): array
    {
        $parts = explode('$', $ciphertext, 3);

        if (count($parts) !== 3 || $parts[1] === '') {
            return [null, null];
        }

        return [$parts[1], $parts[2]];
    }
}
