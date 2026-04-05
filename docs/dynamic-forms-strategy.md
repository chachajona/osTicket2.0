# Dynamic Forms Access Strategy

## Context

osTicket uses an EAV (Entity-Attribute-Value) pattern for custom fields. The core tables are:

- `form` — form template definitions
- `form_field` — field metadata (label, name, type, configuration)
- `form_entry` — links forms to objects (tickets, users)
- `form_entry_values` — the actual field values per entry

To avoid slow EAV joins in production queries, osTicket also maintains a **materialized view** table `ticket__cdata` that flattens all ticket custom fields into a single row per ticket. This table is kept up-to-date via database triggers / signal callbacks in the legacy PHP codebase.

## Three Approaches Evaluated

### Approach A — Direct `__cdata` Access (`CdataApproach`)

Reads `ticket__cdata` directly via the existing `Ticket::cdata()` Eloquent relationship.

```php
$ticket = Ticket::with('cdata')->find($ticketId);
$subject  = $ticket->cdata->subject;
$priority = $ticket->cdata->priority;

// All fields as array:
$fields = CdataApproach::getCustomFields($ticketId);
// ['subject' => '...', 'priority' => '2', 'transid' => '', ...]
```

**Query pattern:** 2 queries (ticket + cdata) on cold; 0 extra if already eager-loaded.

### Approach B — JSON Accessor with Label Mapping (`JsonAccessorApproach`)

Reads `ticket__cdata` for values, and queries `form_field` once to build a `column → label` map. The map is cached. Returns results keyed by human-readable label.

```php
$fields = JsonAccessorApproach::getCustomFields($ticketId);
// ['Subject' => '...', 'Priority' => '2', 'Transaction Number' => '', ...]
```

**Query pattern:** 1 query (cdata only) on warm cache; 2 queries (cdata + form_field) on cold cache.

### Approach C — Full EAV Query (`EavApproach`)

Queries `form_entry JOIN form_entry_values` directly, bypassing `__cdata`. Field metadata cached from `form_field`. Returns results keyed by label.

```php
$fields = EavApproach::getCustomFields($ticketId);
// ['Subject' => '...', 'Priority' => 'Normal']
```

**Query pattern:** 1 JOIN query on warm cache; 2 queries (join + form_field) on cold cache.

---

## Benchmark Results

Measured on a real osTicket database. Ticket ID: 159. 100 iterations per approach.

### Single-Ticket Performance

| Approach                              | Total (s) | Avg/iter (ms) | DB Queries/iter | Fields returned |
|---------------------------------------|-----------|---------------|-----------------|-----------------|
| A: CdataApproach (direct __cdata)     | 0.0567    | 0.567         | 2               | 8               |
| B: JsonAccessor (cold cache per iter) | 0.0872    | 0.872         | 2               | 8               |
| B: JsonAccessor (warm cache)          | 0.0125    | **0.125**     | 1               | 8               |
| C: EavApproach (cold cache per iter)  | 0.1148    | 1.148         | 2               | 2               |
| C: EavApproach (warm cache)           | 0.0178    | 0.178         | 1               | 2               |

### Batch Performance (20 iterations, batch size 10)

| Approach                          | Total (s) | Avg/batch (ms) |
|-----------------------------------|-----------|----------------|
| A: CdataApproach (batch)          | 0.0152    | 0.762          |
| B: JsonAccessorApproach (batch)   | 0.0036    | **0.179**      |
| C: EavApproach (batch)            | 0.0069    | 0.344          |

### Sample Output Comparison

```
A (cdata column names):
  subject:  "Buổi chiều, ông Putin vào Lăng viếng..."
  priority: "2"
  transid:  ""
  transdt:  "0"
  ...

B (human-readable labels):
  Subject:              "Buổi chiều, ông Putin vào Lăng viếng..."
  Priority:             "2"
  Transaction Number:   ""
  Transaction DateTime: "0"
  Payoo eWallet:        ""
  ...

C (EAV labels — incomplete):
  Subject:  "Buổi chiều, ông Putin vào Lăng viếng..."
  Priority: "Normal"     ← value_id resolved to display label
```

