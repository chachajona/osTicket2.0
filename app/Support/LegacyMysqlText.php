<?php

declare(strict_types=1);

namespace App\Support;

final class LegacyMysqlText
{
    public static function stripUnsupportedUtf8mb3Characters(string $value): string
    {
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $value) ?? $value;
    }
}
