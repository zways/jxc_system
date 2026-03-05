<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WarehousesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::with('store');
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user || $user->store_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        // 按状态筛选
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        // 按类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $warehouses = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => WarehouseResource::collection($warehouses),
                'meta' => [
                    'current_page' => $warehouses->currentPage(),
                    'per_page' => $warehouses->perPage(),
                    'total' => $warehouses->total(),
                    'last_page' => $warehouses->lastPage(),
                ]
            ],
            'message' => '仓库列表获取成功'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $scopedStoreId = $this->isSuperAdmin($request)
                ? $request->input('store_id')
                : $request->user()?->store_id;

            $validatedData = $request->validate([
                'code' => [
                    'required',
                    'string',
                    Rule::unique('warehouses', 'code')->where(function ($q) use ($scopedStoreId) {
                        return $q->where('store_id', $scopedStoreId);
                    }),
                ],
                'name' => 'required|string|max:255',
                'location' => 'nullable|string',
                'manager' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|string|in:normal,frozen,liquid',
                'is_active' => 'boolean',
                'notes' => 'nullable|string',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $warehouse = Warehouse::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new WarehouseResource($warehouse),
                'message' => '仓库创建成功'
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
        $warehouse = Warehouse::with('store')->find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => '仓库不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $warehouse->store_id)) {
            return $this->forbid('无权访问该仓库');
        }

        return response()->json([
            'success' => true,
            'data' => new WarehouseResource($warehouse),
            'message' => '仓库详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => '仓库不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $warehouse->store_id)) {
            return $this->forbid('无权修改该仓库');
        }

        try {
            $scopedStoreId = $this->isSuperAdmin($request)
                ? $request->input('store_id', $warehouse->store_id)
                : $request->user()?->store_id;

            $validatedData = $request->validate([
                'code' => [
                    'sometimes',
                    'string',
                    Rule::unique('warehouses', 'code')
                        ->where(function ($q) use ($scopedStoreId) {
                            return $q->where('store_id', $scopedStoreId);
                        })
                        ->ignore($warehouse->id),
                ],
                'name' => 'sometimes|string|max:255',
                'location' => 'nullable|string',
                'manager' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'type' => 'sometimes|string|in:normal,frozen,liquid',
                'is_active' => 'boolean',
                'notes' => 'nullable|string',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                if (array_key_exists('store_id', $validatedData)) {
                    $validatedData['store_id'] = $userStoreId;
                }
            }

            $warehouse->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new WarehouseResource($warehouse),
                'message' => '仓库更新成功'
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
        $warehouse = Warehouse::find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => '仓库不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $warehouse->store_id)) {
            return $this->forbid('无权删除该仓库');
        }

        $wid = (int)$warehouse->id;
        $refs = [
            [PurchaseOrder::class, '采购单'],
            [SalesOrder::class, '销售单'],
            [InventoryTransaction::class, '库存流水'],
            [User::class, '用户'],
        ];
        foreach ($refs as [$model, $label]) {
            if ($model::where('warehouse_id', $wid)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "该仓库下仍有{$label}记录，不可删除"
                ], 400);
            }
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => '仓库删除成功'
        ]);
    }
}
