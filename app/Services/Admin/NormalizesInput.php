<?php

declare(strict_types=1);

namespace App\Services\Admin;

trait NormalizesInput
{
    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeString(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    protected function normalizeBool(mixed $value): int
    {
        return (bool) $value ? 1 : 0;
    }
}
