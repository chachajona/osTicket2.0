<?php

use App\Models\Scp\ScpActionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

test('scp_action_log table exists with required columns', function (): void {
    $schema = Schema::connection('osticket2');

    expect($schema->hasTable('action_log'))->toBeTrue();

    $columns = [
        'id',
        'staff_id',
        'ticket_id',
        'thread_id',
        'queue_id',
        'action',
        'outcome',
        'http_status',
        'before_state',
        'after_state',
        'lock_id',
        'request_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    foreach ($columns as $column) {
        expect($schema->hasColumn('action_log', $column))
            ->toBeTrue("Column {$column} should exist in scp_action_log table");
    }
});

test('scp_action_log table has correct indexes', function (): void {
    $indexes = Schema::connection('osticket2')->getIndexes('action_log');
    $indexedColumns = array_map(fn ($idx) => $idx['columns'], $indexes);

    expect(in_array(['staff_id', 'created_at'], $indexedColumns, true))->toBeTrue();
    expect(in_array(['ticket_id', 'created_at'], $indexedColumns, true))->toBeTrue();
    expect(in_array(['action', 'created_at'], $indexedColumns, true))->toBeTrue();
});

test('scp_action_log model can be instantiated', function (): void {
    $model = new ScpActionLog;

    expect($model)->toBeInstanceOf(Model::class);
    expect($model->getConnectionName())->toBe('osticket2');
    expect($model->getTable())->toBe('action_log');
    expect($model->timestamps)->toBeFalse();
});
