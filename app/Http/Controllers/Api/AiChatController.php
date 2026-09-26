<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AccessMap;
use App\Services\HomeAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiChatController extends Controller
{
    public function __construct(private readonly HomeAiService $ai) {}

    public function chat(Request $request): JsonResponse
    {
        abort_unless(AccessMap::allows($request->user(), 'ai', 'use'), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        $key = 'ai-history-'.$request->user()->id;
        $history = Cache::get($key, []);
        $answer = $this->ai->answer($request->user(), $data['message']);
        $history[] = ['role' => 'user', 'content' => $data['message'], 'created_at' => now()->toIso8601String()];
        $history[] = ['role' => 'assistant', 'content' => $answer['message'], 'created_at' => now()->toIso8601String()];
        Cache::put($key, array_slice($history, -20), now()->addDays(7));

        return response()->json(['message' => $answer['message'], 'context' => $answer['context'], 'history' => $history]);
    }

    public function history(Request $request): JsonResponse
    {
        abort_unless(AccessMap::allows($request->user(), 'ai', 'use'), 403);
        return response()->json(['data' => Cache::get('ai-history-'.$request->user()->id, [])]);
    }

    public function clear(Request $request): JsonResponse
    {
        abort_unless(AccessMap::allows($request->user(), 'ai', 'use'), 403);
        Cache::forget('ai-history-'.$request->user()->id);
        return response()->json(['message' => 'AI conversation cleared.']);
    }
}
