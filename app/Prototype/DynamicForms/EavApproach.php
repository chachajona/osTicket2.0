<?php

namespace App\Prototype\DynamicForms;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Approach C: Full EAV query via form_entry → form_entry_values → form_field JOIN.
 *
 * Bypasses __cdata entirely and reads directly from the canonical EAV tables.
 * Field metadata (label, type, form) is cached to avoid repeated queries.
 *
 * Pros:
 *   - Accesses ALL custom fields, even those not materialized in __cdata
 *   - Human-readable labels from form_field.label
 *   - Not dependent on __cdata being up-to-date
 *   - Can include field type metadata (text, select, date, etc.)
 *
 * Cons:
 *   - More complex JOIN query; more expensive than cdata lookup
 *   - N queries for N object types if not batched
 *   - Cache must be invalidated when form schema changes
 *   - Composite PK on form_entry_values makes bulk upserts tricky
 *
 * Usage:
 *   $fields = EavApproach::getCustomFields($ticketId);
 *   // Returns: ['Subject' => 'Ví điện tử gặp vấn đề', 'Priority' => '2', ...]
 */
class EavApproach
{
    private const FIELD_META_CACHE_KEY = 'dynamic_forms.eav_field_meta';

    private const FIELD_META_CACHE_TTL = 3600;

    private static string $cacheStore = 'file';

    public static function setCacheStore(string $store): void
    {
        self::$cacheStore = $store;
    }

    /**
     * @return array<int, array{label: string, name: string, type: string, form_id: int}>
     *                                                                                    Keyed by form_field.id
     */
    public static function getFieldMetadata(): array
    {
        return Cache::store(self::$cacheStore)->remember(self::FIELD_META_CACHE_KEY, self::FIELD_META_CACHE_TTL, function () {
            $rows = DB::connection('legacy')
                ->table('form_field')
                ->select('id', 'form_id', 'name', 'label', 'type')
                ->get();

            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row->id] = [
                    'label' => $row->label,
                    'name' => $row->name,
                    'type' => $row->type,
                    'form_id' => (int) $row->form_id,
                ];
            }

            return $map;
        });
    }

    /**
     * @param  string  $objectType  'T' for tickets (default)
     * @return array<string, mixed>|null
     */
    public static function getCustomFields(int $ticketId, string $objectType = 'T'): ?array
    {
        $rows = DB::connection('legacy')
            ->table('form_entry as fe')
            ->join('form_entry_values as fev', 'fev.entry_id', '=', 'fe.id')
            ->where('fe.object_id', $ticketId)
            ->where('fe.object_type', $objectType)
            ->select('fev.field_id', 'fev.value', 'fev.value_id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $fieldMeta = self::getFieldMetadata();
        $result = [];

        foreach ($rows as $row) {
            $fieldId = (int) $row->field_id;
            if (! isset($fieldMeta[$fieldId])) {
                continue;
            }

            $label = $fieldMeta[$fieldId]['label'];
            if ($label && $row->value !== null) {
                $result[$label] = $row->value;
            }
        }

        return $result ?: null;
    }

    /**
     * @param  array<int>  $ticketIds
     * @return array<int, array<string, mixed>> Keyed by ticket_id (object_id)
     */
    public static function getCustomFieldsBatch(array $ticketIds, string $objectType = 'T'): array
    {
        $rows = DB::connection('legacy')
            ->table('form_entry as fe')
            ->join('form_entry_values as fev', 'fev.entry_id', '=', 'fe.id')
            ->whereIn('fe.object_id', $ticketIds)
            ->where('fe.object_type', $objectType)
            ->select('fe.object_id', 'fev.field_id', 'fev.value')
            ->get();

        $fieldMeta = self::getFieldMetadata();
        $results = [];

        foreach ($rows as $row) {
            $ticketId = (int) $row->object_id;
            $fieldId = (int) $row->field_id;

            if (! isset($fieldMeta[$fieldId]) || $row->value === null) {
                continue;
            }

            $label = $fieldMeta[$fieldId]['label'];
            if ($label) {
                $results[$ticketId][$label] = $row->value;
            }
        }

        return $results;
    }

    /**
     * Return full field metadata alongside values (useful for rendering forms).
     *
     * @return Collection<int, array{label: string, type: string, value: mixed}>
     */
    public static function getCustomFieldsWithMeta(int $ticketId): Collection
    {
        $rows = DB::connection('legacy')
            ->table('form_entry as fe')
            ->join('form_entry_values as fev', 'fev.entry_id', '=', 'fe.id')
            ->join('form_field as ff', 'ff.id', '=', 'fev.field_id')
            ->where('fe.object_id', $ticketId)
            ->where('fe.object_type', 'T')
            ->select('ff.id', 'ff.label', 'ff.type', 'ff.name', 'fev.value', 'fev.value_id')
            ->get();

        return $rows->mapWithKeys(function ($row) {
            return [
                (int) $row->id => [
                    'label' => $row->label,
                    'name' => $row->name,
                    'type' => $row->type,
                    'value' => $row->value,
                    'value_id' => $row->value_id,
                ],
            ];
        });
    }

    /**
     * Flush cached field metadata (call after form_field schema changes).
     */
    public static function flushCache(): void
    {
        Cache::store(self::$cacheStore)->forget(self::FIELD_META_CACHE_KEY);
    }
}
