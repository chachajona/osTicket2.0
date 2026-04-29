<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Services\Scp\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function index(Request $request): Response
    {
        $query = (string) $request->query('q', '');

        return Inertia::render('Scp/Search/Index', [
            'query' => $query,
            'results' => $this->search->tickets($query),
        ]);
    }
}
