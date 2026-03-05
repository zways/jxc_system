<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    /**
     * 获取当前用户的通知列表
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '未登录'], 401);
        }

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('only_unread')) {
            $onlyUnread = filter_var($request->input('only_unread'), FILTER_VALIDATE_BOOLEAN);
            if ($onlyUnread) {
                $query->where('is_read', false);
            }
        }

        $perPage = min($request->input('per_page', 20), 100);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
            ],
            'message' => '通知列表获取成功',
        ]);
    }

    /**
     * 标记单条通知为已读
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '未登录'], 401);
        }

        $notification = Notification::where('user_id', $user->id)->find($id);
        if (!$notification) {
            return response()->json(['success' => false, 'message' => '通知不存在'], 404);
        }

        if (!$notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json([
            'success' => true,
            'data' => new NotificationResource($notification),
            'message' => '通知已标记为已读',
        ]);
    }

    /**
     * 将当前用户的所有通知标记为已读
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '未登录'], 401);
        }

        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '所有通知已标记为已读',
        ]);
    }
}

