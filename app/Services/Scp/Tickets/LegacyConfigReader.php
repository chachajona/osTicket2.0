<?php

namespace App\Services\Scp\Tickets;

use App\Models\LegacyConfig;

class LegacyConfigReader
{
    private const TICKET_LOCK_MODES = [
        '0' => 'disabled',
        '1' => 'on_view',
        '2' => 'on_activity',
    ];

    private array $cache = [];

    public function ticketLockMode(): string
    {
        $value = $this->get('core', 'ticket_lock', '0');
        return self::TICKET_LOCK_MODES[$value] ?? 'disabled';
    }

    public function lockTime(): int
    {
        $minutes = (int) $this->get('core', 'lock_time', '3');
        return max(60, $minutes * 60);
    }

    private function get(string $namespace, string $key, string $default): string
    {
        $cacheKey = "$namespace:$key";

        if (! array_key_exists($cacheKey, $this->cache)) {
            $row = LegacyConfig::on('legacy')
                ->where('namespace', $namespace)
                ->where('key', $key)
                ->value('value');
            $this->cache[$cacheKey] = $row !== null ? (string) $row : $default;
        }

        return $this->cache[$cacheKey];
    }
}
