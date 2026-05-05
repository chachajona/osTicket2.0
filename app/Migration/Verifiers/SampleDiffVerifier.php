<?php

declare(strict_types=1);

namespace App\Migration\Verifiers;

use App\Migration\AbstractMigrator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SampleDiffVerifier
{
    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function verify(AbstractMigrator $migrator, array $definition, int $sample = 100): array
    {
        $sample = max(1, $sample);
        $sourceQuery = DB::connection($migrator->sourceConnectionName())->table($definition['source']);
        $rows = array_map(
            static fn (object $row): array => (array) $row,
            $sourceQuery->inRandomOrder()->limit($sample)->get()->all(),
        );

        $uniqueBy = $this->uniqueBy($definition);

        foreach ($rows as $row) {
            $expected = $migrator->mapRowForVerification($definition, $row);
            $targetQuery = DB::connection($migrator->targetConnectionName())->table($definition['target']);

            foreach ($uniqueBy as $column) {
                $targetQuery->where($column, $expected[$column] ?? $row[$column] ?? null);
            }

            $actual = $targetQuery->first();

            if ($actual === null) {
                return [
                    'table' => $definition['name'],
                    'verifier' => 'sample-diff',
                    'status' => 'missing',
                    'sample_size' => count($rows),
                    'notes' => sprintf('Target row missing for %s.', $definition['name']),
                ];
            }

            $actualRow = (array) $actual;
            $differences = [];

            foreach ($expected as $column => $expectedValue) {
                $actualValue = Arr::get($actualRow, $column);

                if ($this->normalize($expectedValue) !== $this->normalize($actualValue)) {
                    $differences[$column] = [
                        'expected' => $expectedValue,
                        'actual' => $actualValue,
                    ];
                }
            }

            if ($differences !== []) {
                return [
                    'table' => $definition['name'],
                    'verifier' => 'sample-diff',
                    'status' => 'mismatch',
                    'sample_size' => count($rows),
                    'notes' => sprintf('Sample diff mismatch for %s.', $definition['name']),
                    'diff' => $differences,
                ];
            }
        }

        return [
            'table' => $definition['name'],
            'verifier' => 'sample-diff',
            'status' => 'verified',
            'sample_size' => count($rows),
            'notes' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    private function uniqueBy(array $definition): array
    {
        $uniqueBy = $definition['unique_by'] ?? $definition['primary_key'] ?? [];

        if (is_string($uniqueBy) && $uniqueBy !== '') {
            return [$uniqueBy];
        }

        return is_array($uniqueBy) ? array_values($uniqueBy) : [];
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
