<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CannedResponseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $deptId = $request->filled('dept_id') ? $request->integer('dept_id') : null;
        $q = $request->string('q')->trim()->value();

        $query = CannedResponse::query()
            ->where('isactive', 1)
            ->where(function ($sub) use ($deptId): void {
                $sub->whereNull('dept_id');
                if ($deptId !== null) {
                    $sub->orWhere('dept_id', $deptId);
                }
            })
            ->orderBy('title')
            ->limit(10);

        if ($q !== '') {
            $query->where('title', 'like', "%{$q}%");
        }

        return response()->json(
            $query->get()->map(fn (CannedResponse $cr) => [
                'id' => $cr->canned_id,
                'title' => $cr->title,
                'response' => $cr->response,
            ])
        );
    }
}
