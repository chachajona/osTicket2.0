<?php

namespace App\Services;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Hashing\BcryptHasher;

/**
 * Custom password hasher that supports both bcrypt and MD5 hashes.
 *
 * The legacy osTicket database stores staff passwords as either:
 * - bcrypt ($2a$/$2y$ prefix) — modern osTicket versions
 * - plain MD5 (32-char hex) — older osTicket versions
 *
 * New passwords are always hashed with bcrypt. MD5 hashes are flagged
 * for rehashing via needsRehash() so they get upgraded on next login.
 *
 * @see osticket/include/class.staff.php check_passwd()
 */
class LegacyHasher implements Hasher
{
    /**
     * The underlying bcrypt hasher instance.
     */
    protected BcryptHasher $bcrypt;

    /**
     * Create a new LegacyHasher instance.
     */
    public function __construct()
    {
        $this->bcrypt = new BcryptHasher;
    }

    /**
     * Get information about the given hashed value.
     *
     * @param  string  $hashedValue
     * @return array{algo: string|int, algoName: string, options: array}
     */
    public function info($hashedValue): array
    {
        if ($this->isBcrypt($hashedValue)) {
            return $this->bcrypt->info($hashedValue);
        }

        return ['algo' => 'md5', 'algoName' => 'md5', 'options' => []];
    }

    /**
     * Hash the given value using bcrypt.
     *
     * All new passwords are hashed with bcrypt regardless of the legacy format.
     *
     * @param  string  $value
     */
    public function make($value, array $options = []): string
    {
        return $this->bcrypt->make($value, $options);
    }

    /**
     * Check the given plain value against a hash.
     *
     * Tries bcrypt first (modern osTicket), then falls back to plain MD5
     * comparison (legacy osTicket). This mirrors the logic in
     * osTicket's Staff::check_passwd().
     *
     * @param  string  $value
     * @param  string|null  $hashedValue
     */
    public function check($value, $hashedValue, array $options = []): bool
    {
        if (empty($hashedValue)) {
            return false;
        }

        if ($this->isBcrypt($hashedValue)) {
            return $this->bcrypt->check($value, $hashedValue, $options);
        }

        return md5($value) === $hashedValue;
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * MD5 hashes always need rehashing to bcrypt. Bcrypt hashes are checked
     * against current cost/algorithm settings.
     *
     * @param  string  $hashedValue
     */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        if (! $this->isBcrypt($hashedValue)) {
            return true;
        }

        return $this->bcrypt->needsRehash($hashedValue, $options);
    }

    /**
     * Determine if a hash is a bcrypt hash.
     */
    private function isBcrypt(string $hash): bool
    {
        return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$');
    }
}
