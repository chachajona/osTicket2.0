<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class StaffUserProvider extends EloquentUserProvider
{
    public function __construct(
        HasherContract $hasher,
        string $model,
        private readonly CacheRepository $cache,
    ) {
        parent::__construct($hasher, $model);
    }

    public function retrieveById($identifier)
    {
        $user = parent::retrieveById($identifier);

        if (! $user || ! $this->isActiveStaff($user)) {
            return null;
        }

        $this->hydrateRememberToken($user);

        return $user;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        $user = $this->retrieveById($identifier);

        if (! $user) {
            return null;
        }

        $rememberToken = $this->getStoredRememberToken($identifier);

        if (! is_string($rememberToken) || $rememberToken === '' || ! hash_equals($rememberToken, $token)) {
            return null;
        }

        $user->setRememberToken($rememberToken);

        return $user;
    }

    public function updateRememberToken(UserContract $user, #[\SensitiveParameter] $token): void
    {
        $user->setRememberToken($token);
        $this->cache->forever($this->rememberTokenCacheKey($user->getAuthIdentifier()), $token);
    }

    private function hydrateRememberToken(UserContract $user): void
    {
        $rememberToken = $this->getStoredRememberToken($user->getAuthIdentifier());

        if (is_string($rememberToken) && $rememberToken !== '') {
            $user->setRememberToken($rememberToken);
        }
    }

    private function getStoredRememberToken(mixed $identifier): mixed
    {
        return $this->cache->get($this->rememberTokenCacheKey($identifier));
    }

    private function isActiveStaff(UserContract $user): bool
    {
        return (int) $user->getAttribute('isactive') === 1;
    }

    private function rememberTokenCacheKey(mixed $identifier): string
    {
        return sprintf('auth.staff.remember_token.%s', $identifier);
    }
}
