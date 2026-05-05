<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Models\Draft;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final class DraftService
{
    public function find(Staff $staff, string $namespace): ?Draft
    {
        return Draft::on('legacy')
            ->where('staff_id', $staff->staff_id)
            ->where('namespace', $namespace)
            ->first();
    }

    public function upsert(Staff $staff, string $namespace, string $body): Draft
    {
        $now = now()->toDateTimeString();

        DB::connection('legacy')->table('draft')->updateOrInsert(
            [
                'staff_id' => $staff->staff_id,
                'namespace' => $namespace,
            ],
            function (bool $exists) use ($body, $now): array {
                $values = [
                    'body' => $body,
                    'updated' => $now,
                ];

                if (! $exists) {
                    $values['created'] = $now;
                }

                return $values;
            },
        );

        return $this->find($staff, $namespace);
    }

    public function discard(Staff $staff, string $namespace): void
    {
        DB::connection('legacy')->table('draft')
            ->where('staff_id', $staff->staff_id)
            ->where('namespace', $namespace)
            ->delete();
    }
}
