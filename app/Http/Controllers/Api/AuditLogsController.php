<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogsController extends Controller
{
    /**
     * 查询审计日志列表（仅超级管理员可查所有，普通用户仅本门店）
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user || $user->store_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        // 按操作类型筛选
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // 按操作人筛选
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // 按模型类型筛选
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->input('model_type'));
        }

        // 按对象ID筛选
        if ($request->filled('model_id')) {
            $query->where('model_id', $request->input('model_id'));
        }

        // 按门店筛选（超管可指定）
        if ($request->filled('store_id') && $this->isSuperAdmin($request)) {
            $query->where('store_id', $request->input('store_id'));
        }

        // 按日期范围筛选
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->input('start_date') . ' 00:00:00',
                $request->input('end_date') . ' 23:59:59',
            ]);
        }

        // 关键词搜索
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('model_label', 'LIKE', "%{$search}%")
                    ->orWhere('user_name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $perPage = min($request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => AuditLogResource::collection($logs),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ],
            'message' => '操作日志列表获取成功',
        ]);
    }

    /**
     * 查看单条审计日志详情
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $log = AuditLog::find($id);
        if (!$log) {
            return response()->json(['success' => false, 'message' => '操作日志不存在'], 404);
        }

        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $log->store_id)) {
            return $this->forbid('无权查看该日志');
        }

        return response()->json([
            'success' => true,
            'data' => new AuditLogResource($log),
            'message' => '操作日志详情获取成功',
        ]);
    }
}
