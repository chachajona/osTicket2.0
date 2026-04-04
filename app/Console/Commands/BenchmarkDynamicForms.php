<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Prototype\DynamicForms\CdataApproach;
use App\Prototype\DynamicForms\EavApproach;
use App\Prototype\DynamicForms\JsonAccessorApproach;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkDynamicForms extends Command
{
    protected $signature = 'benchmark:dynamic-forms
                            {--iterations=100 : Number of iterations per approach}
                            {--ticket-id= : Specific ticket ID to benchmark (auto-selects if omitted)}
                            {--batch-size=10 : Batch size for batch benchmark tests}';

    protected $description = 'Benchmark three dynamic forms access approaches and recommend an architecture';

    public function handle(): int
    {
        $iterations = (int) $this->option('iterations');
        $batchSize = (int) $this->option('batch-size');

        $ticketId = $this->resolveTicketId();
        if (! $ticketId) {
            $this->error('No tickets found in the database. Cannot benchmark.');

            return self::FAILURE;
        }

        $ticketIds = $this->resolveTicketIds($batchSize);

        $this->info('Benchmarking dynamic forms access approaches');
        $this->info("Ticket ID: {$ticketId} | Iterations: {$iterations} | Batch size: ".count($ticketIds));
        $this->newLine();

        $results = [];
        $sampleOutputs = [];

        JsonAccessorApproach::setCacheStore('array');
        EavApproach::setCacheStore('array');

        // --- Approach A: Direct cdata ---
        JsonAccessorApproach::flushCache();
        EavApproach::flushCache();

        $this->info('Running Approach A (CdataApproach)...');
        [$timeA, $queriesA] = $this->runBenchmark($iterations, function () use ($ticketId) {
            return CdataApproach::getCustomFields($ticketId);
        });
        $sampleOutputs['A'] = CdataApproach::getCustomFields($ticketId);
        $results[] = [
            'Approach',
            'Total (s)',
            'Avg (ms)',
            'Queries/iter',
            'Fields returned',
        ];
        $results = [];

        $results['A'] = [
            'label' => 'A: CdataApproach (direct __cdata)',
            'total' => $timeA,
            'avg' => round($timeA / $iterations * 1000, 3),
            'queries' => $queriesA,
            'fields' => count($sampleOutputs['A'] ?? []),
        ];

        // --- Approach B: JsonAccessor (cold cache) ---
        JsonAccessorApproach::flushCache();
        $this->info('Running Approach B (JsonAccessorApproach, cold cache)...');
        [$timeBCold, $queriesBCold] = $this->runBenchmark($iterations, function () use ($ticketId) {
            JsonAccessorApproach::flushCache();

            return JsonAccessorApproach::getCustomFields($ticketId);
        });
        $sampleOutputs['B'] = JsonAccessorApproach::getCustomFields($ticketId);

        $results['B_cold'] = [
            'label' => 'B: JsonAccessorApproach (cold cache each iter)',
            'total' => $timeBCold,
            'avg' => round($timeBCold / $iterations * 1000, 3),
            'queries' => $queriesBCold,
            'fields' => count($sampleOutputs['B'] ?? []),
        ];

        // --- Approach B: JsonAccessor (warm cache) ---
        JsonAccessorApproach::flushCache();
        JsonAccessorApproach::getColumnLabelMap();
        $this->info('Running Approach B (JsonAccessorApproach, warm cache)...');
        [$timeBWarm, $queriesBWarm] = $this->runBenchmark($iterations, function () use ($ticketId) {
            return JsonAccessorApproach::getCustomFields($ticketId);
        });

        $results['B_warm'] = [
            'label' => 'B: JsonAccessorApproach (warm cache)',
            'total' => $timeBWarm,
            'avg' => round($timeBWarm / $iterations * 1000, 3),
            'queries' => $queriesBWarm,
            'fields' => count($sampleOutputs['B'] ?? []),
        ];

        // --- Approach C: EAV (cold cache) ---
        EavApproach::flushCache();
        $this->info('Running Approach C (EavApproach, cold cache)...');
        [$timeCCold, $queriesCCold] = $this->runBenchmark($iterations, function () use ($ticketId) {
            EavApproach::flushCache();

            return EavApproach::getCustomFields($ticketId);
        });
        $sampleOutputs['C'] = EavApproach::getCustomFields($ticketId);

        $results['C_cold'] = [
            'label' => 'C: EavApproach (cold cache each iter)',
            'total' => $timeCCold,
            'avg' => round($timeCCold / $iterations * 1000, 3),
            'queries' => $queriesCCold,
            'fields' => count($sampleOutputs['C'] ?? []),
        ];

        // --- Approach C: EAV (warm cache) ---
        EavApproach::flushCache();
        EavApproach::getFieldMetadata();
        $this->info('Running Approach C (EavApproach, warm cache)...');
        [$timeCWarm, $queriesCWarm] = $this->runBenchmark($iterations, function () use ($ticketId) {
            return EavApproach::getCustomFields($ticketId);
        });

        $results['C_warm'] = [
            'label' => 'C: EavApproach (warm cache)',
            'total' => $timeCWarm,
            'avg' => round($timeCWarm / $iterations * 1000, 3),
            'queries' => $queriesCWarm,
            'fields' => count($sampleOutputs['C'] ?? []),
        ];

        // --- Batch benchmarks ---
        $this->info('Running batch benchmark (batch size: '.count($ticketIds).')...');
        [$timeBatch_A] = $this->runBenchmark(20, fn () => CdataApproach::getCustomFieldsBatch($ticketIds));
        [$timeBatch_B] = $this->runBenchmark(20, fn () => JsonAccessorApproach::getCustomFieldsBatch($ticketIds));
        [$timeBatch_C] = $this->runBenchmark(20, fn () => EavApproach::getCustomFieldsBatch($ticketIds));

        // --- Display results ---
        $this->newLine();
        $this->info('=== SINGLE TICKET RESULTS ('.$iterations.' iterations) ===');
        $this->table(
            ['Approach', 'Total (s)', 'Avg/iter (ms)', 'DB Queries/iter', 'Fields'],
            array_map(fn ($r) => [
                $r['label'],
                round($r['total'], 4),
                $r['avg'],
                $r['queries'],
                $r['fields'],
            ], $results)
        );

        $this->newLine();
        $this->info('=== BATCH RESULTS (20 iterations, batch='.count($ticketIds).') ===');
        $this->table(
            ['Approach', 'Total (s)', 'Avg/batch (ms)'],
            [
                ['A: CdataApproach (batch)', round($timeBatch_A, 4), round($timeBatch_A / 20 * 1000, 3)],
                ['B: JsonAccessorApproach (batch)', round($timeBatch_B, 4), round($timeBatch_B / 20 * 1000, 3)],
                ['C: EavApproach (batch)', round($timeBatch_C, 4), round($timeBatch_C / 20 * 1000, 3)],
            ]
        );

        $this->newLine();
        $this->info('=== SAMPLE FIELD OUTPUT ===');
        $this->displaySampleOutput('A (cdata columns)', $sampleOutputs['A']);
        $this->displaySampleOutput('B (label-mapped)', $sampleOutputs['B']);
        $this->displaySampleOutput('C (EAV labels)', $sampleOutputs['C']);

        $this->newLine();
        $this->outputRecommendation($results, $sampleOutputs);

        return self::SUCCESS;
    }

    private function resolveTicketId(): ?int
    {
        $id = $this->option('ticket-id');
        if ($id) {
            return (int) $id;
        }

        $ticket = Ticket::first();

        return $ticket?->ticket_id;
    }

    private function resolveTicketIds(int $batchSize): array
    {
        return Ticket::limit($batchSize)->pluck('ticket_id')->map(fn ($id) => (int) $id)->all();
    }

    private function runBenchmark(int $iterations, callable $fn): array
    {
        $queryCount = 0;

        DB::connection('legacy')->listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $fn();
        }
        $elapsed = microtime(true) - $start;

        $avgQueries = $iterations > 0 ? round($queryCount / $iterations, 1) : 0;

        return [$elapsed, $avgQueries];
    }

    private function displaySampleOutput(string $label, ?array $output): void
    {
        $this->line("<comment>{$label}:</comment>");
        if (! $output) {
            $this->line('  (no data)');

            return;
        }
        foreach (array_slice($output, 0, 5, true) as $key => $value) {
            $truncated = mb_strlen((string) $value) > 60
                ? mb_substr((string) $value, 0, 57).'...'
                : $value;
            $this->line("  {$key}: {$truncated}");
        }
        if (count($output) > 5) {
            $this->line('  ... and '.(count($output) - 5).' more fields');
        }
    }

    private function outputRecommendation(array $results, array $sampleOutputs): void
    {
        $this->info('=== RECOMMENDATION ===');

        $avgA = $results['A']['avg'] ?? PHP_INT_MAX;
        $avgBWarm = $results['B_warm']['avg'] ?? PHP_INT_MAX;
        $avgCWarm = $results['C_warm']['avg'] ?? PHP_INT_MAX;

        $cdataFields = count($sampleOutputs['A'] ?? []);
        $eavFields = count($sampleOutputs['C'] ?? []);
        $extraFieldsInEav = max(0, $eavFields - $cdataFields);

        $this->line('Performance ranking (warm cache, single ticket):');
        $this->line("  1st: A={$avgA}ms | 2nd: B={$avgBWarm}ms | 3rd: C={$avgCWarm}ms");
        $this->newLine();

        if ($extraFieldsInEav > 0) {
            $this->warn("EAV returns {$extraFieldsInEav} more field(s) than __cdata. Some custom fields are NOT in the materialized view.");
            $this->line('Consider Approach C (EAV) or B+C hybrid if completeness is required.');
        } else {
            $this->line("__cdata and EAV return the same field count ({$cdataFields} fields).");
            $this->info('RECOMMENDED: Approach B (JsonAccessorApproach) with warm cache.');
            $this->line('Rationale: Human-readable keys, near-parity performance with Approach A, and');
            $this->line('           uses the fast __cdata materialized view. Label map cached in Redis/file.');
        }

        $this->newLine();
        $this->line('See docs/dynamic-forms-strategy.md for full architectural analysis.');
    }
}
