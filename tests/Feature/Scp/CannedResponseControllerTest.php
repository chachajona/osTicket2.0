<?php

declare(strict_types=1);

namespace Tests\Feature\Scp;

use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CannedResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection('legacy');

        if (! $schema->hasTable('canned_response')) {
            $schema->create('canned_response', function (Blueprint $table): void {
                $table->increments('canned_id');
                $table->unsignedInteger('dept_id')->nullable();
                $table->string('title', 255);
                $table->text('response');
                $table->string('notes', 255)->nullable();
                $table->tinyInteger('isactive')->default(1);
                $table->string('lang', 16)->nullable();
                $table->timestamp('created')->nullable();
                $table->timestamp('updated')->nullable();
            });
        }

        DB::connection('legacy')->table('canned_response')->delete();
    }

    private function createCannedResponse(array $attrs = []): int
    {
        return DB::connection('legacy')->table('canned_response')->insertGetId(array_merge([
            'dept_id' => null,
            'isactive' => 1,
            'title' => 'Default Title',
            'response' => '<p>Default response</p>',
            'lang' => 'en',
            'notes' => '',
            'created' => now(),
            'updated' => now(),
        ], $attrs));
    }

    public function test_returns_global_responses_when_no_dept_id(): void
    {
        /** @var Staff $staff */
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Global Response', 'dept_id' => null]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Global Response']);
    }

    public function test_returns_dept_and_global_responses_when_dept_id_given(): void
    {
        /** @var Staff $staff */
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Global', 'dept_id' => null]);
        $this->createCannedResponse(['title' => 'Dept 5', 'dept_id' => 5]);
        $this->createCannedResponse(['title' => 'Dept 9', 'dept_id' => 9]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index', ['dept_id' => 5]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['title' => 'Global'])
            ->assertJsonFragment(['title' => 'Dept 5'])
            ->assertJsonMissing(['title' => 'Dept 9']);
    }

    public function test_excludes_disabled_responses(): void
    {
        /** @var Staff $staff */
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Enabled', 'isactive' => 1, 'dept_id' => null]);
        $this->createCannedResponse(['title' => 'Disabled', 'isactive' => 0, 'dept_id' => null]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Enabled'])
            ->assertJsonMissing(['title' => 'Disabled']);
    }

    public function test_filters_by_title_query(): void
    {
        /** @var Staff $staff */
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Greeting', 'dept_id' => null]);
        $this->createCannedResponse(['title' => 'Closing', 'dept_id' => null]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index', ['q' => 'greet']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Greeting']);
    }

    public function test_returns_at_most_ten_results(): void
    {
        /** @var Staff $staff */
        $staff = Staff::factory()->create(['isactive' => 1]);
        foreach (range(1, 15) as $i) {
            $this->createCannedResponse(['title' => "Response {$i}", 'dept_id' => null]);
        }

        $response = $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index'))
            ->assertOk();

        $this->assertCount(10, $response->json());
    }

    public function test_returns_401_for_unauthenticated(): void
    {
        $this->getJson(route('scp.canned-responses.index'))->assertUnauthorized();
    }
}
