<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Staff;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AutocompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Staff $currentStaff */
        $currentStaff = $request->user('staff');
        $q = $request->string('q')->trim()->value();

        $query = Staff::where('isactive', 1)
            ->where('staff_id', '!=', $currentStaff->staff_id)
            ->orderBy('firstname')
            ->limit(10);

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('firstname', 'like', "%{$q}%")
                    ->orWhere('lastname', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->get()->map(fn (Staff $s) => [
                'id' => $s->staff_id,
                'name' => trim("{$s->firstname} {$s->lastname}"),
                'username' => $s->username,
            ])
        );
    }
}
