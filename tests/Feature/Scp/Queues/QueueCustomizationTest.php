<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Queues;

use App\Models\Queue;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class QueueCustomizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['osticket.ticket_lock' => '0']);

        DB::connection('legacy')->table('staff')->delete();
        DB::connection('legacy')->table('queue')->delete();
        DB::connection('legacy')->table('queue_column')->delete();
        DB::connection('legacy')->table('queue_columns')->delete();
        DB::connection('legacy')->table('queue_config')->delete();
    }

    public function test_per_staff_column_edits_isolated(): void
    {
        $a = Staff::factory()->admin()->create();
        $b = Staff::factory()->admin()->create();

        $queue = Queue::factory()->create();

        DB::connection('legacy')->table('queue_column')->insert([
            [
                'id' => 1,
                'queue_id' => $queue->id,
                'name' => 'X',
                'sort' => 1,
                'width' => 100,
                'truncate' => 'wrap',
                'translatable' => '',
                'heading' => 'X',
                'primary' => 'x',
                'secondary' => '',
                'filter' => '',
                'flags' => 0,
                'updated' => now()->toDateTimeString(),
            ],
        ]);

        $response = $this->actingAs($a, 'staff')
            ->patchJson("/scp/queues/{$queue->id}/columns", [
                'columns' => [
                    [
                        'column_id' => 1,
                        'sort' => 9,
                        'width' => 222,
                        'heading' => 'A-side',
                    ],
                ],
            ]);

        $response->assertFound();

        $response = $this->actingAs($b, 'staff')
            ->patchJson("/scp/queues/{$queue->id}/columns", [
                'columns' => [
                    [
                        'column_id' => 1,
                        'sort' => 1,
                        'width' => 100,
                        'heading' => 'B-side',
                    ],
                ],
            ]);

        $response->assertFound();

        $this->assertDatabaseHas('queue_columns', [
            'queue_id' => $queue->id,
            'column_id' => 1,
            'staff_id' => $a->staff_id,
            'heading' => 'A-side',
        ], 'legacy');

        $this->assertDatabaseHas('queue_columns', [
            'queue_id' => $queue->id,
            'column_id' => 1,
            'staff_id' => $b->staff_id,
            'heading' => 'B-side',
        ], 'legacy');
    }

    public function test_queue_config_update_succeeds(): void
    {
        $staff = Staff::factory()->admin()->create();
        $queue = Queue::factory()->create();

        $response = $this->actingAs($staff, 'staff')
            ->patchJson("/scp/queues/{$queue->id}/config", [
                'sort_id' => 5,
                'filter' => 'open',
            ]);

        $response->assertFound();

        $this->assertDatabaseHas('queue_config', [
            'queue_id' => $queue->id,
            'staff_id' => $staff->staff_id,
        ], 'legacy');

        $config = DB::connection('legacy')
            ->table('queue_config')
            ->where('queue_id', $queue->id)
            ->where('staff_id', $staff->staff_id)
            ->first();

        $setting = json_decode($config->setting, true);
        $this->assertSame(5, $setting['sort_id']);
        $this->assertSame('open', $setting['filter']);
    }
}
