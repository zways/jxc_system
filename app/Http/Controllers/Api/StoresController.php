<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StoresController extends Controller
{
    private function isSelfOrDescendant(int $storeId, int $parentId): bool
    {
        $currentId = $parentId;
        $guard = 0;

        while ($currentId && $guard < 100) {
            if ($currentId === $storeId) {
                return true;
            }
            $currentId = (int)Store::query()
                ->whereKey($currentId)
                ->value('parent_store_id');
            $guard++;
        }

        return false;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Store::with(['parent']);
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user || $user->store_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('id', $user->store_id);
            }
        }

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('store_code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('manager', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        // 按门店类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // 按激活状态筛选
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        // 按父级门店筛选
        if ($request->filled('parent_store_id')) {
            $query->where('parent_store_id', $request->input('parent_store_id'));
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $stores = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => StoreResource::collection($stores),
                'meta' => [
                    'current_page' => $stores->currentPage(),
                    'per_page' => $stores->perPage(),
                    'total' => $stores->total(),
                    'last_page' => $stores->lastPage(),
                ]
            ],
            'message' => '门店列表获取成功'
        ]);
    }

    /**
     * 生成门店编号
     */
    private function generateStoreCode(): string
    {
        $prefix = 'STORE';
        $lastStore = Store::where('store_code', 'like', "{$prefix}%")
            ->orderBy('store_code', 'desc')
            ->first();
        
        if ($lastStore) {
            $lastNumber = intval(substr($lastStore->store_code, 5));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return "{$prefix}{$newNumber}";
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbid('仅超级管理员可创建门店');
        }

        try {
            $validatedData = $request->validate([
                'store_code' => 'nullable|string|unique:stores,store_code',
                'name' => 'required|string|max:255',
                'manager' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'type' => 'nullable|string|in:retail,wholesale,online,hybrid',
                'is_active' => 'nullable|boolean',
                'parent_store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
            ]);

            // 自动生成门店编号
            if (empty($validatedData['store_code'])) {
                $validatedData['store_code'] = $this->generateStoreCode();
            }
            // 设置默认值
            $validatedData['type'] = $validatedData['type'] ?? 'retail';
            $validatedData['is_active'] = $validatedData['is_active'] ?? true;

            $store = Store::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new StoreResource($store),
                'message' => '门店创建成功'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = Store::with(['parent', 'children'])->find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => '门店不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, (int)$store->id)) {
            return $this->forbid('无权访问该门店');
        }

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
            'message' => '门店详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => '门店不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request)) {
            return $this->forbid('仅超级管理员可修改门店');
        }

        try {
            $validatedData = $request->validate([
                'store_code' => 'sometimes|string|unique:stores,store_code,' . $id,
                'name' => 'sometimes|string|max:255',
                'manager' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'type' => 'sometimes|string|in:retail,wholesale,online,hybrid',
                'is_active' => 'sometimes|boolean',
                'parent_store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
            ]);

            if (array_key_exists('parent_store_id', $validatedData) && $validatedData['parent_store_id'] !== null) {
                $parentId = (int)$validatedData['parent_store_id'];
                if ($this->isSelfOrDescendant((int)$id, $parentId)) {
                    throw ValidationException::withMessages([
                        'parent_store_id' => ['上级门店不能是自身或其子级'],
                    ]);
                }
            }

            $store->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new StoreResource($store),
                'message' => '门店更新成功'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => '门店不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request)) {
            return $this->forbid('仅超级管理员可删除门店');
        }

        // 检查是否有子门店关联此门店
        if ($store->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => '该门店存在子门店，不可删除'
            ], 400);
        }

        // 检查是否有用户、仓库、部门等关联此门店
        $storeId = (int)$store->id;
        $relations = [
            [\App\Models\User::class, '用户'],
            [\App\Models\Warehouse::class, '仓库'],
            [\App\Models\Department::class, '部门'],
            [\App\Models\Role::class, '角色'],
            [\App\Models\Customer::class, '客户'],
            [\App\Models\Supplier::class, '供应商'],
            [\App\Models\Product::class, '商品'],
        ];
        foreach ($relations as [$modelClass, $label]) {
            if ($modelClass::where('store_id', $storeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "该门店下仍有{$label}记录，不可删除"
                ], 400);
            }
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => '门店删除成功'
        ]);
    }
}
