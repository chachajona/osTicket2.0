<?php

declare(strict_types=1);

namespace App\Support;

final class OsTicketSecretSaltResolver
{
    public function resolve(): ?string
    {
        $configuredSalt = (string) config('services.osticket.secret_salt', '');

        if ($this->isUsableSalt($configuredSalt)) {
            return $configuredSalt;
        }

        $configPath = (string) config('services.osticket.config_path', '');

        if ($configPath === '' || ! is_readable($configPath)) {
            return null;
        }

        $contents = file_get_contents($configPath);
        if ($contents === false) {
            return null;
        }

        if (! preg_match('/define\(\s*[\'"]SECRET_SALT[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $contents, $matches)) {
            return null;
        }

        $salt = $matches[1];

        return $this->isUsableSalt($salt) ? $salt : null;
    }

    private function isUsableSalt(string $salt): bool
    {
        return $salt !== '' && ! str_starts_with($salt, '%CONFIG-');
    }
}
