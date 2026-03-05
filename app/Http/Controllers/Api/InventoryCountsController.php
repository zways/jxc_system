<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryCountResource;
use App\Http\Resources\InventoryCountItemResource;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\InventoryAdjustment;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InventoryCountsController extends Controller
{
    private function dec2(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryCount::with(['warehouse', 'countedBy'])->withCount('items');
        $this->scopeByOwner($request, $query, 'counted_by', 'countedBy', 'warehouse_id', 'store_id');

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('count_number', 'LIKE', "%{$search}%")
                    ->orWhere('type', 'LIKE', "%{$search}%")
                    ->orWhereHas('warehouse', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 按仓库筛选
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // 按盘点类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // 按状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 按日期范围筛选
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('count_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $counts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => InventoryCountResource::collection($counts),
                'meta' => [
                    'current_page' => $counts->currentPage(),
                    'per_page' => $counts->perPage(),
                    'total' => $counts->total(),
                    'last_page' => $counts->lastPage(),
                ]
            ],
            'message' => '盘点列表获取成功'
        ]);
    }

    /**
     * 生成盘点单号
     */
    private function generateCountNumber(): string
    {
        $prefix = 'IC';
        $date = date('Ymd');
        $lastCount = InventoryCount::withoutGlobalScopes()->withTrashed()->where('count_number', 'like', "{$prefix}{$date}%")
            ->orderBy('count_number', 'desc')
            ->first();
        
        if ($lastCount) {
            $lastNumber = intval(substr($lastCount->count_number, -4));
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
                'warehouse_id' => 'required|exists:warehouses,id',
                'store_id' => 'nullable|exists:stores,id',
                'type' => 'nullable|string|in:full,partial,cycle,frozen',
                'count_date' => 'nullable|date',
                'status' => 'nullable|string|in:pending,in_progress,completed,verified',
                'variance_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            // 自动生成盘点单号
            $validatedData['count_number'] = $this->generateCountNumber();
            // 设置默认值
            $validatedData['count_date'] = $validatedData['count_date'] ?? now()->toDateString();
            $validatedData['type'] = $validatedData['type'] ?? 'cycle';
            $validatedData['status'] = $validatedData['status'] ?? 'in_progress';
            // 以当前登录用户为准（避免全部落到默认用户导致审计失真）
            $validatedData['counted_by'] = $request->user()?->id ?? 1;
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

        $warehouse = Warehouse::find($validatedData['warehouse_id']);
        $targetStoreId = $validatedData['store_id'];
        if ($targetStoreId === null) {
            $targetStoreId = $warehouse?->store_id;
            $validatedData['store_id'] = $targetStoreId;
        }
        if ($targetStoreId !== null && $warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
            return response()->json(['success' => false, 'message' => '仓库与门店不匹配'], 403);
        }

            $count = InventoryCount::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new InventoryCountResource($count),
                'message' => '盘点单创建成功'
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
        $count = InventoryCount::with(['warehouse', 'countedBy', 'items.product'])->find($id);

        if (!$count) {
            return response()->json([
                'success' => false,
                'message' => '盘点单不存在'
            ], 404);
        }
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $count->counted_by,
            $count->countedBy?->department_id,
            $count->store_id,
            $count->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new InventoryCountResource($count),
            'message' => '盘点单详情获取成功'
        ]);
    }

    /**
     * 盘点明细列表
     */
    public function items(Request $request, string $id): JsonResponse
    {
        $count = InventoryCount::with(['items.product', 'warehouse'])->find($id);
        if (!$count) {
            return response()->json(['success' => false, 'message' => '盘点单不存在'], 404);
        }
        $count->loadMissing('countedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $count->counted_by,
            $count->countedBy?->department_id,
            $count->store_id,
            $count->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => InventoryCountItemResource::collection($count->items),
            'message' => '盘点明细获取成功',
        ]);
    }

    /**
     * 保存（覆盖）盘点明细：传入 product_id + counted_quantity
     */
    public function saveItems(Request $request, string $id): JsonResponse
    {
        $count = InventoryCount::find($id);
        if (!$count) {
            return response()->json(['success' => false, 'message' => '盘点单不存在'], 404);
        }
        $count->loadMissing('countedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $count->counted_by,
            $count->countedBy?->department_id,
            $count->store_id,
            $count->warehouse_id
        )) {
            return $resp;
        }

        if (in_array($count->status, ['completed', 'verified'], true)) {
            return response()->json(['success' => false, 'message' => '盘点单已完成'], 400);
        }

        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.counted_quantity' => 'required|numeric|min:0',
                'items.*.notes' => 'nullable|string',
            ]);

            $warehouseId = (int)$count->warehouse_id;
            $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();

        if ($count->store_id !== null) {
            $products = Product::whereIn('id', $productIds)->get();
            foreach ($products as $p) {
                if ($p->store_id !== null && $p->store_id !== $count->store_id) {
                    return response()->json(['success' => false, 'message' => '商品与门店不匹配'], 403);
                }
            }
        }

            // 批量计算账面库存
            $changeExpr = "CASE WHEN transaction_type='in' THEN quantity WHEN transaction_type='out' THEN -quantity ELSE quantity END";
            $bookMap = InventoryTransaction::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $productIds)
                ->select('product_id', DB::raw("COALESCE(SUM($changeExpr),0) as qty"))
                ->groupBy('product_id')
                ->pluck('qty', 'product_id')
                ->toArray();

            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            DB::transaction(function () use ($count, $validated, $bookMap, $products) {
                // 物理删除旧明细（表有 unique(inventory_count_id, product_id)，软删会导致再次保存时重复键冲突）
                $count->items()->forceDelete();

                $varianceTotal = 0.0;
                foreach ($validated['items'] as $it) {
                    $pid = (int)$it['product_id'];
                    $bookQty = (float)($bookMap[$pid] ?? 0);
                    $countedQty = (float)$it['counted_quantity'];
                    $varianceQty = $countedQty - $bookQty;

                    $p = $products->get($pid);
                    $unitCost = $p ? (float)($p->purchase_price ?? 0) : 0.0;
                    $varianceAmount = $varianceQty * $unitCost;
                    $varianceTotal += abs($varianceAmount);

                    InventoryCountItem::create([
                        'inventory_count_id' => $count->id,
                        'product_id' => $pid,
                        'book_quantity' => $this->dec2($bookQty),
                        'counted_quantity' => $this->dec2($countedQty),
                        'variance_quantity' => $this->dec2($varianceQty),
                        'unit_cost' => $this->dec2($unitCost),
                        'variance_amount' => $this->dec2($varianceAmount),
                        'notes' => $it['notes'] ?? null,
                    ]);
                }

                $count->variance_amount = $this->dec2($varianceTotal);
                $count->save();
            });

            $count->load(['items.product', 'warehouse', 'countedBy']);

            return response()->json([
                'success' => true,
                'data' => new InventoryCountResource($count),
                'message' => '盘点明细保存成功',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * 完成盘点：根据差异生成调整单 & 库存调整流水，并将盘点状态置为 completed
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $count = InventoryCount::with(['items.product', 'warehouse'])->find($id);
        if (!$count) {
            return response()->json(['success' => false, 'message' => '盘点单不存在'], 404);
        }
        $count->loadMissing('countedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $count->counted_by,
            $count->countedBy?->department_id,
            $count->store_id,
            $count->warehouse_id
        )) {
            return $resp;
        }
        if (in_array($count->status, ['completed', 'verified'], true)) {
            return response()->json(['success' => false, 'message' => '盘点单已完成'], 400);
        }
        if ($count->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => '暂无盘点明细，请先点击「明细」添加商品并保存后再完成'], 400);
        }

        if ($count->store_id !== null) {
            foreach ($count->items as $item) {
                $p = $item->product;
                if ($p && $p->store_id !== null && $p->store_id !== $count->store_id) {
                    return response()->json(['success' => false, 'message' => '商品与门店不匹配'], 403);
                }
            }
        }

        DB::transaction(function () use ($count) {
            $varianceTotal = 0.0;
            $adjOffset = 0;
            $txOffset = 0;

            foreach ($count->items as $item) {
                $varianceQty = (float)$item->variance_quantity;
                $p = $item->product;
                $unitCost = $p ? (float)($p->purchase_price ?? $item->unit_cost ?? 0) : (float)($item->unit_cost ?? 0);

                $varianceAmount = $varianceQty * $unitCost;
                $varianceTotal += abs($varianceAmount);

                // 生成库存调整（每个商品一张，符合当前表结构）
                if (abs($varianceQty) > 0.00001) {
                    $adjType = $varianceQty >= 0 ? 'increase' : 'decrease';
                    $adj = InventoryAdjustment::create([
                        'adjustment_number' => $this->generateAdjustmentNumber($adjOffset),
                        'product_id' => $item->product_id,
                        'warehouse_id' => $count->warehouse_id,
                        'store_id' => $count->store_id,
                        'quantity' => $this->dec2(abs($varianceQty)),
                        'adjustment_type' => $adjType,
                        'adjustment_reason' => '盘点差异调整',
                        'adjustment_date' => $count->count_date,
                        'adjusted_by' => $count->counted_by,
                        'status' => 'completed',
                        'notes' => $count->count_number,
                    ]);
                    $adjOffset++;

                    InventoryTransaction::create([
                        'transaction_number' => $this->generateInventoryTransactionNumber($txOffset),
                        'product_id' => $item->product_id,
                        'warehouse_id' => $count->warehouse_id,
                        'store_id' => $count->store_id,
                        'transaction_type' => 'adjust',
                        'quantity' => $this->dec2($varianceQty), // adjust 允许正负
                        'unit' => $p?->unit ?? ($item->product?->unit ?? ''),
                        'unit_cost' => $this->dec2($unitCost),
                        'total_cost' => $this->dec2($unitCost * $varianceQty),
                        'reference_type' => 'inventory_count',
                        'reference_id' => $count->id,
                        'reason' => '盘点调整',
                        'created_by' => $count->counted_by,
                        'notes' => $count->count_number,
                    ]);
                    $txOffset++;
                }

                // 同步更新 item 的金额（确保一致）
                $item->unit_cost = $this->dec2($unitCost);
                $item->variance_amount = $this->dec2($varianceAmount);
                $item->save();
            }

            $count->variance_amount = $this->dec2($varianceTotal);
            $count->status = 'completed';
            $count->save();
        });

        $count->load(['warehouse', 'countedBy', 'items.product']);

        return response()->json([
            'success' => true,
            'data' => new InventoryCountResource($count),
            'message' => '盘点完成成功',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $count = InventoryCount::find($id);

        if (!$count) {
            return response()->json([
                'success' => false,
                'message' => '盘点单不存在'
            ], 404);
        }
        $count->loadMissing('countedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $count->counted_by,
            $count->countedBy?->department_id,
            $count->store_id,
            $count->warehouse_id
        )) {
            return $resp;
        }

        try {
            $validatedData = $request->validate([
                'count_number' => 'sometimes|string|unique:inventory_counts,count_number,' . $id,
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'store_id' => 'nullable|exists:stores,id',
                'type' => 'sometimes|string|in:full,partial,cycle,frozen',
                'count_date' => 'sometimes|date',
                'status' => 'sometimes|string|in:pending,in_progress,completed,verified',
                'variance_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
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

            $warehouseId = $validatedData['warehouse_id'] ?? $count->warehouse_id;
            $targetStoreId = array_key_exists('store_id', $validatedData) ? $validatedData['store_id'] : $count->store_id;
            $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
            if ($targetStoreId === null) {
                $targetStoreId = $warehouse?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null && $warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
                return response()->json(['success' => false, 'message' => '仓库与门店不匹配'], 403);
            }

            $count->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new InventoryCountResource($count),
                'message' => '盘点单更新成功'
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
        $count = InventoryCount::find($id);

        if (!$count) {
            return response()->json([
                'success' => false,
                'message' => '盘点单不存在'
            ], 404);
        }
        $count->loadMissing('countedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $count->counted_by,
            $count->countedBy?->department_id,
            $count->store_id,
            $count->warehouse_id
        )) {
            return $resp;
        }

        $count->delete();

        return response()->json([
            'success' => true,
            'message' => '盘点单删除成功'
        ]);
    }

    private function generateAdjustmentNumber(int $offset = 0): string
    {
        $prefix = 'IA';
        $date = date('Ymd');
        $last = InventoryAdjustment::withoutGlobalScopes()->withTrashed()->where('adjustment_number', 'like', "{$prefix}{$date}%")
            ->orderBy('adjustment_number', 'desc')
            ->first();

        $baseNumber = 1;
        if ($last) {
            $baseNumber = intval(substr($last->adjustment_number, -4)) + 1;
        }
        $newNumber = str_pad($baseNumber + $offset, 4, '0', STR_PAD_LEFT);
        return "{$prefix}{$date}{$newNumber}";
    }

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
}
