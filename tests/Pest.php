<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\SkippedWithMessageException;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Characterization', 'Feature', 'Unit');

function inertiaHeaders(): array
{
    $headers = [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ];

    $version = app(HandleInertiaRequests::class)->version(request());

    if ($version !== null) {
        $headers['X-Inertia-Version'] = $version;
    }

    return $headers;
}

function skipIfLegacyTablesMissing(array $tables): void
{
    $schema = Schema::connection('legacy');
    $missing = array_values(array_filter(
        array_unique($tables),
        fn (string $table): bool => ! $schema->hasTable($table)
    ));

    if ($missing !== []) {
        throw new SkippedWithMessageException(
            'Legacy SQLite fixture is missing required tables: '.implode(', ', $missing)
        );
    }
}

function skipIfLegacyColumnsMissing(string $table, array $columns): void
{
    skipIfLegacyTablesMissing([$table]);

    $schema = Schema::connection('legacy');
    $missing = array_values(array_filter(
        array_unique($columns),
        fn (string $column): bool => ! $schema->hasColumn($table, $column)
    ));

    if ($missing !== []) {
        throw new SkippedWithMessageException(
            "Legacy SQLite fixture is missing required columns on {$table}: ".implode(', ', $missing)
        );
    }
}
