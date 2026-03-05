<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\InventoryTransaction;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SalesOrdersController extends Controller
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
     * 获取某商品在某仓库的当前库存（与前端 CurrentInventory 口径一致）
     */
    private function getStockQty(int $productId, int $warehouseId): float
    {
        $changeExpr = "CASE
            WHEN transaction_type='in' THEN quantity
            WHEN transaction_type='out' THEN -quantity
            WHEN transaction_type='adjust' THEN quantity
            WHEN transaction_type='transfer' THEN quantity
            ELSE quantity
        END";

        $qty = InventoryTransaction::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw("COALESCE(SUM($changeExpr),0) as qty")
            ->value('qty');

        return (float)($qty ?? 0);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesOrder::with(['customer', 'warehouse']);
        $this->scopeByOwner($request, $query, 'created_by', 'createdBy', 'warehouse_id', 'store_id');

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 按客户筛选
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // 按订单类型筛选
        if ($request->filled('order_type')) {
            $query->where('order_type', $request->input('order_type'));
        }

        // 按状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 按日期范围筛选
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('order_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $salesOrders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => SalesOrderResource::collection($salesOrders),
                'meta' => [
                    'current_page' => $salesOrders->currentPage(),
                    'per_page' => $salesOrders->perPage(),
                    'total' => $salesOrders->total(),
                    'last_page' => $salesOrders->lastPage(),
                ]
            ],
            'message' => '销售订单列表获取成功'
        ]);
    }

    /**
     * 生成销售订单号
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'SO';
        $date = date('Ymd');
        $lastOrder = SalesOrder::withoutGlobalScopes()->withTrashed()->where('order_number', 'like', "{$prefix}{$date}%")
            ->orderBy('order_number', 'desc')
            ->first();
        
        if ($lastOrder) {
            $lastNumber = intval(substr($lastOrder->order_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return "{$prefix}{$date}{$newNumber}";
    }

    /**
     * 销售发货：将销售订单标记为 delivered，并自动生成出库流水 + 应收。
     *
     * 幂等策略：
     * - inventory_transactions：按 reference_type=sales_order, reference_id=order.id 判断是否已生成
     * - accounts_receivable：按 customer_id + document_type + document_id updateOrCreate
     */
    public function deliver(Request $request, string $id): JsonResponse
    {
        $order = SalesOrder::with(['items', 'items.product', 'customer', 'warehouse'])->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => '销售订单不存在'], 404);
        }
        $order->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $order->created_by,
            $order->createdBy?->department_id,
            $order->store_id,
            $order->warehouse_id
        )) {
            return $resp;
        }

        if ($order->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => '已取消的销售单不能发货'], 422);
        }

        if ($order->status === 'delivered') {
            return response()->json(['success' => false, 'message' => '该销售单已发货，请勿重复操作'], 422);
        }

        $userId = $request->user()?->id ?? 1;

        DB::transaction(function () use ($order, $userId) {
            // 1) 库存校验（避免发货导致负库存）
            foreach ($order->items as $it) {
                $need = (float)$it->quantity;
                $stock = $this->getStockQty((int)$it->product_id, (int)$order->warehouse_id);
                if ($stock < $need) {
                    throw ValidationException::withMessages([
                        'stock' => ["库存不足：{$it->product?->name} 需要 {$need}，当前 {$stock}"],
                    ]);
                }
            }

            // 2) 更新订单状态
            $order->forceFill([
                'status' => 'delivered',
                'delivery_status' => 'delivered',
            ])->save();

            // 3) 出库流水（每条明细一条）
            $hasTx = InventoryTransaction::query()
                ->where('reference_type', 'sales_order')
                ->where('reference_id', $order->id)
                ->exists();

            if (!$hasTx) {
                $txOffset = 0;
                foreach ($order->items as $it) {
                    $qty = (float)$it->quantity;
                    $unit = $it->unit ?: ($it->product?->unit ?? '');
                    // 这里用商品采购价作为“成本”近似值（无成本核算模块时的折中）
                    $unitCost = $it->product?->purchase_price;
                    $totalCost = $unitCost !== null ? $qty * (float)$unitCost : null;

                    InventoryTransaction::create([
                        'transaction_number' => $this->generateInventoryTransactionNumber($txOffset),
                        'product_id' => $it->product_id,
                        'warehouse_id' => $order->warehouse_id,
                        'store_id' => $order->store_id,
                        'transaction_type' => 'out',
                        'quantity' => $qty, // out 在库存计算中会取 -quantity
                        'unit' => $unit,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'reference_type' => 'sales_order',
                        'reference_id' => $order->id,
                        'reason' => 'sales_deliver',
                        'created_by' => $userId,
                        'notes' => "销售发货：{$order->order_number}",
                    ]);
                    $txOffset++;
                }
            }

            // 4) 应收（按订单一条）
            $amount = (float)$order->total_amount;
            $paid = 0.0;
            $balance = max(0.0, $amount - $paid);
            $dueDate = ($order->delivery_date ?: $order->order_date)?->format('Y-m-d') ?? now()->toDateString();

            AccountReceivable::updateOrCreate(
                [
                    'customer_id' => $order->customer_id,
                    'document_type' => 'sales_order',
                    'document_id' => $order->id,
                ],
                [
                    'document_date' => $order->order_date?->format('Y-m-d') ?? now()->toDateString(),
                    'amount' => $amount,
                    'paid_amount' => $paid,
                    'balance' => $balance,
                    'due_date' => $dueDate,
                    'status' => $balance <= 0 ? 'paid' : (now()->toDateString() > $dueDate ? 'overdue' : 'unpaid'),
                    'store_id' => $order->store_id,
                    'notes' => "来源销售单：{$order->order_number}",
                ]
            );

            // 5) 同步客户未结金额
            $outstanding = AccountReceivable::query()
                ->where('customer_id', $order->customer_id)
                ->where('status', '!=', 'paid')
                ->sum('balance');
            Customer::query()->whereKey($order->customer_id)->update(['outstanding_amount' => $outstanding]);
        });

        $order->refresh()->load(['customer', 'warehouse', 'createdBy', 'items.product']);

        $this->audit($request, 'process', $order, null, null, '销售发货');

        return response()->json([
            'success' => true,
            'data' => new SalesOrderResource($order),
            'message' => '已发货，并生成出库流水与应收',
        ]);
    }

    /**
     * 销售取消/撤销：
     * - 未发货：直接标记 cancelled
     * - 已发货：回滚出库流水 + 应收（存在未作废收款流水则拒绝撤销）
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = SalesOrder::with(['customer'])->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => '销售订单不存在'], 404);
        }
        $order->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $order->created_by,
            $order->createdBy?->department_id,
            $order->store_id,
            $order->warehouse_id
        )) {
            return $resp;
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => true,
                'data' => new SalesOrderResource($order),
                'message' => '已取消',
            ]);
        }

        try {
            DB::transaction(function () use ($order) {
                // 悲观锁防止并发取消
                $order = SalesOrder::lockForUpdate()->find($order->id);
                if ($order->status === 'cancelled') {
                    return; // 已被其他请求取消
                }
                if ($order->status === 'delivered') {
                    // 1) 检查应收是否有未作废的收款流水
                    $receivable = AccountReceivable::query()
                        ->where('customer_id', $order->customer_id)
                        ->where('document_type', 'sales_order')
                        ->where('document_id', $order->id)
                        ->first();

                    if ($receivable) {
                        $hasReceiptTx = FinancialTransaction::query()
                            ->where('type', 'receipt')
                            ->where('related_model_type', AccountReceivable::class)
                            ->where('related_model_id', $receivable->id)
                            ->where('status', '!=', 'voided')
                            ->exists();

                        if ($hasReceiptTx) {
                            throw ValidationException::withMessages([
                                'receipt' => ['该销售单已发生收款流水，请先在收支明细中作废对应流水后再撤销发货'],
                            ]);
                        }
                    }

                    // 2) 物理删除出库流水（回滚库存），避免软删堆积
                    InventoryTransaction::query()
                        ->where('reference_type', 'sales_order')
                        ->where('reference_id', $order->id)
                        ->forceDelete();

                    // 3) 删除应收
                    if ($receivable) {
                        $receivable->delete();
                    }
                }

                // 4) 取消订单
                $order->forceFill([
                    'status' => 'cancelled',
                    'delivery_status' => 'cancelled',
                    'payment_status' => 'unpaid',
                ])->save();

                // 5) 同步客户未结金额
                $outstanding = AccountReceivable::query()
                    ->where('customer_id', $order->customer_id)
                    ->where('status', '!=', 'paid')
                    ->sum('balance');
                Customer::query()->whereKey($order->customer_id)->update(['outstanding_amount' => $outstanding]);
            });

            $order->refresh()->load(['customer', 'warehouse', 'createdBy', 'items.product']);

            $this->audit($request, 'void', $order, null, null, '销售单取消');

            return response()->json([
                'success' => true,
                'data' => new SalesOrderResource($order),
                'message' => '取消成功',
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'order_date' => 'nullable|date',
                'delivery_date' => 'nullable|date',
                'discount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'order_type' => 'nullable|string|in:retail,wholesale,pos',
                'status' => 'nullable|string|in:pending,confirmed,delivered,cancelled',
                'payment_status' => 'nullable|string|in:unpaid,paid,partial',
                'delivery_status' => 'nullable|string|in:pending,shipped,delivered,cancelled',
                'warehouse_id' => 'required|exists:warehouses,id',
                'store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.notes' => 'nullable|string',
            ]);

            // 自动生成订单号
            $validatedData['order_number'] = $this->generateOrderNumber();
            // 设置默认值
            $validatedData['order_date'] = $validatedData['order_date'] ?? now()->toDateString();
            $validatedData['delivery_date'] = $validatedData['delivery_date'] ?? $validatedData['order_date'];
            $validatedData['discount'] = $validatedData['discount'] ?? 0;
            $validatedData['tax_amount'] = $validatedData['tax_amount'] ?? 0;
            $validatedData['shipping_cost'] = $validatedData['shipping_cost'] ?? 0;
            $validatedData['order_type'] = $validatedData['order_type'] ?? 'retail';
            $validatedData['status'] = $validatedData['status'] ?? 'pending';
            $validatedData['payment_status'] = $validatedData['payment_status'] ?? 'unpaid';
            $validatedData['delivery_status'] = $validatedData['delivery_status'] ?? 'pending';
            // 以当前登录用户为准（避免全部落到默认用户导致审计失真）
            $validatedData['created_by'] = $request->user()?->id ?? 1;
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $customer = Customer::find($validatedData['customer_id']);
            $warehouse = Warehouse::find($validatedData['warehouse_id']);
            $targetStoreId = $validatedData['store_id'];
            if ($targetStoreId === null) {
                $targetStoreId = $customer?->store_id ?? $warehouse?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($customer && $customer->store_id !== null && $customer->store_id !== $targetStoreId) {
                    return $this->forbid('客户与门店不匹配');
                }
                if ($warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
                    return $this->forbid('仓库与门店不匹配');
                }
            }

            $items = $validatedData['items'];
            unset($validatedData['items']);

            $salesOrder = DB::transaction(function () use ($validatedData, $items) {
                $subtotal = 0;
                foreach ($items as $it) {
                    $subtotal += (float)$it['quantity'] * (float)$it['unit_price'];
                }
                $validatedData['subtotal'] = $subtotal;
                $validatedData['total_amount'] = max(
                    0,
                    $subtotal - (float)$validatedData['discount'] + (float)$validatedData['tax_amount'] + (float)$validatedData['shipping_cost']
                );

                $order = SalesOrder::create($validatedData);

                $targetStoreId = $validatedData['store_id'] ?? null;
                foreach ($items as $it) {
                    /** @var Product $product */
                    $product = Product::find($it['product_id']);
                    if ($product && $product->store_id !== null) {
                        if ($targetStoreId === null) {
                            $targetStoreId = $product->store_id;
                        } elseif ($product->store_id !== $targetStoreId) {
                            throw ValidationException::withMessages([
                                'items' => ['商品与门店不匹配'],
                            ]);
                        }
                    }
                    $qty = (float)$it['quantity'];
                    $price = (float)$it['unit_price'];
                    $lineAmount = $qty * $price;

                    SalesOrderItem::create([
                        'sales_order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit' => $product->unit,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'line_amount' => $lineAmount,
                        'notes' => $it['notes'] ?? null,
                    ]);
                }

                return $order;
            });

            $salesOrder->load(['customer', 'warehouse', 'createdBy', 'items.product']);

            return response()->json([
                'success' => true,
                'data' => new SalesOrderResource($salesOrder),
                'message' => '销售订单创建成功'
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
        $salesOrder = SalesOrder::with(['customer', 'warehouse', 'createdBy', 'items.product'])->find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => '销售订单不存在'
            ], 404);
        }
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $salesOrder->created_by,
            $salesOrder->createdBy?->department_id,
            $salesOrder->store_id,
            $salesOrder->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new SalesOrderResource($salesOrder),
            'message' => '销售订单详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $salesOrder = SalesOrder::find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => '销售订单不存在'
            ], 404);
        }
        $salesOrder->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $salesOrder->created_by,
            $salesOrder->createdBy?->department_id,
            $salesOrder->store_id,
            $salesOrder->warehouse_id
        )) {
            return $resp;
        }
        if ($salesOrder->status === 'delivered') {
            return response()->json([
                'success' => false,
                'message' => '已发货的销售单不可修改'
            ], 422);
        }

        try {
            $validatedData = $request->validate([
                'order_number' => 'sometimes|string|unique:sales_orders,order_number,' . $id,
                'customer_id' => 'sometimes|exists:customers,id',
                'order_date' => 'sometimes|date',
                'delivery_date' => 'nullable|date',
                'discount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'order_type' => 'sometimes|string|in:retail,wholesale,pos',
                'status' => 'sometimes|string|in:pending,confirmed,delivered,cancelled',
                'payment_status' => 'sometimes|string|in:unpaid,paid,partial',
                'delivery_status' => 'sometimes|string|in:pending,shipped,delivered,cancelled',
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'notes' => 'nullable|string',
                'items' => 'sometimes|array|min:1',
                'items.*.product_id' => 'required_with:items|exists:products,id',
                'items.*.quantity' => 'required_with:items|numeric|min:0.01',
                'items.*.unit_price' => 'required_with:items|numeric|min:0',
                'items.*.notes' => 'nullable|string',
            ]);

            $salesOrder = DB::transaction(function () use ($salesOrder, $validatedData) {
                $items = $validatedData['items'] ?? null;
                unset($validatedData['items']);

                if (is_array($items)) {
                    $subtotal = 0;
                    foreach ($items as $it) {
                        $subtotal += (float)$it['quantity'] * (float)$it['unit_price'];
                    }
                    $discount = array_key_exists('discount', $validatedData) ? (float)$validatedData['discount'] : (float)$salesOrder->discount;
                    $tax = array_key_exists('tax_amount', $validatedData) ? (float)$validatedData['tax_amount'] : (float)$salesOrder->tax_amount;
                    $ship = array_key_exists('shipping_cost', $validatedData) ? (float)$validatedData['shipping_cost'] : (float)$salesOrder->shipping_cost;

                    $validatedData['subtotal'] = $subtotal;
                    $validatedData['total_amount'] = max(0, $subtotal - $discount + $tax + $ship);
                }

                $salesOrder->update($validatedData);

                if (is_array($items)) {
                    // 直接重建明细：物理删除避免软删记录堆积，与盘点明细逻辑一致
                    $salesOrder->items()->forceDelete();
                    foreach ($items as $it) {
                        $product = Product::find($it['product_id']);
                        $qty = (float)$it['quantity'];
                        $price = (float)$it['unit_price'];
                        SalesOrderItem::create([
                            'sales_order_id' => $salesOrder->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'unit' => $product->unit,
                            'quantity' => $qty,
                            'unit_price' => $price,
                            'line_amount' => $qty * $price,
                            'notes' => $it['notes'] ?? null,
                        ]);
                    }
                }

                return $salesOrder;
            });

            $salesOrder->load(['customer', 'warehouse', 'createdBy', 'items.product']);

            return response()->json([
                'success' => true,
                'data' => new SalesOrderResource($salesOrder),
                'message' => '销售订单更新成功'
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
        $salesOrder = SalesOrder::find($id);

        if (!$salesOrder) {
            return response()->json([
                'success' => false,
                'message' => '销售订单不存在'
            ], 404);
        }
        $salesOrder->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $salesOrder->created_by,
            $salesOrder->createdBy?->department_id,
            $salesOrder->store_id,
            $salesOrder->warehouse_id
        )) {
            return $resp;
        }

        $salesOrder->delete();

        return response()->json([
            'success' => true,
            'message' => '销售订单删除成功'
        ]);
    }
}
