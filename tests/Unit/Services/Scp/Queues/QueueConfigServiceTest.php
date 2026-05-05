<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Queues;

use App\Models\Queue;
use App\Models\Staff;
use App\Services\Scp\Queues\QueueConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class QueueConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private QueueConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::connection('legacy')->hasTable('queue_config')) {
            Schema::connection('legacy')->create('queue_config', function ($table) {
                $table->unsignedInteger('queue_id');
                $table->unsignedInteger('staff_id');
                $table->text('setting')->nullable();
                $table->timestamp('updated')->nullable();
                $table->primary(['queue_id', 'staff_id']);
            });
        }

        DB::connection('legacy')->table('queue_config')->delete();

        $this->service = new QueueConfigService();
    }

    public function test_upserts_config_json(): void
    {
        $queue = Queue::factory()->create();
        $staff = Staff::factory()->create();

        $config = [
            'sort_id' => 5,
            'filter' => 'status:open',
            'criteria_inheritance' => true,
            'columns_inheritance' => false,
            'sort_inheritance' => true,
        ];

        $this->service->update($staff, $queue, $config);

        $this->assertDatabaseHas(
            'queue_config',
            [
                'queue_id' => $queue->id,
                'staff_id' => $staff->staff_id,
                'setting' => json_encode($config),
            ],
            'legacy'
        );
    }

    public function test_updates_existing_config(): void
    {
        $queue = Queue::factory()->create();
        $staff = Staff::factory()->create();

        $initialConfig = [
            'sort_id' => 1,
            'filter' => 'status:open',
            'criteria_inheritance' => true,
            'columns_inheritance' => false,
            'sort_inheritance' => true,
        ];

        $this->service->update($staff, $queue, $initialConfig);

        $updatedConfig = [
            'sort_id' => 2,
            'filter' => 'status:closed',
            'criteria_inheritance' => false,
            'columns_inheritance' => true,
            'sort_inheritance' => false,
        ];

        $this->service->update($staff, $queue, $updatedConfig);

        $count = DB::connection('legacy')->table('queue_config')
            ->where('queue_id', $queue->id)
            ->where('staff_id', $staff->staff_id)
            ->count();

        $this->assertSame(1, $count);

        $this->assertDatabaseHas(
            'queue_config',
            [
                'queue_id' => $queue->id,
                'staff_id' => $staff->staff_id,
                'setting' => json_encode($updatedConfig),
            ],
            'legacy'
        );
    }
}
