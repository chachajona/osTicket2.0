<?php

declare(strict_types=1);

namespace App\Migration;

use App\Migration\Migrators\CannedResponseMigrator;
use App\Migration\Migrators\DepartmentMigrator;
use App\Migration\Migrators\EmailConfigMigrator;
use App\Migration\Migrators\FilterActionMigrator;
use App\Migration\Migrators\FilterMigrator;
use App\Migration\Migrators\FilterRuleMigrator;
use App\Migration\Migrators\HelpTopicMigrator;
use App\Migration\Migrators\RoleMigrator;
use App\Migration\Migrators\SlaMigrator;
use App\Migration\Migrators\StaffDeptAccessMigrator;
use App\Migration\Migrators\StaffMigrator;
use App\Migration\Migrators\TeamMemberMigrator;
use App\Migration\Migrators\TeamMigrator;
use App\Migration\Verifiers\RowCountVerifier;
use App\Migration\Verifiers\SampleDiffVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class LegacyMigrator
{
    /**
     * @var list<AbstractMigrator>
     */
    private array $migrators;

    public function __construct(
        private readonly RoleMigrator $roleMigrator,
        private readonly PermissionsTranslator $permissionsTranslator,
        private readonly SlaMigrator $slaMigrator,
        private readonly EmailConfigMigrator $emailConfigMigrator,
        private readonly DepartmentMigrator $departmentMigrator,
        private readonly StaffMigrator $staffMigrator,
        private readonly StaffDeptAccessMigrator $staffDeptAccessMigrator,
        private readonly TeamMigrator $teamMigrator,
        private readonly TeamMemberMigrator $teamMemberMigrator,
        private readonly HelpTopicMigrator $helpTopicMigrator,
        private readonly CannedResponseMigrator $cannedResponseMigrator,
        private readonly FilterMigrator $filterMigrator,
        private readonly FilterRuleMigrator $filterRuleMigrator,
        private readonly FilterActionMigrator $filterActionMigrator,
        private readonly RowCountVerifier $rowCountVerifier,
        private readonly SampleDiffVerifier $sampleDiffVerifier,
    ) {
        $this->migrators = [
            $this->roleMigrator,
            $this->slaMigrator,
            $this->emailConfigMigrator,
            $this->departmentMigrator,
            $this->staffMigrator,
            $this->staffDeptAccessMigrator,
            $this->teamMigrator,
            $this->teamMemberMigrator,
            $this->helpTopicMigrator,
            $this->cannedResponseMigrator,
            $this->filterMigrator,
            $this->filterRuleMigrator,
            $this->filterActionMigrator,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dryRun(?string $fromTable = null): array
    {
        $this->preflight();
        $results = [];

        foreach ($this->orderedMigrators($fromTable) as $index => $migrator) {
            $results = [...$results, ...$migrator->dryRun($this->innerFromTable($migrator, $fromTable, $index))];
        }

        return $this->summarize($results, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function migrate(?string $fromTable = null): array
    {
        $this->preflight();

        $results = [];
        $anomalies = [];
        $startedAt = microtime(true);

        foreach ($this->orderedMigrators($fromTable) as $index => $migrator) {
            $results = [...$results, ...$migrator->migrate($this->innerFromTable($migrator, $fromTable, $index))];

            if ($migrator === $this->roleMigrator) {
                $translation = $this->permissionsTranslator->translate();
                $results[] = $translation;

                if ($translation['status'] !== 'translated') {
                    $anomalies[] = 'Permissions translation completed with warnings.';
                }
            }
        }

        return $this->summarize($results, $anomalies, $startedAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(?string $fromTable = null, int $sample = 100): array
    {
        $this->preflight();

        $results = [];
        $anomalies = [];
        $startedAt = microtime(true);

        foreach ($this->orderedMigrators($fromTable) as $index => $migrator) {
            $innerFrom = $this->innerFromTable($migrator, $fromTable, $index);

            foreach ($migrator->verificationDefinitions($innerFrom) as $definition) {
                $results[] = $this->rowCountVerifier->verify($migrator, $definition);
                $results[] = $this->sampleDiffVerifier->verify($migrator, $definition, $sample);
            }
        }

        foreach ($results as $result) {
            if (($result['status'] ?? null) !== 'verified') {
                $anomalies[] = (string) ($result['notes'] ?? 'Verification anomaly detected.');
            }
        }

        return $this->summarize($results, $anomalies, $startedAt);
    }

    public function preflight(): void
    {
        DB::connection('legacy')->getPdo();
        DB::connection('osticket2')->getPdo();

        if (! Schema::connection('osticket2')->hasTable('_migration_progress')) {
            throw new LogicException('Target migration progress table is missing. Run php artisan migrate first.');
        }

        $permissionsTable = (string) config('permission.table_names.permissions', 'permissions');

        if (! Schema::connection('osticket2')->hasTable($permissionsTable)) {
            throw new LogicException('Permission tables are missing on the target connection. Run php artisan migrate first.');
        }

        $this->permissionsTranslator->useTargetPermissionConnection(function () use ($permissionsTable): void {
            $permissionsCount = DB::connection('osticket2')->table($permissionsTable)
                ->where('guard_name', 'staff')
                ->count();

            if ($permissionsCount === 0) {
                throw new LogicException('PermissionCatalogSeeder has not been seeded on the target connection.');
            }
        });
    }

    /**
     * @return list<AbstractMigrator>
     */
    private function orderedMigrators(?string $fromTable = null): array
    {
        if ($fromTable === null) {
            return $this->migrators;
        }

        foreach ($this->migrators as $index => $migrator) {
            if ($migrator->handlesTable($fromTable)) {
                return array_slice($this->migrators, $index);
            }
        }

        throw new LogicException(sprintf('Unknown migration table [%s].', $fromTable));
    }

    private function innerFromTable(AbstractMigrator $migrator, ?string $fromTable, int $index): ?string
    {
        if ($index !== 0 || $fromTable === null || ! $migrator->handlesTable($fromTable)) {
            return null;
        }

        return $fromTable;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @param  list<string>  $anomalies
     * @return array<string, mixed>
     */
    private function summarize(array $results, array $anomalies, ?float $startedAt = null): array
    {
        $totalSeconds = $startedAt === null ? null : round(microtime(true) - $startedAt, 3);
        $estimatedSeconds = array_sum(array_map(
            static fn (array $result): float => (float) ($result['estimate_seconds'] ?? 0),
            $results,
        ));

        return [
            'results' => $results,
            'anomalies' => array_values(array_unique(array_filter($anomalies))),
            'total_seconds' => $totalSeconds,
            'estimated_seconds' => round($estimatedSeconds, 2),
            'successful' => collect($results)->every(
                static fn (array $result): bool => ! in_array($result['status'] ?? null, ['failed', 'mismatch', 'missing'], true),
            ),
        ];
    }
}
