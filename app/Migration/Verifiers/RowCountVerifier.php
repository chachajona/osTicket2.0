<?php

declare(strict_types=1);

namespace App\Migration\Verifiers;

use App\Migration\AbstractMigrator;
use Illuminate\Support\Facades\DB;

class RowCountVerifier
{
    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function verify(AbstractMigrator $migrator, array $definition): array
    {
        $sourceCount = DB::connection($migrator->sourceConnectionName())
            ->table($definition['source'])
            ->count();

        $targetCount = DB::connection($migrator->targetConnectionName())
            ->table($definition['target'])
            ->count();

        return [
            'table' => $definition['name'],
            'verifier' => 'row-count',
            'status' => $sourceCount === $targetCount ? 'verified' : 'mismatch',
            'source_count' => $sourceCount,
            'target_count' => $targetCount,
            'notes' => $sourceCount === $targetCount
                ? null
                : sprintf('Row count mismatch for %s: source=%d target=%d', $definition['name'], $sourceCount, $targetCount),
        ];
    }
}