> **Key finding:** EAV (Approach C) only returns 2 fields for this ticket, while `__cdata` returns 8. This indicates that most custom fields in this osTicket instance are NOT populated via `form_entry_values` entries — they are only materialized into `__cdata`. The EAV tables appear to hold a subset of field values.

---

## Decision

**Recommended: Approach B (JsonAccessorApproach) as the production strategy.**

### Rationale

1. **Performance:** With a warm cache, Approach B is 4.5× faster than Approach A on single-ticket reads (0.125ms vs 0.567ms). This is because it drops from 2 DB queries to 1 by serving the label map from cache, while Approach A always requires 2 queries (ticket + cdata join).

2. **Human-readable API:** Approach B returns `$fields['Subject']`, `$fields['Priority']` — these keys are stable across deployments and usable in templates, exports, and API responses without a separate lookup. Approach A returns raw column names like `shortdesc`, `callerid`, `bankacc` which require schema knowledge.

3. **Uses the materialized view:** Unlike Approach C (EAV), Approach B still reads from `ticket__cdata` which is optimized, indexed, and maintained by the legacy system. It does not bypass the existing performance optimization.

4. **Field completeness:** Approach C (EAV) returned only 2 out of 8 fields in real data — it cannot be relied on as the sole source. Approach B returns all 8 materialized fields.

5. **Batch efficiency:** Approach B is the fastest in batch mode (0.179ms/batch) because the label map is shared across all tickets in a batch, amortizing its cost to zero.

### When to Use Each Approach

| Use case | Recommended approach |
|----------|---------------------|
| Production read API, ticket display | **B (JsonAccessor, warm cache)** |
| Performance-critical bulk export | **A (Cdata direct)** — skip label mapping overhead |
| Field type/configuration metadata needed | **C (EAV with meta)** via `getCustomFieldsWithMeta()` |
| Debugging / schema exploration | **A** — raw column names are more debuggable |
| Fields not in __cdata (future custom fields) | **C (EAV)** — only option for unmaterialized fields |

---

## Implementation Plan for Task 0.6

Based on this benchmark, Task 0.6 should implement **Approach B** as the default, with **Approach C** as a fallback for fields not present in `__cdata`.

### Suggested Ticket Model Extension

```php
// In App\Models\Ticket — add as Eloquent attribute accessor:
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Prototype\DynamicForms\JsonAccessorApproach;

protected function customFields(): Attribute
{
    return Attribute::get(
        fn () => JsonAccessorApproach::getCustomFields($this->ticket_id) ?? []
    )->shouldCache();
}
```

### Cache Strategy

- Cache store: `file` (default) or `redis` if available
- TTL: 3600 seconds (1 hour) for label map
- Invalidation: call `JsonAccessorApproach::flushCache()` after any `form_field` schema change
- Label map is shared across all tickets — one cache entry per application

---

## Limitations and Future Considerations

1. **__cdata sync dependency:** Approach B depends on osTicket's legacy signal system to keep `ticket__cdata` current. If this sync breaks (e.g., during migration), field values will be stale.

2. **New custom fields:** When osTicket admins add new custom fields, `ticket__cdata` is rebuilt via `rebuildDynamicDataViews()`. The new column will not appear in Approach B until the cache is flushed.

3. **Field name collisions:** If two form fields have the same label, Approach B's label map will only retain the last one. This can be mitigated by using `form_id:label` as the key.

4. **EAV value resolution:** Approach C's `getCustomFields()` returns raw `value` strings. For select/dropdown fields, the display value may require resolving `value_id` against the field's configuration JSON. `getCustomFieldsWithMeta()` returns both.

5. **Non-ticket objects:** The EAV pattern extends to users (`object_type='U'`) and organizations. The prototypes support this via the `$objectType` parameter but `ticket__cdata` is ticket-only.
