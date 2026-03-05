<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RolesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('store');
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user || $user->store_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => RoleResource::collection($items),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ],
            'message' => '角色列表获取成功',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $scopedStoreId = $this->isSuperAdmin($request)
                ? $request->input('store_id')
                : $request->user()?->store_id;

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('roles', 'code')->where(function ($q) use ($scopedStoreId) {
                        return $q->where('store_id', $scopedStoreId);
                    }),
                ],
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (!$this->isSuperAdmin($request) && ($validated['code'] ?? null) === 'super_admin') {
                return $this->forbid('无权创建超级管理员角色');
            }
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validated) && $validated['store_id'] !== $userStoreId) {
                    return $this->forbid('无权设置该门店');
                }
                $validated['store_id'] = $userStoreId;
            }
            $validated['store_id'] = $validated['store_id'] ?? ($request->user()?->store_id ?? null);

            $role = Role::create($validated);

            return response()->json([
                'success' => true,
                'data' => new RoleResource($role),
                'message' => '角色创建成功',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $role = Role::with('store')->find($id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => '角色不存在',
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $role->store_id)) {
            return $this->forbid('无权访问该角色');
        }

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
            'message' => '角色详情获取成功',
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => '角色不存在',
            ], 404);
        }

        if (!$this->isSuperAdmin($request) && $role->code === 'super_admin') {
            return $this->forbid('无权修改超级管理员角色');
        }

        try {
            $scopedStoreId = $this->isSuperAdmin($request)
                ? $request->input('store_id', $role->store_id)
                : $request->user()?->store_id;

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'code' => [
                    'sometimes',
                    'string',
                    'max:255',
                    Rule::unique('roles', 'code')
                        ->where(function ($q) use ($scopedStoreId) {
                            return $q->where('store_id', $scopedStoreId);
                        })
                        ->ignore($role->id),
                ],
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (!$this->isSuperAdmin($request) && ($validated['code'] ?? null) === 'super_admin') {
                return $this->forbid('无权设置超级管理员角色编码');
            }
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validated) && $validated['store_id'] !== $userStoreId) {
                    return $this->forbid('无权设置该门店');
                }
                if (array_key_exists('store_id', $validated)) {
                    $validated['store_id'] = $userStoreId;
                }
            }

            $role->update($validated);

            return response()->json([
                'success' => true,
                'data' => new RoleResource($role),
                'message' => '角色更新成功',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => '角色不存在',
            ], 404);
        }

        if (!$this->isSuperAdmin($request) && $role->code === 'super_admin') {
            return $this->forbid('无权删除超级管理员角色');
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $role->store_id)) {
            return $this->forbid('无权删除该角色');
        }

        if (User::where('role_id', (int)$role->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => '该角色下仍有用户，不可删除',
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => '角色删除成功',
        ]);
    }

    public function permissions(Request $request, Role $role): JsonResponse
    {
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $role->store_id)) {
            return $this->forbid('无权访问该角色权限');
        }
        $role->loadMissing('permissions:id,name,title,group,is_active');

        return response()->json([
            'success' => true,
            'data' => [
                'role_id' => $role->id,
                'permission_ids' => $role->permissions->pluck('id')->values(),
                'permissions' => $role->permissions,
            ],
            'message' => '角色权限获取成功',
        ]);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        // 超级管理员角色权限不允许任何人修改（包括超管自己），防止误操作导致前端权限丢失
        if ($role->code === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => '超级管理员角色拥有全部权限，不允许修改',
            ], 403);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $role->store_id)) {
            return $this->forbid('无权修改该角色权限');
        }

        try {
            $validated = $request->validate([
                'permission_ids' => 'array',
                'permission_ids.*' => 'integer|exists:permissions,id',
                'permission_names' => 'array',
                'permission_names.*' => 'string|exists:permissions,name',
            ]);

            $ids = $validated['permission_ids'] ?? null;
            if ($ids === null && array_key_exists('permission_names', $validated)) {
                $ids = Permission::query()
                    ->whereIn('name', $validated['permission_names'] ?? [])
                    ->pluck('id')
                    ->all();
            }
            $ids = $ids ?? [];

            $role->permissions()->sync($ids);

            // 权限同步后清除该角色下所有用户的权限缓存
            PermissionCacheService::flushRole($role->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'role_id' => $role->id,
                    'permission_ids' => array_values($ids),
                ],
                'message' => '角色权限更新成功',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}

