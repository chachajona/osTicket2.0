<?php

namespace Tests\Unit\Services\Scp\Tickets;

use App\Services\Scp\Tickets\LegacyConfigReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyConfigReaderTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        
        $schema = Schema::connection('legacy');
        if (! $schema->hasTable('config')) {
            $schema->create('config', function (Blueprint $table): void {
                $table->id();
                $table->string('namespace');
                $table->string('key');
                $table->text('value');
                $table->timestamp('updated')->nullable();
            });
        }
        
        DB::connection('legacy')->table('config')->where('namespace', 'core')->delete();
    }

    public function test_returns_lock_disabled_when_setting_is_zero(): void
    {
        DB::connection('legacy')->table('config')->insert([
            ['namespace' => 'core', 'key' => 'ticket_lock', 'value' => '0', 'updated' => now()],
        ]);

        $reader = new LegacyConfigReader();

        $this->assertSame('disabled', $reader->ticketLockMode());
    }

    public function test_returns_on_view_when_setting_is_one(): void
    {
        DB::connection('legacy')->table('config')->insert([
            ['namespace' => 'core', 'key' => 'ticket_lock', 'value' => '1', 'updated' => now()],
        ]);

        $reader = new LegacyConfigReader();
        $this->assertSame('on_view', $reader->ticketLockMode());
    }

    public function test_returns_on_activity_when_setting_is_two(): void
    {
        DB::connection('legacy')->table('config')->insert([
            ['namespace' => 'core', 'key' => 'ticket_lock', 'value' => '2', 'updated' => now()],
        ]);

        $reader = new LegacyConfigReader();
        $this->assertSame('on_activity', $reader->ticketLockMode());
    }

    public function test_defaults_to_disabled_when_setting_missing(): void
    {
        $reader = new LegacyConfigReader();
        $this->assertSame('disabled', $reader->ticketLockMode());
    }

    public function test_lock_time_returns_seconds_with_default_180(): void
    {
        DB::connection('legacy')->table('config')->insert([
            ['namespace' => 'core', 'key' => 'lock_time', 'value' => '5', 'updated' => now()],
        ]);

        $reader = new LegacyConfigReader();
        $this->assertSame(300, $reader->lockTime());  // 5 minutes = 300s
    }

    public function test_lock_time_default_when_missing(): void
    {
        $reader = new LegacyConfigReader();
        $this->assertSame(180, $reader->lockTime());
    }
}
