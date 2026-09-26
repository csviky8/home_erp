<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()->where('user_id', $request->user()->id)->latest()->paginate(min($request->integer('per_page', 20), 50));
        return response()->json(['data' => $notifications->items(), 'meta' => ['total' => $notifications->total(), 'unread' => Notification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count()]]);
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $item = Notification::query()->where('user_id', $request->user()->id)->whereKey($notification)->firstOrFail();
        $item->update(['read_at' => now()]);
        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        Notification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
