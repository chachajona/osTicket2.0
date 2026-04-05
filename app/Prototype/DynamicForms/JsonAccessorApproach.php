<?php

namespace App\Prototype\DynamicForms;

use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Approach B: JSON accessor that translates __cdata column IDs to human-readable field labels.
 *
 * Queries form_field to build a column→label map, then merges with cdata values.
 * Returns an associative array keyed by the form field label (e.g. 'Subject', 'Priority').
 *
 * Pros:
 *   - Human-readable keys in output
 *   - Still uses the fast __cdata materialized view for values
 *   - Label map is cached (no repeated form_field queries)
 *
 * Cons:
 *   - Extra query for label map on cold cache
 *   - Label/column mapping assumes form_field.name matches cdata column name
 *   - Requires form_field table to exist and be consistent with __cdata columns
 *
 * Usage:
 *   $fields = JsonAccessorApproach::getCustomFields($ticketId);
 *   // Returns: ['Subject' => '...', 'Priority' => '2', ...]
 */
class JsonAccessorApproach
{
    private const CACHE_KEY = 'dynamic_forms.cdata_label_map';

    private const CACHE_TTL = 3600;

    private static string $cacheStore = 'file';

    public static function setCacheStore(string $store): void
    {
        self::$cacheStore = $store;
    }

    /**
     * @return array<string, string> Maps cdata column name → human-readable label
     */
    public static function getColumnLabelMap(): array
    {
        return Cache::store(self::$cacheStore)->remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $rows = DB::connection('legacy')
                ->table('form_field')
                ->select('name', 'label')
                ->whereNotNull('name')
                ->whereNotNull('label')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                // form_field.name is the column name used in __cdata
                $map[$row->name] = $row->label;
            }

            return $map;
        });
    }

    /**
     * @return array<string, mixed>|null Label-keyed custom fields, or null if not found
     */
    public static function getCustomFields(int $ticketId): ?array
    {
        $cdataRow = DB::connection('legacy')
            ->table('ticket__cdata')
            ->where('ticket_id', $ticketId)
            ->first();

        if (! $cdataRow) {
            return null;
        }

        $labelMap = self::getColumnLabelMap();
        $cdataArray = (array) $cdataRow;
        unset($cdataArray['ticket_id']);

        $result = [];
        foreach ($cdataArray as $column => $value) {
            if ($value === null) {
                continue;
            }
            // Use human-readable label if mapping exists, else fall back to column name
            $key = $labelMap[$column] ?? $column;
            $result[$key] = $value;
        }

        return $result ?: null;
    }

    /**
     * @param  array<int>  $ticketIds
     * @return array<int, array<string, mixed>> Keyed by ticket_id
     */
    public static function getCustomFieldsBatch(array $ticketIds): array
    {
        $cdataRows = DB::connection('legacy')
            ->table('ticket__cdata')
            ->whereIn('ticket_id', $ticketIds)
            ->get()
            ->keyBy('ticket_id');

        $labelMap = self::getColumnLabelMap();
        $results = [];

        foreach ($cdataRows as $ticketId => $cdataRow) {
            $cdataArray = (array) $cdataRow;
            unset($cdataArray['ticket_id']);

            $fields = [];
            foreach ($cdataArray as $column => $value) {
                if ($value === null) {
                    continue;
                }
                $key = $labelMap[$column] ?? $column;
                $fields[$key] = $value;
            }

            $results[(int) $ticketId] = $fields;
        }

        return $results;
    }

    /**
     * Flush the cached column→label map (call after form_field changes).
     */
    public static function flushCache(): void
    {
        Cache::store(self::$cacheStore)->forget(self::CACHE_KEY);
    }

    /**
     * Get accessor on a Ticket model instance.
     * Intended to be added as an Eloquent attribute accessor.
     *
     * Example in Ticket model:
     *   protected function customFields(): Attribute {
     *       return Attribute::get(fn () => JsonAccessorApproach::getCustomFields($this->ticket_id));
     *   }
     *
     * @return array<string, mixed>|null
     */
    public static function getFromTicket(Ticket $ticket): ?array
    {
        return self::getCustomFields($ticket->ticket_id);
    }
}
