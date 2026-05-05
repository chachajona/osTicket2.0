<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use Illuminate\Support\Facades\DB;

final class SearchIndexer
{
    public function index(string $objectType, int $objectId, string $title, string $content): void
    {
        $processedContent = $this->plain($content);

        DB::connection('legacy')->table('_search')->updateOrInsert(
            [
                'object_type' => $objectType,
                'object_id' => $objectId,
            ],
            [
                'title' => $title,
                'content' => $processedContent,
            ]
        );
    }

    private function plain(string $content): string
    {
        $stripped = strip_tags($content);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $decoded) ?? '';

        return trim($normalized);
    }
}
