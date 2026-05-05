<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Services\Scp\Tickets\SearchIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SearchIndexerTest extends TestCase
{
    use RefreshDatabase;

    private SearchIndexer $indexer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create _search table if it doesn't exist
        if (!Schema::connection('legacy')->hasTable('_search')) {
            Schema::connection('legacy')->create('_search', function ($table) {
                $table->string('object_type', 8);
                $table->unsignedInteger('object_id');
                $table->text('title');
                $table->text('content');
                $table->primary(['object_type', 'object_id']);
            });
        }

        $this->indexer = new SearchIndexer();
    }

    public function test_inserts_search_row(): void
    {
        $this->indexer->index('ticket', 123, 'Test Ticket', 'This is a test ticket with <b>HTML</b> content');

        $this->assertDatabaseHas(
            '_search',
            [
                'object_type' => 'ticket',
                'object_id' => 123,
                'title' => 'Test Ticket',
                'content' => 'This is a test ticket with HTML content',
            ],
            'legacy'
        );
    }

    public function test_updates_existing_row_on_duplicate(): void
    {
        // Insert first time
        $this->indexer->index('ticket', 456, 'Original Title', 'Original content');

        // Verify first insert
        $this->assertDatabaseHas(
            '_search',
            [
                'object_type' => 'ticket',
                'object_id' => 456,
                'title' => 'Original Title',
            ],
            'legacy'
        );

        // Index same object again with different content
        $this->indexer->index('ticket', 456, 'Updated Title', 'Updated &amp; modified content');

        // Verify only one row exists with updated data
        $count = DB::connection('legacy')->table('_search')
            ->where('object_type', 'ticket')
            ->where('object_id', 456)
            ->count();

        $this->assertSame(1, $count);

        $this->assertDatabaseHas(
            '_search',
            [
                'object_type' => 'ticket',
                'object_id' => 456,
                'title' => 'Updated Title',
                'content' => 'Updated & modified content',
            ],
            'legacy'
        );
    }
}
