<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Queues;

use App\Models\Queue;
use App\Models\Staff;
use App\Services\Scp\Queues\QueueColumnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class QueueColumnsServiceTest extends TestCase
{
    use RefreshDatabase;

    private QueueColumnsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create queue_column table if it doesn't exist
        if (!Schema::connection('legacy')->hasTable('queue_column')) {
            Schema::connection('legacy')->create('queue_column', function ($table) {
                $table->unsignedInteger('id')->autoIncrement();
                $table->unsignedInteger('queue_id');
                $table->string('name', 64);
                $table->unsignedInteger('sort')->default(0);
                $table->unsignedInteger('width')->default(0);
                $table->tinyInteger('truncate')->default(0);
                $table->tinyInteger('translatable')->default(0);
                $table->string('heading', 64)->default('');
                $table->tinyInteger('primary')->default(0);
                $table->tinyInteger('secondary')->default(0);
                $table->tinyInteger('filter')->default(0);
                $table->string('flags', 255)->default('');
                $table->timestamp('updated')->nullable();
            });
        }

        // Create queue_columns table if it doesn't exist
        if (!Schema::connection('legacy')->hasTable('queue_columns')) {
            Schema::connection('legacy')->create('queue_columns', function ($table) {
                $table->unsignedInteger('queue_id');
                $table->unsignedInteger('column_id');
                $table->unsignedInteger('staff_id');
                $table->unsignedInteger('sort')->default(0);
                $table->unsignedInteger('width')->default(0);
                $table->string('heading', 64)->default('');
                $table->string('bits', 255)->default('');
                $table->primary(['queue_id', 'column_id', 'staff_id']);
            });
        }

        // Clean up tables
        DB::connection('legacy')->table('queue_columns')->delete();
        DB::connection('legacy')->table('queue_column')->delete();

        $this->service = new QueueColumnsService();
    }

    public function test_upserts_per_staff_column_rows(): void
    {
        $queue = Queue::factory()->create();
        $staff = Staff::factory()->create();

        // Seed queue_column rows
        DB::connection('legacy')->table('queue_column')->insert([
            ['id' => 1, 'queue_id' => $queue->id, 'name' => 'ticket_id', 'sort' => 0, 'width' => 100],
            ['id' => 2, 'queue_id' => $queue->id, 'name' => 'subject', 'sort' => 1, 'width' => 200],
        ]);

        // Update columns for staff
        $this->service->update($staff, $queue, [
            ['column_id' => 1, 'sort' => 0, 'width' => 150, 'heading' => 'ID'],
            ['column_id' => 2, 'sort' => 1, 'width' => 250, 'heading' => 'Subject'],
        ]);

        // Verify rows were upserted
        $this->assertDatabaseHas(
            'queue_columns',
            [
                'queue_id' => $queue->id,
                'column_id' => 1,
                'staff_id' => $staff->staff_id,
                'sort' => 0,
                'width' => 150,
                'heading' => 'ID',
            ],
            'legacy'
        );

        $this->assertDatabaseHas(
            'queue_columns',
            [
                'queue_id' => $queue->id,
                'column_id' => 2,
                'staff_id' => $staff->staff_id,
                'sort' => 1,
                'width' => 250,
                'heading' => 'Subject',
            ],
            'legacy'
        );
    }

    public function test_does_not_write_staff_id_zero_default_rows(): void
    {
        $queue = Queue::factory()->create();
        $staff = Staff::factory()->create();

        // Seed queue_column rows
        DB::connection('legacy')->table('queue_column')->insert([
            ['id' => 1, 'queue_id' => $queue->id, 'name' => 'ticket_id', 'sort' => 0, 'width' => 100],
        ]);

        // Update columns for staff
        $this->service->update($staff, $queue, [
            ['column_id' => 1, 'sort' => 0, 'width' => 150, 'heading' => 'ID'],
        ]);

        // Verify no staff_id=0 row exists
        $zeroStaffRows = DB::connection('legacy')->table('queue_columns')
            ->where('queue_id', $queue->id)
            ->where('column_id', 1)
            ->where('staff_id', 0)
            ->count();

        $this->assertSame(0, $zeroStaffRows);

        // Verify the staff-specific row exists
        $this->assertDatabaseHas(
            'queue_columns',
            [
                'queue_id' => $queue->id,
                'column_id' => 1,
                'staff_id' => $staff->staff_id,
            ],
            'legacy'
        );
    }

    public function test_throws_for_invalid_column_id(): void
    {
        $queue = Queue::factory()->create();
        $staff = Staff::factory()->create();

        // Seed queue_column rows
        DB::connection('legacy')->table('queue_column')->insert([
            ['id' => 1, 'queue_id' => $queue->id, 'name' => 'ticket_id', 'sort' => 0, 'width' => 100],
        ]);

        // Try to update with non-existent column_id
        $this->expectException(\InvalidArgumentException::class);

        $this->service->update($staff, $queue, [
            ['column_id' => 999, 'sort' => 0, 'width' => 150, 'heading' => 'Invalid'],
        ]);
    }
}
