<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Config;
use App\Models\EmailAccount;

final class LegacyEmailAccountCredentialsResolver
{
    public function __construct(
        private readonly OsTicketCrypto $crypto,
        private readonly OsTicketSecretSaltResolver $secretSaltResolver,
    ) {}

    public function resolve(EmailAccount $account): array
    {
        $namespace = $this->namespaceFor($account);

        $configValues = Config::query()
            ->namespace($namespace)
            ->whereIn('key', ['username', 'passwd'])
            ->pluck('value', 'key');

        if ($configValues->isNotEmpty()) {
            $username = (string) ($configValues['username'] ?? '');
            $storedPassword = (string) ($configValues['passwd'] ?? '');

            if ($username === '' || $storedPassword === '') {
                throw new \RuntimeException("Legacy mailbox credentials are incomplete for namespace {$namespace}.");
            }

            return [
                'username' => $username,
                'password' => $this->resolveStoredPassword($storedPassword, $username, $namespace),
            ];
        }

        return $this->resolveFallbackCredentials($account, $namespace);
    }

    public function namespaceFor(EmailAccount $account): string
    {
        return sprintf('email.%d.account.%d', (int) $account->email_id, (int) $account->id);
    }

    private function resolveStoredPassword(string $storedPassword, string $username, string $namespace): string
    {
        if (! str_starts_with($storedPassword, '$')) {
            return $storedPassword;
        }

        $secretSalt = $this->secretSaltResolver->resolve();

        if (! $secretSalt) {
            throw new \RuntimeException(
                'Legacy osTicket SECRET_SALT is required to decrypt mailbox credentials. '
                .'Set OSTICKET_SECRET_SALT or OSTICKET_CONFIG_PATH.'
            );
        }

        $password = $this->crypto->decrypt($storedPassword, $secretSalt, md5($username.$namespace));

        if ($password === null) {
            throw new \RuntimeException("Unable to decrypt legacy mailbox credentials for namespace {$namespace}.");
        }

        return $password;
    }

    private function resolveFallbackCredentials(EmailAccount $account, string $namespace): array
    {
        $username = trim((string) $account->auth_id);
        $password = (string) $account->auth_bk;

        if ($password === 'none') {
            return [
                'username' => $username !== '' ? $username : (string) ($account->email?->email ?? ''),
                'password' => '',
            ];
        }

        // Compatibility fallback for simplified fixtures or legacy rows where
        // credentials were copied directly into auth_id/auth_bk.
        if ($username !== ''
            && $password !== ''
            && ! in_array($password, ['basic', 'mailbox', 'none'], true)
            && ! str_starts_with($password, 'oauth2')
        ) {
            return [
                'username' => $username,
                'password' => $password,
            ];
        }

        throw new \RuntimeException("Mailbox credentials were not found in legacy config namespace {$namespace}.");
    }
}
