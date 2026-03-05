<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['role', 'department', 'store', 'warehouse']);
        $this->scopeUsersByDepartmentOrSelf($request, $query);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'LIKE', "%{$search}%")
                    ->orWhere('real_name', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('employee_code', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => UserResource::collection($items),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ],
            'message' => '用户列表获取成功',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:users,username',
                'real_name' => 'nullable|string|max:255',
                'name' => 'nullable|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:50',
                'password' => ['required', 'string', new StrongPassword],
                'status' => ['nullable', 'string', Rule::in(['enabled', 'disabled'])],
                'employee_code' => 'nullable|string|max:255',
                'role_id' => 'nullable|exists:roles,id',
                'department_id' => 'nullable|exists:departments,id',
                'store_id' => 'nullable|exists:stores,id',
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $currentUser = $request->user();
                $currentDepartmentId = $currentUser?->department_id;
                $currentStoreId = $currentUser?->store_id;
                $currentWarehouseId = $currentUser?->warehouse_id;
                if (array_key_exists('department_id', $validated)) {
                    if ($currentDepartmentId === null || $validated['department_id'] !== $currentDepartmentId) {
                        return $this->forbid('无权设置该部门');
                    }
                }
                if (array_key_exists('store_id', $validated)) {
                    if ($currentStoreId === null || $validated['store_id'] !== $currentStoreId) {
                        return $this->forbid('无权设置该门店');
                    }
                }
                if (array_key_exists('warehouse_id', $validated)) {
                    if ($currentWarehouseId === null || $validated['warehouse_id'] !== $currentWarehouseId) {
                        return $this->forbid('无权设置该仓库');
                    }
                }
                if (array_key_exists('role_id', $validated)) {
                    $role = Role::find($validated['role_id']);
                    if ($role && $role->code === 'super_admin') {
                        return $this->forbid('无权分配超级管理员角色');
                    }
                    if ($role && $role->store_id !== null && $role->store_id !== $currentStoreId) {
                        return $this->forbid('无权分配该门店角色');
                    }
                    if ($role && $role->store_id === null) {
                        return $this->forbid('无权分配全局角色');
                    }
                }
            }

            if (array_key_exists('role_id', $validated)) {
                $role = Role::find($validated['role_id']);
                if ($role && $role->store_id !== null) {
                    if (!array_key_exists('store_id', $validated) || $validated['store_id'] === null) {
                        $validated['store_id'] = $role->store_id;
                    }
                    $targetStoreId = $validated['store_id'];
                    if ($targetStoreId !== $role->store_id) {
                        return $this->forbid('角色门店与用户门店不匹配');
                    }
                }
            }

            $user = User::create([
                'username' => $validated['username'],
                'real_name' => $validated['real_name'] ?? null,
                'name' => $validated['name'] ?? ($validated['real_name'] ?? $validated['username']),
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'] ?? 'enabled',
                'employee_code' => $validated['employee_code'] ?? null,
                'role_id' => $validated['role_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'store_id' => $validated['store_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
            ]);

            $user->load(['role', 'department', 'store', 'warehouse']);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => '用户创建成功',
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
        $user = User::with(['role', 'department', 'store', 'warehouse'])->find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户不存在',
            ], 404);
        }

        if (!$this->isSuperAdmin($request) && ($user->role?->code ?? null) === 'super_admin') {
            return $this->forbid('无权访问超级管理员');
        }
        if ($resp = $this->ensureSameDepartmentOrSelfOrSuperAdmin(
            $request,
            $user->id,
            $user->department_id,
            $user->store_id,
            $user->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => '用户详情获取成功',
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户不存在',
            ], 404);
        }

        $user->loadMissing('role');
        if (!$this->isSuperAdmin($request) && ($user->role?->code ?? null) === 'super_admin') {
            return $this->forbid('无权修改超级管理员');
        }
        if ($resp = $this->ensureSameDepartmentOrSelfOrSuperAdmin(
            $request,
            $user->id,
            $user->department_id,
            $user->store_id,
            $user->warehouse_id
        )) {
            return $resp;
        }

        try {
            $validated = $request->validate([
                'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
                'real_name' => 'nullable|string|max:255',
                'name' => 'nullable|string|max:255',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:50',
                'password' => ['nullable', 'string', new StrongPassword],
                'status' => ['nullable', 'string', Rule::in(['enabled', 'disabled'])],
                'employee_code' => 'nullable|string|max:255',
                'role_id' => 'nullable|exists:roles,id',
                'department_id' => 'nullable|exists:departments,id',
                'store_id' => 'nullable|exists:stores,id',
                'warehouse_id' => 'nullable|exists:warehouses,id',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $currentUser = $request->user();
                $currentDepartmentId = $currentUser?->department_id;
                $currentStoreId = $currentUser?->store_id;
                $currentWarehouseId = $currentUser?->warehouse_id;
                if (array_key_exists('department_id', $validated)) {
                    if ($currentDepartmentId === null || $validated['department_id'] !== $currentDepartmentId) {
                        return $this->forbid('无权设置该部门');
                    }
                }
                if (array_key_exists('store_id', $validated)) {
                    if ($currentStoreId === null || $validated['store_id'] !== $currentStoreId) {
                        return $this->forbid('无权设置该门店');
                    }
                }
                if (array_key_exists('warehouse_id', $validated)) {
                    if ($currentWarehouseId === null || $validated['warehouse_id'] !== $currentWarehouseId) {
                        return $this->forbid('无权设置该仓库');
                    }
                }
                if (array_key_exists('role_id', $validated)) {
                    $role = Role::find($validated['role_id']);
                    if ($role && $role->code === 'super_admin') {
                        return $this->forbid('无权分配超级管理员角色');
                    }
                    if ($role && $role->store_id !== null && $role->store_id !== $currentStoreId) {
                        return $this->forbid('无权分配该门店角色');
                    }
                    if ($role && $role->store_id === null) {
                        return $this->forbid('无权分配全局角色');
                    }
                }
            }

            $targetRole = null;
            if (array_key_exists('role_id', $validated)) {
                $targetRole = Role::find($validated['role_id']);
            } else {
                $user->loadMissing('role');
                $targetRole = $user->role;
            }
            if ($targetRole && $targetRole->store_id !== null) {
                $targetStoreId = array_key_exists('store_id', $validated) ? $validated['store_id'] : $user->store_id;
                if ($targetStoreId !== $targetRole->store_id) {
                    return $this->forbid('角色门店与用户门店不匹配');
                }
            }

            if (array_key_exists('password', $validated) && $validated['password']) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            $user->update($validated);
            $user->load(['role', 'department', 'store', 'warehouse']);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => '用户更新成功',
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
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户不存在',
            ], 404);
        }

        $user->loadMissing('role');
        if (!$this->isSuperAdmin($request) && ($user->role?->code ?? null) === 'super_admin') {
            return $this->forbid('无权删除超级管理员');
        }
        if ($resp = $this->ensureSameDepartmentOrSelfOrSuperAdmin(
            $request,
            $user->id,
            $user->department_id,
            $user->store_id,
            $user->warehouse_id
        )) {
            return $resp;
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => '用户删除成功',
        ]);
    }
}

