<?php

use App\Models\Queue;
use App\Models\QueueColumn;
use App\Models\QueueColumns;
use App\Models\QueueConfig;
use App\Models\QueueExport;
use App\Models\QueueSort;
use App\Models\QueueSorts;

test('Queue model reads from legacy database', function () {
    $queue = Queue::first();

    if ($queue === null) {
        $this->markTestSkipped('No queues found in legacy database.');
    }

    expect($queue)->toBeInstanceOf(Queue::class);
    expect($queue->id)->toBeInt();
});

test('Queue loads children relation', function () {
    $queue = Queue::whereHas('children')->with('children')->first();

    if ($queue === null) {
        $this->markTestSkipped('No queues with children found.');
    }

    expect($queue->children)->not->toBeEmpty();
    expect($queue->children->first()->parent_id)->toBe($queue->id);
});

test('Queue loads parent relation', function () {
    $queue = Queue::whereNotNull('parent_id')->with('parent')->first();

    if ($queue === null) {
        $this->markTestSkipped('No child queues found.');
    }

    expect($queue->parent)->not->toBeNull();
    expect($queue->parent->id)->toBe($queue->parent_id);
});

test('QueueColumn model reads from legacy database', function () {
    $column = QueueColumn::first();

    if ($column === null) {
        $this->markTestSkipped('No queue columns found in legacy database.');
    }

    expect($column)->toBeInstanceOf(QueueColumn::class);
    expect($column->id)->toBeInt();
});

test('QueueColumn loads queue relation', function () {
    $column = QueueColumn::with('queue')->first();

    if ($column === null) {
        $this->markTestSkipped('No queue columns found.');
    }

    expect($column->queue)->not->toBeNull();
    expect($column->queue->id)->toBe($column->queue_id);
});

test('QueueColumns pivot model reads from legacy database', function () {
    $pivot = QueueColumns::first();

    if ($pivot === null) {
        $this->markTestSkipped('No queue_columns entries found in legacy database.');
    }

    expect($pivot)->toBeInstanceOf(QueueColumns::class);
});

test('QueueConfig model reads from legacy database', function () {
    $config = QueueConfig::first();

    if ($config === null) {
        $this->markTestSkipped('No queue_config entries found in legacy database.');
    }

    expect($config)->toBeInstanceOf(QueueConfig::class);
});

test('QueueExport model reads from legacy database', function () {
    $export = QueueExport::first();

    if ($export === null) {
        $this->markTestSkipped('No queue_export entries found in legacy database.');
    }

    expect($export)->toBeInstanceOf(QueueExport::class);
    expect($export->id)->toBeInt();
});

test('QueueSort model reads from legacy database', function () {
    $sort = QueueSort::first();

    if ($sort === null) {
        $this->markTestSkipped('No queue_sort entries found in legacy database.');
    }

    expect($sort)->toBeInstanceOf(QueueSort::class);
    expect($sort->id)->toBeInt();
});

test('QueueSorts pivot model reads from legacy database', function () {
    $pivot = QueueSorts::first();

    if ($pivot === null) {
        $this->markTestSkipped('No queue_sorts entries found in legacy database.');
    }

    expect($pivot)->toBeInstanceOf(QueueSorts::class);
});
