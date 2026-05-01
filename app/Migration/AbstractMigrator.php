<?php

declare(strict_types=1);

namespace App\Migration;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

abstract class AbstractMigrator
{
    protected const int BATCH_SIZE = 1000;

    /**
     * @var array<string, list<string>>
     */
    private array $columnCache = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $activeDefinition = null;

    abstract protected function definitions(): array;

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return array_values(array_map(
            static fn (array $definition): string => (string) $definition['name'],
            $this->definitions(),
        ));
    }

    public function handlesTable(string $tableName): bool
    {
        return in_array($tableName, $this->tableNames(), true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dryRun(?string $fromTable = null): array
    {
        $results = [];

        foreach ($this->selectedDefinitions($fromTable) as $definition) {
            $results[] = [
                'table' => $definition['name'],
                'status' => 'dry-run',
                'source_count' => $this->sourceConnection()->table($definition['source'])->count(),
                'target_count' => $this->targetConnection()->table($definition['target'])->count(),
                'estimate_seconds' => $this->estimateSeconds(
                    $this->sourceConnection()->table($definition['source'])->count(),
                ),
                'notes' => null,
            ];
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function migrate(?string $fromTable = null): array
    {
        $results = [];

        foreach ($this->selectedDefinitions($fromTable) as $definition) {
            $this->activeDefinition = $definition;

            try {
                $results[] = $this->copyTable(
                    (string) $definition['source'],
                    (string) $definition['target'],
                    $definition['mapper'] ?? null,
                );
            } finally {
                $this->activeDefinition = null;
            }
        }

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function verificationDefinitions(?string $fromTable = null): array
    {
        return $this->selectedDefinitions($fromTable);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapRowForVerification(array $definition, array $row): array
    {
        $this->activeDefinition = $definition;

        try {
            return $this->prepareRow(
                $row,
                $this->targetColumns((string) $definition['target']),
                $definition['mapper'] ?? null,
            );
        } finally {
            $this->activeDefinition = null;
        }
    }

    public function sourceConnectionName(): string
    {
        return 'legacy';
    }

    public function targetConnectionName(): string
    {
        return 'osticket2';
    }

    public function progressTable(): string
    {
        return '_migration_progress';
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $rowMapper
     * @return array<string, mixed>
     */
    public function copyTable(string $sourceTable, string $targetTable, ?callable $rowMapper = null): array
    {
        $definition = $this->currentDefinition();
        $tableName = (string) $definition['name'];
        $startedAt = microtime(true);
        $sourceCount = $this->sourceConnection()->table($sourceTable)->count();

        $progress = $this->readWatermark($tableName);

        if (($progress['status'] ?? null) === 'completed') {
            return [
                'table' => $tableName,
                'status' => 'skipped',
                'source_count' => $sourceCount,
                'target_count' => $this->targetConnection()->table($targetTable)->count(),
                'duration_seconds' => round(microtime(true) - $startedAt, 3),
                'notes' => 'Already completed; skipping due to watermark.',
            ];
        }

        $targetColumns = $this->targetColumns($targetTable);
        $primaryKey = $definition['primary_key'] ?? null;
        $uniqueBy = $this->uniqueBy($definition);
        $processed = 0;
        $lastId = Arr::get($progress, 'last_id');

        $this->markRunning($tableName, $this->normalizeWatermarkValue($lastId));

        try {
            if (is_string($primaryKey) && $primaryKey !== '') {
                $processed = $this->copyIncrementingTable(
                    $sourceTable,
                    $targetTable,
                    $targetColumns,
                    $primaryKey,
                    $uniqueBy,
                    $rowMapper,
                    is_numeric($lastId) ? (int) $lastId : null,
                    $tableName,
                );
            } else {
                $processed = $this->copyNonIncrementingTable(
                    $sourceTable,
                    $targetTable,
                    $targetColumns,
                    $uniqueBy,
                    $rowMapper,
                );
            }
        } catch (\Throwable $throwable) {
            $this->markFailed($tableName, $this->normalizeWatermarkValue($lastId));

            throw $throwable;
        }

        $this->markCompleted($tableName, $this->latestCompletedWatermark($sourceTable, $primaryKey));

        return [
            'table' => $tableName,
            'status' => 'migrated',
            'source_count' => $sourceCount,
            'target_count' => $this->targetConnection()->table($targetTable)->count(),
            'processed' => $processed,
            'duration_seconds' => round(microtime(true) - $startedAt, 3),
            'notes' => null,
        ];
    }

    protected function sourceConnection(): ConnectionInterface
    {
        return DB::connection($this->sourceConnectionName());
    }

    protected function targetConnection(): ConnectionInterface
    {
        return DB::connection($this->targetConnectionName());
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    protected function uniqueBy(array $definition): array
    {
        $uniqueBy = $definition['unique_by'] ?? $definition['primary_key'] ?? [];

        if (is_string($uniqueBy) && $uniqueBy !== '') {
            return [$uniqueBy];
        }

        if (is_array($uniqueBy)) {
            return array_values(array_filter(array_map(
                static fn (mixed $value): string => (string) $value,
                $uniqueBy,
            )));
        }

        return [];
    }

    protected function markRunning(string $tableName, string|int|null $lastId): void
    {
        $this->writeProgress($tableName, $lastId, 'running');
    }

    protected function updateWatermark(string $tableName, string|int|null $lastId): void
    {
        $this->writeProgress($tableName, $lastId, 'running');
    }

    protected function markCompleted(string $tableName, string|int|null $lastId): void
    {
        $this->writeProgress($tableName, $lastId, 'completed', now()->toDateTimeString());
    }

    protected function markFailed(string $tableName, string|int|null $lastId): void
    {
        $this->writeProgress($tableName, $lastId, 'failed');
    }

    protected function writeProgress(string $tableName, string|int|null $lastId, string $status, ?string $completedAt = null): void
    {
        $this->targetConnection()->table($this->progressTable())->updateOrInsert(
            ['table_name' => $tableName],
            [
                'last_id' => $lastId,
                'status' => $status,
                'completed_at' => $completedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function readWatermark(string $tableName): array
    {
        $progress = $this->targetConnection()->table($this->progressTable())
            ->where('table_name', $tableName)
            ->first();

        return $progress !== null ? (array) $progress : [];
    }

    /**
     * @param  list<string>  $targetColumns
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $rowMapper
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function prepareRow(array $row, array $targetColumns, ?callable $rowMapper = null): array
    {
        $mapped = $rowMapper instanceof \Closure || is_callable($rowMapper)
            ? $rowMapper($row)
            : $row;

        if (! is_array($mapped)) {
            throw new LogicException('Row mapper must return an array.');
        }

        if (in_array('created_at', $targetColumns, true) && ! array_key_exists('created_at', $mapped)) {
            $mapped['created_at'] = $this->timestampFromRow($row, 'created');
        }

        if (in_array('updated_at', $targetColumns, true) && ! array_key_exists('updated_at', $mapped)) {
            $mapped['updated_at'] = $this->timestampFromRow($row, 'updated');
        }

        $filtered = [];

        foreach ($targetColumns as $column) {
            if (! array_key_exists($column, $mapped)) {
                continue;
            }

            $filtered[$column] = $this->normalizeDatabaseValue($mapped[$column]);
        }

        return $filtered;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function selectedDefinitions(?string $fromTable = null): array
    {
        $definitions = array_values($this->definitions());

        if ($fromTable === null) {
            return $definitions;
        }

        foreach ($definitions as $index => $definition) {
            if (($definition['name'] ?? null) === $fromTable) {
                return array_slice($definitions, $index);
            }
        }

        throw new LogicException(sprintf('Unknown migration table [%s].', $fromTable));
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentDefinition(): array
    {
        if ($this->activeDefinition === null) {
            throw new LogicException('No active migration definition is set.');
        }

        return $this->activeDefinition;
    }

    /**
     * @return list<string>
     */
    private function targetColumns(string $table): array
    {
        if (! array_key_exists($table, $this->columnCache)) {
            $this->columnCache[$table] = Schema::connection($this->targetConnectionName())
                ->getColumnListing($table);
        }

        return $this->columnCache[$table];
    }

    /**
     * @param  list<string>  $targetColumns
     * @param  list<string>  $uniqueBy
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $rowMapper
     */
    private function copyIncrementingTable(
        string $sourceTable,
        string $targetTable,
        array $targetColumns,
        string $primaryKey,
        array $uniqueBy,
        ?callable $rowMapper,
        ?int $lastId,
        string $watermarkTableName,
    ): int {
        $processed = 0;

        while (true) {
            $query = $this->sourceConnection()->table($sourceTable)
                ->orderBy($primaryKey)
                ->limit(self::BATCH_SIZE);

            if ($lastId !== null) {
                $query->where($primaryKey, '>', $lastId);
            }

            $rows = array_map(
                static fn (object $row): array => (array) $row,
                $query->get()->all(),
            );

            if ($rows === []) {
                break;
            }

            $preparedRows = array_map(
                fn (array $row): array => $this->prepareRow($row, $targetColumns, $rowMapper),
                $rows,
            );

            $this->upsertRows($targetTable, $preparedRows, $uniqueBy);

            $processed += count($preparedRows);
            $lastId = (int) end($rows)[$primaryKey];
            $this->updateWatermark($watermarkTableName, $lastId);
        }

        return $processed;
    }

    /**
     * @param  list<string>  $targetColumns
     * @param  list<string>  $uniqueBy
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $rowMapper
     */
    private function copyNonIncrementingTable(
        string $sourceTable,
        string $targetTable,
        array $targetColumns,
        array $uniqueBy,
        ?callable $rowMapper = null,
    ): int {
        $processed = 0;
        $offset = 0;

        while (true) {
            $rows = array_map(
                static fn (object $row): array => (array) $row,
                $this->sourceConnection()->table($sourceTable)
                    ->offset($offset)
                    ->limit(self::BATCH_SIZE)
                    ->get()
                    ->all(),
            );

            if ($rows === []) {
                break;
            }

            $preparedRows = array_map(
                fn (array $row): array => $this->prepareRow($row, $targetColumns, $rowMapper),
                $rows,
            );

            $this->upsertRows($targetTable, $preparedRows, $uniqueBy);

            $processed += count($preparedRows);
            $offset += self::BATCH_SIZE;
        }

        return $processed;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     */
    private function upsertRows(string $targetTable, array $rows, array $uniqueBy): void
    {
        if ($rows === []) {
            return;
        }

        if ($uniqueBy === []) {
            $this->targetConnection()->table($targetTable)->insert($rows);

            return;
        }

        $updateColumns = array_values(array_diff(array_keys($rows[0]), $uniqueBy));

        $this->targetConnection()->table($targetTable)->upsert($rows, $uniqueBy, $updateColumns);
    }

    private function estimateSeconds(int $rowCount): float
    {
        return round(($rowCount / 1_000_000) * 30, 2);
    }

    /**
     * @param  string|array<int, string>|null  $primaryKey
     */
    private function latestCompletedWatermark(string $sourceTable, string|array|null $primaryKey): string|int|null
    {
        if (! is_string($primaryKey) || $primaryKey === '') {
            return null;
        }

        return $this->sourceConnection()->table($sourceTable)->max($primaryKey);
    }

    private function timestampFromRow(array $row, string $sourceColumn): string
    {
        $value = $row[$sourceColumn] ?? $row[$sourceColumn.'_at'] ?? null;

        if ($value === null || $value === '') {
            return now()->toDateTimeString();
        }

        return Carbon::parse((string) $value)->toDateTimeString();
    }

    private function normalizeDatabaseValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateTimeString();
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    private function normalizeWatermarkValue(string|int|null $value): string|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric((string) $value) ? (int) $value : $value;
    }
}
