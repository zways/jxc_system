<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Rules\StrongPassword;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    /**
     * 企业（企业）自助注册
     *
     * 流程：
     * 1. 创建 Store 记录（代表企业企业）
     * 2. 创建企业管理员角色 + 分配全部权限
     * 3. 创建默认部门
     * 4. 创建默认仓库
     * 5. 创建管理员用户
     * 6. 返回登录 Token
     */
    public function register(Request $request): JsonResponse
    {
        try {
            // 兼容简写字段名：email -> admin_email, password -> admin_password, phone -> admin_phone
            if (!$request->has('admin_email') && $request->has('email')) {
                $request->merge(['admin_email' => $request->input('email')]);
            }
            if (!$request->has('admin_password') && $request->has('password')) {
                $request->merge(['admin_password' => $request->input('password')]);
            }
            if (!$request->has('admin_phone') && $request->has('phone')) {
                $request->merge(['admin_phone' => $request->input('phone')]);
            }
            if (!$request->has('admin_name') && $request->has('name')) {
                $request->merge(['admin_name' => $request->input('name')]);
            }

            $validated = $request->validate([
                // 企业信息
                'company_name' => 'required|string|max:100',
                'industry' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:255',
                'business_license' => 'nullable|string|max:100',
                // 管理员信息
                'admin_name' => 'required|string|max:50',
                'admin_email' => 'required|email|max:100',
                'admin_phone' => 'nullable|string|max:20',
                'admin_password' => ['required', 'string', new StrongPassword],
                'device_name' => 'nullable|string|max:100',
            ]);

            // 检查邮箱是否已注册
            $existingUser = User::withoutGlobalScope(TenantScope::class)
                ->where('email', $validated['admin_email'])
                ->first();
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => '该邮箱已注册',
                ], 422);
            }

            $result = DB::transaction(function () use ($validated) {
                // 1. 创建企业（门店/企业）
                $storeCode = 'T' . date('Ymd') . Str::upper(Str::random(4));
                $store = Store::create([
                    'store_code' => $storeCode,
                    'name' => $validated['company_name'],
                    'manager' => $validated['admin_name'],
                    'phone' => $validated['admin_phone'] ?? null,
                    'contact_email' => $validated['admin_email'],
                    'address' => $validated['address'] ?? null,
                    'business_license' => $validated['business_license'] ?? null,
                    'type' => 'retail',
                    'industry' => $validated['industry'] ?? null,
                    'is_active' => true,
                    'plan' => 'free',
                    'max_users' => 5,
                    'expires_at' => null, // 免费版无到期时间
                    'is_tenant' => true,
                ]);

                // 2. 创建企业管理员角色（全部权限）
                $role = Role::withoutGlobalScope(TenantScope::class)->create([
                    'store_id' => $store->id,
                    'name' => '管理员',
                    'code' => 'tenant_admin',
                    'description' => '企业管理员，拥有本企业全部权限',
                    'is_active' => true,
                ]);

                // 分配全部权限给管理员角色
                $allPermissions = Permission::where('is_active', true)->pluck('id');
                $role->permissions()->sync($allPermissions);

                // 3. 创建默认部门
                Department::withoutGlobalScope(TenantScope::class)->create([
                    'store_id' => $store->id,
                    'name' => '管理部',
                    'code' => 'admin',
                    'description' => '默认管理部门',
                    'is_active' => true,
                ]);

                // 4. 创建默认仓库
                Warehouse::withoutGlobalScope(TenantScope::class)->create([
                    'store_id' => $store->id,
                    'code' => 'WH001',
                    'name' => '默认仓库',
                    'location' => $validated['address'] ?? null,
                    'manager' => $validated['admin_name'],
                    'type' => 'normal',
                    'is_active' => true,
                ]);

                // 5. 创建管理员用户
                $username = 'admin_' . strtolower(Str::random(6));
                $user = User::withoutGlobalScope(TenantScope::class)->create([
                    'username' => $username,
                    'real_name' => $validated['admin_name'],
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'phone' => $validated['admin_phone'] ?? null,
                    'password' => Hash::make($validated['admin_password']),
                    'status' => 'enabled',
                    'role_id' => $role->id,
                    'department_id' => Department::withoutGlobalScope(TenantScope::class)
                        ->where('store_id', $store->id)->first()?->id,
                    'store_id' => $store->id,
                    'warehouse_id' => Warehouse::withoutGlobalScope(TenantScope::class)
                        ->where('store_id', $store->id)->first()?->id,
                ]);

                // 6. 创建登录 Token
                $plainToken = Str::random(64);
                $tokenHash = hash('sha256', $plainToken);
                ApiToken::create([
                    'user_id' => $user->id,
                    'name' => 'web',
                    'token_hash' => $tokenHash,
                    'abilities' => null,
                    'last_used_at' => now(),
                    'expires_at' => null,
                ]);

                $user->forceFill(['last_login_at' => now()])->save();

                return compact('store', 'user', 'role', 'plainToken');
            });

            // 加载关联
            $result['user']->load(['role.permissions', 'department', 'store', 'warehouse']);
            $permissions = $result['user']->role?->permissions?->pluck('name')->values() ?? collect();

            $this->audit($request, 'create', $result['store'], null, null, '企业注册: ' . $validated['company_name']);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $result['plainToken'],
                    'user' => (new UserResource($result['user']))->resolve(),
                    'permissions' => $permissions,
                    'tenant' => [
                        'id' => $result['store']->id,
                        'name' => $result['store']->name,
                        'store_code' => $result['store']->store_code,
                        'plan' => $result['store']->plan,
                        'max_users' => $result['store']->max_users,
                    ],
                ],
                'message' => '企业注册成功',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '输入验证失败',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '注册失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取当前企业信息（需要登录）
     *
     * 超级管理员：返回平台概览（所有企业汇总统计）
     * 普通用户：返回所属企业详情
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // ── 超级管理员：返回平台级概览 ──
        if ($this->isSuperAdmin($request)) {
            return $this->platformOverview();
        }

        // ── 普通用户：返回所属企业信息 ──
        if (!$user || !$user->store_id) {
            return response()->json([
                'success' => false,
                'message' => '未绑定企业',
            ], 404);
        }

        $store = Store::find($user->store_id);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => '企业不存在',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $store->id,
                'store_code' => $store->store_code,
                'name' => $store->name,
                'manager' => $store->manager,
                'phone' => $store->phone,
                'contact_email' => $store->contact_email,
                'address' => $store->address,
                'business_license' => $store->business_license,
                'industry' => $store->industry,
                'type' => $store->type,
                'plan' => $store->plan,
                'max_users' => $store->max_users,
                'user_count' => $store->users()->count(),
                'expires_at' => $store->expires_at,
                'is_expired' => $store->isExpired(),
                'created_at' => $store->created_at,
            ],
            'message' => '操作成功',
        ]);
    }

    /**
     * 超级管理员平台概览：汇总所有企业统计数据
     */
    private function platformOverview(): JsonResponse
    {
        $allTenants = Store::where('is_tenant', true)->get();
        $totalTenants = $allTenants->count();
        $activeTenants = $allTenants->where('is_active', true)->count();
        $expiredTenants = $allTenants->filter(fn ($s) => $s->isExpired())->count();

        // 套餐分布
        $planDistribution = $allTenants->groupBy('plan')->map->count();

        // 总用户数
        $totalUsers = User::withoutGlobalScope(\App\Scopes\TenantScope::class)->count();

        // 最近注册的企业（5条）
        $recentTenants = Store::where('is_tenant', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'store_code', 'name', 'plan', 'is_active', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'is_platform' => true,   // 前端据此判断展示平台概览 UI
                'name' => '平台管理中心',
                'store_code' => 'PLATFORM',
                'plan' => 'enterprise',
                'manager' => '超级管理员',
                'stats' => [
                    'total_tenants' => $totalTenants,
                    'active_tenants' => $activeTenants,
                    'expired_tenants' => $expiredTenants,
                    'total_users' => $totalUsers,
                ],
                'plan_distribution' => $planDistribution,
                'recent_tenants' => $recentTenants,
                // 兼容前端套餐卡片
                'max_users' => $totalUsers,
                'user_count' => $totalUsers,
                'expires_at' => null,
                'is_expired' => false,
            ],
            'message' => '操作成功',
        ]);
    }

    /**
     * 更新当前企业信息（需要登录 + 管理员权限）
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->store_id) {
            return $this->forbid('未绑定企业');
        }

        // 仅企业管理员或超级管理员可修改
        $roleCode = $user->role?->code ?? '';
        if (!in_array($roleCode, ['super_admin', 'tenant_admin'])) {
            return $this->forbid('仅管理员可修改企业信息');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'manager' => 'sometimes|string|max:50',
            'phone' => 'sometimes|nullable|string|max:20',
            'contact_email' => 'sometimes|nullable|email|max:100',
            'address' => 'sometimes|nullable|string|max:255',
            'business_license' => 'sometimes|nullable|string|max:100',
            'industry' => 'sometimes|nullable|string|max:50',
        ]);

        $store = Store::findOrFail($user->store_id);
        $originalAttributes = $store->getAttributes();
        $store->update($validated);

        $this->auditUpdated($request, $store, $originalAttributes, '更新企业信息');

        return response()->json([
            'success' => true,
            'data' => $store->fresh(),
            'message' => '企业信息已更新',
        ]);
    }

    /**
     * 超级管理员：列出所有企业
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbid('仅超级管理员可查看所有企业');
        }

        $query = Store::query()->where('is_tenant', true);

        // 搜索
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('store_code', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // 筛选
        if ($plan = $request->query('plan')) {
            $query->where('plan', $plan);
        }
        if ($request->query('is_active') !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderByDesc('created_at');
        $perPage = min((int) ($request->query('per_page', 15)), 100);
        $tenants = $query->paginate($perPage);

        // 附加用户数
        $tenants->getCollection()->transform(function ($tenant) {
            $tenant->user_count = $tenant->users()->count();
            return $tenant;
        });

        return response()->json([
            'success' => true,
            'data' => $tenants,
            'message' => '操作成功',
        ]);
    }

    /**
     * 超级管理员：手动为企业更新订阅（线下付款后操作）
     *
     * 可更新：套餐(plan)、到期时间(expires_at)、最大用户数(max_users)
     */
    public function updateSubscription(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->forbid('仅超级管理员可为企业办理订阅/续费');
        }

        $store = Store::where('id', $id)->where('is_tenant', true)->first();
        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => '企业不存在或不是企业',
            ], 404);
        }

        $validated = $request->validate([
            'plan' => 'sometimes|string|in:free,basic,pro,enterprise',
            'expires_at' => 'sometimes|nullable|date',
            'max_users' => 'sometimes|integer|min:1|max:9999',
        ]);

        $original = $store->getAttributes();
        $updates = array_intersect_key($validated, array_flip(['plan', 'expires_at', 'max_users']));
        if (empty($updates)) {
            return response()->json([
                'success' => false,
                'message' => '请至少提交 plan、expires_at 或 max_users 之一',
            ], 422);
        }

        // 若改套餐，可同步该套餐的默认 max_users（未传时）
        $plans = Store::availablePlans();
        if (isset($updates['plan']) && ! array_key_exists('max_users', $updates) && isset($plans[$updates['plan']]['max_users'])) {
            $updates['max_users'] = $plans[$updates['plan']]['max_users'];
        }

        $store->update($updates);

        $this->auditUpdated($request, $store, $original, '管理员手动更新企业订阅（线下续费）');

        return response()->json([
            'success' => true,
            'data' => $store->fresh(),
            'message' => '订阅已更新',
        ]);
    }
}
