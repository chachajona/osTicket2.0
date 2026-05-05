<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Migration\LegacyMigrator;
use Illuminate\Console\Command;

class LegacyMigrateCommand extends Command
{
    protected $signature = 'legacy:migrate {--dry-run} {--verify} {--from=} {--sample=100}';

    protected $description = 'Copy legacy install data into the target application database.';

    public function handle(LegacyMigrator $legacyMigrator): int
    {
        if ($this->option('dry-run') && $this->option('verify')) {
            $this->error('Use either --dry-run or --verify, not both.');

            return self::FAILURE;
        }

        $from = $this->option('from');

        try {
            $summary = match (true) {
                (bool) $this->option('dry-run') => $legacyMigrator->dryRun(is_string($from) && $from !== '' ? $from : null),
                (bool) $this->option('verify') => $legacyMigrator->verify(
                    is_string($from) && $from !== '' ? $from : null,
                    max(1, (int) $this->option('sample')),
                ),
                default => $legacyMigrator->migrate(is_string($from) && $from !== '' ? $from : null),
            };
        } catch (\Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Status', 'Source', 'Target', 'Seconds / Estimate', 'Notes'],
            array_map(function (array $result): array {
                return [
                    $result['table'] ?? '-',
                    $result['status'] ?? '-',
                    (string) ($result['source_count'] ?? $result['sample_size'] ?? '-'),
                    (string) ($result['target_count'] ?? $result['translated_roles'] ?? '-'),
                    (string) ($result['duration_seconds'] ?? $result['estimate_seconds'] ?? '-'),
                    $result['notes'] ?? '',
                ];
            }, $summary['results']),
        );

        if (($summary['anomalies'] ?? []) !== []) {
            $this->warn('Anomalies:');

            foreach ($summary['anomalies'] as $anomaly) {
                $this->line('- '.$anomaly);
            }
        }

        if (isset($summary['estimated_seconds']) && $this->option('dry-run')) {
            $this->info(sprintf('Estimated total seconds: %.2f', (float) $summary['estimated_seconds']));
        }

        if (isset($summary['total_seconds']) && ! $this->option('dry-run')) {
            $this->info(sprintf('Total seconds: %.3f', (float) $summary['total_seconds']));
        }

        return ! empty($summary['successful']) ? self::SUCCESS : self::FAILURE;
    }
}
