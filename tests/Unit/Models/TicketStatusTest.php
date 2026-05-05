<?php

namespace Tests\Unit\Models;

use App\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TicketStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create ticket_status table if it doesn't exist
        if (!Schema::connection('legacy')->hasTable('ticket_status')) {
            Schema::connection('legacy')->create('ticket_status', function ($table) {
                $table->unsignedInteger('id')->primary();
                $table->string('name');
                $table->string('state');
                $table->string('mode')->nullable();
                $table->string('flags')->nullable();
                $table->integer('sort')->default(0);
                $table->json('properties')->nullable();
                $table->timestamp('created')->nullable();
                $table->timestamp('updated')->nullable();
            });
        }
    }

    public function test_ticket_status_loads_state_column(): void
    {
        // Seed a ticket_status row
        DB::connection('legacy')->table('ticket_status')->insert([
            'id' => 1,
            'name' => 'Open',
            'state' => 'open',
            'mode' => 'default',
            'flags' => null,
            'sort' => 0,
            'properties' => null,
            'created' => now(),
            'updated' => now(),
        ]);

        // Load the model
        $status = TicketStatus::find(1);

        // Assert state column is loaded correctly
        $this->assertNotNull($status);
        $this->assertSame('open', $status->state);
        $this->assertSame('Open', $status->name);
    }
}
