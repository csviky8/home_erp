<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function __construct(private readonly LookupService $lookups) {}

    public function __invoke(Request $request, string $resource, ?string $type = null): JsonResponse
    {
        return response()->json(['data' => $this->lookups->get($request->user(), $resource, $type)]);
    }
}

