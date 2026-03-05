<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\AccountPayable;
use App\Models\FinancialTransaction;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PurchaseOrdersController extends Controller
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
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier', 'warehouse']);
        $this->scopeByOwner($request, $query, 'created_by', 'createdBy', 'warehouse_id', 'store_id');

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 按供应商筛选
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
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
        $purchaseOrders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => PurchaseOrderResource::collection($purchaseOrders),
                'meta' => [
                    'current_page' => $purchaseOrders->currentPage(),
                    'per_page' => $purchaseOrders->perPage(),
                    'total' => $purchaseOrders->total(),
                    'last_page' => $purchaseOrders->lastPage(),
                ]
            ],
            'message' => '采购订单列表获取成功'
        ]);
    }

    /**
     * 生成采购订单号
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'PO';
        $date = date('Ymd');
        $lastOrder = PurchaseOrder::withoutGlobalScopes()->withTrashed()->where('order_number', 'like', "{$prefix}{$date}%")
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
     * 采购收货：将采购订单标记为 received，并自动生成入库流水 + 应付。
     *
     * 幂等策略：
     * - inventory_transactions：按 reference_type=purchase_order, reference_id=order.id 判断是否已生成
     * - accounts_payable：按 supplier_id + document_type + document_id updateOrCreate
     */
    public function receive(Request $request, string $id): JsonResponse
    {
        $order = PurchaseOrder::with(['items', 'items.product', 'supplier', 'warehouse'])->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => '采购订单不存在'], 404);
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
            return response()->json(['success' => false, 'message' => '已取消的采购单不能收货'], 422);
        }

        if ($order->status === 'received') {
            return response()->json(['success' => false, 'message' => '该采购单已收货，请勿重复操作'], 422);
        }

        $userId = $request->user()?->id ?? 1;

        DB::transaction(function () use ($order, $userId) {
            // 1) 更新订单状态
            $order->forceFill([
                'status' => 'received',
                'delivery_status' => 'delivered',
            ])->save();

            // 2) 入库流水（每条明细一条）
            $hasTx = InventoryTransaction::query()
                ->where('reference_type', 'purchase_order')
                ->where('reference_id', $order->id)
                ->exists();

            if (!$hasTx) {
                $txOffset = 0;
                foreach ($order->items as $it) {
                    $qty = (float)$it->quantity;
                    $unitCost = (float)$it->unit_price;
                    $unit = $it->unit ?: ($it->product?->unit ?? '');

                    InventoryTransaction::create([
                        'transaction_number' => $this->generateInventoryTransactionNumber($txOffset),
                        'product_id' => $it->product_id,
                        'warehouse_id' => $order->warehouse_id,
                        'store_id' => $order->store_id,
                        'transaction_type' => 'in',
                        'quantity' => $qty,
                        'unit' => $unit,
                        'unit_cost' => $unitCost,
                        'total_cost' => $qty * $unitCost,
                        'reference_type' => 'purchase_order',
                        'reference_id' => $order->id,
                        'reason' => 'purchase_receive',
                        'created_by' => $userId,
                        'notes' => "采购收货：{$order->order_number}",
                    ]);
                    $txOffset++;
                }
            }

            // 3) 应付（按订单一条）
            $amount = (float)$order->total_amount;
            $paid = 0.0;
            $balance = max(0.0, $amount - $paid);
            $dueDate = ($order->expected_delivery_date ?: $order->order_date)?->format('Y-m-d') ?? now()->toDateString();

            AccountPayable::updateOrCreate(
                [
                    'supplier_id' => $order->supplier_id,
                    'document_type' => 'purchase_order',
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
                    'notes' => "来源采购单：{$order->order_number}",
                ]
            );

            // 4) 同步供应商未结金额
            $outstanding = AccountPayable::query()
                ->where('supplier_id', $order->supplier_id)
                ->where('status', '!=', 'paid')
                ->sum('balance');
            Supplier::query()->whereKey($order->supplier_id)->update(['outstanding_amount' => $outstanding]);
        });

        $order->refresh()->load(['supplier', 'warehouse', 'createdBy', 'items.product']);

        $this->audit($request, 'process', $order, null, null, '采购收货');

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($order),
            'message' => '已收货，并生成入库流水与应付',
        ]);
    }

    /**
     * 采购取消/撤销：
     * - 未收货：直接标记 cancelled
     * - 已收货：回滚入库流水 + 应付（存在未作废付款流水则拒绝撤销）
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = PurchaseOrder::with(['supplier'])->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => '采购订单不存在'], 404);
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
                'data' => new PurchaseOrderResource($order),
                'message' => '已取消',
            ]);
        }

        try {
            DB::transaction(function () use ($order) {
                // 悲观锁防止并发取消
                $order = PurchaseOrder::lockForUpdate()->find($order->id);
                if ($order->status === 'cancelled') {
                    return; // 已被其他请求取消
                }
                if ($order->status === 'received') {
                    // 1) 检查应付是否有未作废的付款流水
                    $payable = AccountPayable::query()
                        ->where('supplier_id', $order->supplier_id)
                        ->where('document_type', 'purchase_order')
                        ->where('document_id', $order->id)
                        ->first();

                    if ($payable) {
                        $hasPaymentTx = FinancialTransaction::query()
                            ->where('type', 'payment')
                            ->where('related_model_type', AccountPayable::class)
                            ->where('related_model_id', $payable->id)
                            ->where('status', '!=', 'voided')
                            ->exists();

                        if ($hasPaymentTx) {
                            throw ValidationException::withMessages([
                                'payment' => ['该采购单已发生付款流水，请先在收支明细中作废对应流水后再撤销收货'],
                            ]);
                        }
                    }

                    // 2) 物理删除入库流水（回滚库存），避免软删堆积
                    InventoryTransaction::query()
                        ->where('reference_type', 'purchase_order')
                        ->where('reference_id', $order->id)
                        ->forceDelete();

                    // 3) 删除应付
                    if ($payable) {
                        $payable->delete();
                    }
                }

                // 4) 取消订单
                $order->forceFill([
                    'status' => 'cancelled',
                    'delivery_status' => 'cancelled',
                    'payment_status' => 'unpaid',
                ])->save();

                // 5) 同步供应商未结金额
                $outstanding = AccountPayable::query()
                    ->where('supplier_id', $order->supplier_id)
                    ->where('status', '!=', 'paid')
                    ->sum('balance');
                Supplier::query()->whereKey($order->supplier_id)->update(['outstanding_amount' => $outstanding]);
            });

            $order->refresh()->load(['supplier', 'warehouse', 'createdBy', 'items.product']);

            $this->audit($request, 'void', $order, null, null, '采购单取消');

            return response()->json([
                'success' => true,
                'data' => new PurchaseOrderResource($order),
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
                'supplier_id' => 'required|exists:suppliers,id',
                'order_date' => 'nullable|date',
                'expected_delivery_date' => 'nullable|date',
                'discount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|in:pending,confirmed,received,cancelled',
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
            $validatedData['expected_delivery_date'] = $validatedData['expected_delivery_date'] ?? $validatedData['order_date'];
            $validatedData['discount'] = $validatedData['discount'] ?? 0;
            $validatedData['tax_amount'] = $validatedData['tax_amount'] ?? 0;
            $validatedData['shipping_cost'] = $validatedData['shipping_cost'] ?? 0;
            $validatedData['subtotal'] = $validatedData['subtotal'] ?? 0;
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

            $supplier = Supplier::find($validatedData['supplier_id']);
            $warehouse = Warehouse::find($validatedData['warehouse_id']);
            $targetStoreId = $validatedData['store_id'];
            if ($targetStoreId === null) {
                $targetStoreId = $supplier?->store_id ?? $warehouse?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($supplier && $supplier->store_id !== null && $supplier->store_id !== $targetStoreId) {
                    return $this->forbid('供应商与门店不匹配');
                }
                if ($warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
                    return $this->forbid('仓库与门店不匹配');
                }
            }

            $items = $validatedData['items'];
            unset($validatedData['items']);

            $purchaseOrder = DB::transaction(function () use ($validatedData, $items) {
                $subtotal = 0;
                foreach ($items as $it) {
                    $subtotal += (float)$it['quantity'] * (float)$it['unit_price'];
                }

                $validatedData['subtotal'] = $subtotal;
                $validatedData['total_amount'] = max(
                    0,
                    $subtotal - (float)$validatedData['discount'] + (float)$validatedData['tax_amount'] + (float)$validatedData['shipping_cost']
                );

                $order = PurchaseOrder::create($validatedData);

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

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
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

            $purchaseOrder->load(['supplier', 'warehouse', 'createdBy', 'items.product']);

            return response()->json([
                'success' => true,
                'data' => new PurchaseOrderResource($purchaseOrder),
                'message' => '采购订单创建成功'
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
        $purchaseOrder = PurchaseOrder::with(['supplier', 'warehouse', 'createdBy', 'items.product'])->find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => '采购订单不存在'
            ], 404);
        }
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $purchaseOrder->created_by,
            $purchaseOrder->createdBy?->department_id,
            $purchaseOrder->store_id,
            $purchaseOrder->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new PurchaseOrderResource($purchaseOrder),
            'message' => '采购订单详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => '采购订单不存在'
            ], 404);
        }
        $purchaseOrder->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $purchaseOrder->created_by,
            $purchaseOrder->createdBy?->department_id,
            $purchaseOrder->store_id,
            $purchaseOrder->warehouse_id
        )) {
            return $resp;
        }
        if ($purchaseOrder->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => '已收货的采购单不可修改'
            ], 422);
        }

        try {
            $validatedData = $request->validate([
                'order_number' => 'sometimes|string|unique:purchase_orders,order_number,' . $id,
                'supplier_id' => 'sometimes|exists:suppliers,id',
                'order_date' => 'sometimes|date',
                'expected_delivery_date' => 'nullable|date',
                'discount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'shipping_cost' => 'nullable|numeric|min:0',
                'status' => 'sometimes|string|in:pending,confirmed,received,cancelled',
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

            $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $validatedData) {
                $items = $validatedData['items'] ?? null;
                unset($validatedData['items']);

                // 如果更新了明细，则重算金额
                if (is_array($items)) {
                    $subtotal = 0;
                    foreach ($items as $it) {
                        $subtotal += (float)$it['quantity'] * (float)$it['unit_price'];
                    }
                    $discount = array_key_exists('discount', $validatedData) ? (float)$validatedData['discount'] : (float)$purchaseOrder->discount;
                    $tax = array_key_exists('tax_amount', $validatedData) ? (float)$validatedData['tax_amount'] : (float)$purchaseOrder->tax_amount;
                    $ship = array_key_exists('shipping_cost', $validatedData) ? (float)$validatedData['shipping_cost'] : (float)$purchaseOrder->shipping_cost;

                    $validatedData['subtotal'] = $subtotal;
                    $validatedData['total_amount'] = max(0, $subtotal - $discount + $tax + $ship);
                }

                $purchaseOrder->update($validatedData);

                if (is_array($items)) {
                    // 直接重建明细：物理删除避免软删记录堆积，与盘点明细逻辑一致
                    $purchaseOrder->items()->forceDelete();
                    foreach ($items as $it) {
                        $product = Product::find($it['product_id']);
                        $qty = (float)$it['quantity'];
                        $price = (float)$it['unit_price'];
                        PurchaseOrderItem::create([
                            'purchase_order_id' => $purchaseOrder->id,
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

                return $purchaseOrder;
            });

            $purchaseOrder->load(['supplier', 'warehouse', 'createdBy', 'items.product']);

            return response()->json([
                'success' => true,
                'data' => new PurchaseOrderResource($purchaseOrder),
                'message' => '采购订单更新成功'
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
        $purchaseOrder = PurchaseOrder::find($id);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => '采购订单不存在'
            ], 404);
        }
        $purchaseOrder->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $purchaseOrder->created_by,
            $purchaseOrder->createdBy?->department_id,
            $purchaseOrder->store_id,
            $purchaseOrder->warehouse_id
        )) {
            return $resp;
        }

        $purchaseOrder->delete();

        return response()->json([
            'success' => true,
            'message' => '采购订单删除成功'
        ]);
    }
}
