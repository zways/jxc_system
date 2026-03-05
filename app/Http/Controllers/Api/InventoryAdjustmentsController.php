<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryAdjustmentResource;
use App\Models\InventoryAdjustment;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentsController extends Controller
{
    /**
     * 生成库存流水号（与 InventoryTransactionsController 一致）
     */
    private function generateInventoryTransactionNumber(int $offset = 0): string
    {
        $prefix = 'IT';
        $date = date('Ymd');
        $last = InventoryTransaction::withoutGlobalScopes()->withTrashed()->where('transaction_number', 'like', "{$prefix}{$date}%")
            ->orderBy('transaction_number', 'desc')
            ->first();

        $baseNumber = 1;
        if ($last) {
            $baseNumber = intval(substr($last->transaction_number, -4)) + 1;
        }
        $newNumber = str_pad($baseNumber + $offset, 4, '0', STR_PAD_LEFT);

        return "{$prefix}{$date}{$newNumber}";
    }

    /**
     * 库存调整闭环：创建/更新对应的库存流水（transaction_type=adjust）。
     *
     * 说明：
     * - increase => quantity 为正；decrease => quantity 为负
     * - reference_type/reference_id 用于幂等与追溯
     */
    private function upsertInventoryTransactionForAdjustment(InventoryAdjustment $adjustment, int $userId): void
    {
        $product = $adjustment->relationLoaded('product')
            ? $adjustment->product
            : Product::find($adjustment->product_id);

        $qty = (float)$adjustment->quantity;
        if ($adjustment->adjustment_type === 'decrease') {
            $qty = -abs($qty);
        } else {
            $qty = abs($qty);
        }

        $unit = $product?->unit ?? '';
        $unitCost = $product?->purchase_price;
        $totalCost = $unitCost !== null ? abs($qty) * (float)$unitCost : null;

        $tx = InventoryTransaction::query()
            ->where('reference_type', 'inventory_adjustment')
            ->where('reference_id', $adjustment->id)
            ->first();

        $payload = [
            'product_id' => $adjustment->product_id,
            'warehouse_id' => $adjustment->warehouse_id,
            'store_id' => $adjustment->store_id,
            'transaction_type' => 'adjust',
            'quantity' => $qty,
            'unit' => $unit,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'reference_type' => 'inventory_adjustment',
            'reference_id' => $adjustment->id,
            'reason' => $adjustment->adjustment_reason ?: null,
            'created_by' => $userId,
            'notes' => $adjustment->notes ?: "库存调整 {$adjustment->adjustment_number}",
        ];

        if ($tx) {
            $tx->update($payload);
        } else {
            $payload['transaction_number'] = $this->generateInventoryTransactionNumber();
            InventoryTransaction::create($payload);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryAdjustment::with(['product', 'warehouse', 'adjustedBy']);
        $this->scopeByOwner($request, $query, 'adjusted_by', 'adjustedBy', 'warehouse_id', 'store_id');

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 按产品筛选
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        // 按仓库筛选
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // 按调整类型筛选
        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->input('adjustment_type'));
        }

        // 按日期范围筛选
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('adjustment_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $adjustments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => InventoryAdjustmentResource::collection($adjustments),
                'meta' => [
                    'current_page' => $adjustments->currentPage(),
                    'per_page' => $adjustments->perPage(),
                    'total' => $adjustments->total(),
                    'last_page' => $adjustments->lastPage(),
                ]
            ],
            'message' => '库存调整列表获取成功'
        ]);
    }

    /**
     * 生成调整单号
     */
    private function generateAdjustmentNumber(): string
    {
        $prefix = 'IA';
        $date = date('Ymd');
        $lastAdjustment = InventoryAdjustment::withoutGlobalScopes()->withTrashed()->where('adjustment_number', 'like', "{$prefix}{$date}%")
            ->orderBy('adjustment_number', 'desc')
            ->first();
        
        if ($lastAdjustment) {
            $lastNumber = intval(substr($lastAdjustment->adjustment_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return "{$prefix}{$date}{$newNumber}";
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'product_id' => 'required|exists:products,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'store_id' => 'nullable|exists:stores,id',
                'adjustment_type' => 'nullable|string|in:increase,decrease',
                'quantity' => 'required|numeric|min:0.01',
                'adjustment_reason' => 'nullable|string|max:255',
                'adjustment_date' => 'nullable|date',
                'status' => 'nullable|string|in:pending,approved,completed',
                'notes' => 'nullable|string',
            ]);

            // 自动生成调整单号
            $validatedData['adjustment_number'] = $this->generateAdjustmentNumber();
            // 设置默认值
            $validatedData['adjustment_date'] = $validatedData['adjustment_date'] ?? now()->toDateString();
            $validatedData['adjustment_type'] = $validatedData['adjustment_type'] ?? 'increase';
            $validatedData['status'] = $validatedData['status'] ?? 'pending';
            // 以当前登录用户为准（避免全部落到默认用户导致审计失真）
            $validatedData['adjusted_by'] = $request->user()?->id ?? 1;
            $validatedData['adjustment_reason'] = $validatedData['adjustment_reason'] ?? '';
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $product = Product::find($validatedData['product_id']);
            $warehouse = Warehouse::find($validatedData['warehouse_id']);
            $targetStoreId = $validatedData['store_id'];
            if ($targetStoreId === null) {
                $targetStoreId = $product?->store_id ?? $warehouse?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($product && $product->store_id !== null && $product->store_id !== $targetStoreId) {
                    return $this->forbid('商品与门店不匹配');
                }
                if ($warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
                    return $this->forbid('仓库与门店不匹配');
                }
            }

            $userId = $validatedData['adjusted_by'];
            $adjustment = DB::transaction(function () use ($validatedData, $userId) {
                $adj = InventoryAdjustment::create($validatedData);
                $this->upsertInventoryTransactionForAdjustment($adj, $userId);
                return $adj;
            });

            $adjustment->load(['product', 'warehouse', 'adjustedBy']);

            return response()->json([
                'success' => true,
                'data' => new InventoryAdjustmentResource($adjustment),
                'message' => '库存调整单创建成功'
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
        $adjustment = InventoryAdjustment::with(['product', 'warehouse', 'adjustedBy'])->find($id);

        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => '库存调整单不存在'
            ], 404);
        }
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $adjustment->adjusted_by,
            $adjustment->adjustedBy?->department_id,
            $adjustment->store_id,
            $adjustment->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new InventoryAdjustmentResource($adjustment),
            'message' => '库存调整单详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     * 库存调整创建即生效，不允许编辑。
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => '库存调整创建后不允许编辑',
        ], 403);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $adjustment = InventoryAdjustment::find($id);

        if (!$adjustment) {
            return response()->json([
                'success' => false,
                'message' => '库存调整单不存在'
            ], 404);
        }
        $adjustment->loadMissing('adjustedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $adjustment->adjusted_by,
            $adjustment->adjustedBy?->department_id,
            $adjustment->store_id,
            $adjustment->warehouse_id
        )) {
            return $resp;
        }

        DB::transaction(function () use ($adjustment) {
            // 物理删除关联流水，避免软删堆积
            InventoryTransaction::query()
                ->where('reference_type', 'inventory_adjustment')
                ->where('reference_id', $adjustment->id)
                ->forceDelete();
            $adjustment->delete();
        });

        return response()->json([
            'success' => true,
            'message' => '库存调整单删除成功'
        ]);
    }
}
