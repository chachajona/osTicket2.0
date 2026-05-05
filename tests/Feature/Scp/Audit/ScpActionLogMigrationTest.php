<?php

use Illuminate\Support\Facades\Schema;

test('scp_action_log table exists with required columns', function (): void {
    expect(Schema::hasTable('scp_action_log'))->toBeTrue();

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
        expect(Schema::hasColumn('scp_action_log', $column))
            ->toBeTrue("Column {$column} should exist in scp_action_log table");
    }
});

test('scp_action_log table has correct indexes', function (): void {
    $indexes = Schema::getIndexes('scp_action_log');
    $indexNames = array_map(fn ($idx) => $idx['name'], $indexes);

    // Check for composite indexes
    expect(in_array('scp_action_log_staff_id_created_at_index', $indexNames))->toBeTrue();
    expect(in_array('scp_action_log_ticket_id_created_at_index', $indexNames))->toBeTrue();
    expect(in_array('scp_action_log_action_created_at_index', $indexNames))->toBeTrue();
});

test('scp_action_log model can be instantiated', function (): void {
    $model = new \App\Models\Scp\ScpActionLog();

    expect($model)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
    expect($model->getTable())->toBe('scp_action_log');
    expect($model->timestamps)->toBeFalse();
});
