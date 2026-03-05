<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('group')) {
            $query->where('group', $request->input('group'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min($request->input('per_page', 200), 1000);
        $items = $query->orderBy('group')->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => PermissionResource::collection($items),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ],
            'message' => '权限列表获取成功',
        ]);
    }
}

