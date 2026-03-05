<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesReturnResource;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\InventoryTransaction;
use App\Models\SalesReturn;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SalesReturnsController extends Controller
{
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

    private function generateFinancialTransactionNumber(): string
    {
        $prefix = 'FT';
        $date = date('Ymd');
        $last = FinancialTransaction::withoutGlobalScopes()->withTrashed()->where('transaction_number', 'like', "{$prefix}{$date}%")
            ->orderBy('transaction_number', 'desc')
            ->first();

        $newNumber = '0001';
        if ($last) {
            $lastNumber = intval(substr($last->transaction_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }
        return "{$prefix}{$date}{$newNumber}";
    }

    private function syncCustomerOutstanding(int $customerId): void
    {
        $outstanding = AccountReceivable::query()
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'paid')
            ->sum('balance');
        Customer::query()->whereKey($customerId)->update(['outstanding_amount' => $outstanding]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesReturn::with(['sale', 'customer', 'returnedBy', 'warehouse']);
        $this->scopeByOwner($request, $query, 'returned_by', 'returnedBy', 'warehouse_id', 'store_id');

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'LIKE', "%{$search}%")
                    ->orWhere('reason', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 按客户筛选
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // 按状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 按日期范围筛选
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('return_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $returns = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => SalesReturnResource::collection($returns),
                'meta' => [
                    'current_page' => $returns->currentPage(),
                    'per_page' => $returns->perPage(),
                    'total' => $returns->total(),
                    'last_page' => $returns->lastPage(),
                ]
            ],
            'message' => '退货单列表获取成功'
        ]);
    }

    /**
     * 生成退货单号
     */
    private function generateReturnNumber(): string
    {
        $prefix = 'SR';
        $date = date('Ymd');
        $lastReturn = SalesReturn::withoutGlobalScopes()->withTrashed()->where('return_number', 'like', "{$prefix}{$date}%")
            ->orderBy('return_number', 'desc')
            ->first();
        
        if ($lastReturn) {
            $lastNumber = intval(substr($lastReturn->return_number, -4));
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
                'sale_id' => 'required|exists:sales_orders,id',
                'customer_id' => 'required|exists:customers,id',
                'return_date' => 'nullable|date',
                'subtotal' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'total_amount' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|in:pending,approved,processed,refunded',
                'reason' => 'nullable|string|max:255',
                'warehouse_id' => 'required|exists:warehouses,id',
                'store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
            ]);

            // 自动生成退货单号
            $validatedData['return_number'] = $this->generateReturnNumber();
            // 设置默认值
            $validatedData['return_date'] = $validatedData['return_date'] ?? now()->toDateString();
            $validatedData['status'] = $validatedData['status'] ?? 'pending';
            // 当前登录用户
            $validatedData['returned_by'] = $request->user()?->id;
            if ($validatedData['returned_by'] === null) {
                return response()->json(['success' => false, 'message' => '未认证用户不允许创建退货单'], 401);
            }

            // 自动从关联销售单计算退货金额（当未显式传入时）
            if (empty($validatedData['subtotal']) || empty($validatedData['total_amount'])) {
                $sale = SalesOrder::with('items')->find($validatedData['sale_id']);
                if ($sale) {
                    $subtotal = $sale->items->sum(fn($it) => (float) $it->line_amount);
                    $validatedData['subtotal'] = $validatedData['subtotal'] ?? $subtotal;
                    $validatedData['tax_amount'] = $validatedData['tax_amount'] ?? (float) ($sale->tax_amount ?? 0);
                    $validatedData['total_amount'] = $validatedData['total_amount'] ?? (float) ($sale->total_amount ?? $subtotal);
                } else {
                    $validatedData['subtotal'] = $validatedData['subtotal'] ?? 0;
                    $validatedData['tax_amount'] = $validatedData['tax_amount'] ?? 0;
                    $validatedData['total_amount'] = $validatedData['total_amount'] ?? 0;
                }
            }
            $validatedData['tax_amount'] = $validatedData['tax_amount'] ?? 0;
            $validatedData['reason'] = $validatedData['reason'] ?? '';
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $sale = SalesOrder::find($validatedData['sale_id']);
            $customer = Customer::find($validatedData['customer_id']);
            $warehouse = Warehouse::find($validatedData['warehouse_id']);
            $targetStoreId = $validatedData['store_id'];
            if ($targetStoreId === null) {
                $targetStoreId = $sale?->store_id ?? $customer?->store_id ?? $warehouse?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($sale && $sale->store_id !== null && $sale->store_id !== $targetStoreId) {
                    return $this->forbid('销售单与门店不匹配');
                }
                if ($customer && $customer->store_id !== null && $customer->store_id !== $targetStoreId) {
                    return $this->forbid('客户与门店不匹配');
                }
                if ($warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
                    return $this->forbid('仓库与门店不匹配');
                }
            }

            $return = SalesReturn::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new SalesReturnResource($return),
                'message' => '退货单创建成功'
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
        $return = SalesReturn::with(['sale', 'customer', 'returnedBy', 'warehouse'])->find($id);

        if (!$return) {
            return response()->json([
                'success' => false,
                'message' => '退货单不存在'
            ], 404);
        }
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $return->returned_by,
            $return->returnedBy?->department_id,
            $return->store_id,
            $return->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new SalesReturnResource($return),
            'message' => '退货单详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $return = SalesReturn::find($id);

        if (!$return) {
            return response()->json([
                'success' => false,
                'message' => '退货单不存在'
            ], 404);
        }
        $return->loadMissing(['returnedBy', 'warehouse']);
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $return->returned_by,
            $return->returnedBy?->department_id,
            $return->store_id,
            $return->warehouse_id
        )) {
            return $resp;
        }

        try {
            $validatedData = $request->validate([
                'return_number' => 'sometimes|string|unique:sales_returns,return_number,' . $id,
                'sale_id' => 'sometimes|exists:sales_orders,id',
                'customer_id' => 'sometimes|exists:customers,id',
                'return_date' => 'sometimes|date',
                'subtotal' => 'sometimes|numeric|min:0',
                'tax_amount' => 'sometimes|numeric|min:0',
                'total_amount' => 'sometimes|numeric|min:0',
                'status' => 'sometimes|string|in:pending,approved,processed,refunded',
                'reason' => 'sometimes|string|max:255',
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'store_id' => 'nullable|exists:stores,id',
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

            $saleId = $validatedData['sale_id'] ?? $return->sale_id;
            $customerId = $validatedData['customer_id'] ?? $return->customer_id;
            $warehouseId = $validatedData['warehouse_id'] ?? $return->warehouse_id;
            $targetStoreId = array_key_exists('store_id', $validatedData) ? $validatedData['store_id'] : $return->store_id;
            $sale = $saleId ? SalesOrder::find($saleId) : null;
            $customer = $customerId ? Customer::find($customerId) : null;
            $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
            if ($targetStoreId === null) {
                $targetStoreId = $sale?->store_id ?? $customer?->store_id ?? $warehouse?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($sale && $sale->store_id !== null && $sale->store_id !== $targetStoreId) {
                    return $this->forbid('销售单与门店不匹配');
                }
                if ($customer && $customer->store_id !== null && $customer->store_id !== $targetStoreId) {
                    return $this->forbid('客户与门店不匹配');
                }
                if ($warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
                    return $this->forbid('仓库与门店不匹配');
                }
            }

            $return->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new SalesReturnResource($return),
                'message' => '退货单更新成功'
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
        $return = SalesReturn::find($id);

        if (!$return) {
            return response()->json([
                'success' => false,
                'message' => '退货单不存在'
            ], 404);
        }
        $return->loadMissing('returnedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $return->returned_by,
            $return->returnedBy?->department_id,
            $return->store_id,
            $return->warehouse_id
        )) {
            return $resp;
        }

        $return->delete();

        return response()->json([
            'success' => true,
            'message' => '退货单删除成功'
        ]);
    }

    /**
     * 退货处理闭环：
     * - 生成退回入库流水（按原销售单明细全量退回的简化实现）
     * - 冲减销售单应收（amount/balance），并同步客户未结与订单付款状态
     */
    public function process(Request $request, string $id): JsonResponse
    {
        $return = SalesReturn::with(['sale.items.product', 'customer'])->find($id);
        if (!$return) {
            return response()->json(['success' => false, 'message' => '退货单不存在'], 404);
        }
        $return->loadMissing('returnedBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $return->returned_by,
            $return->returnedBy?->department_id,
            $return->store_id,
            $return->warehouse_id
        )) {
            return $resp;
        }

        if (in_array($return->status, ['processed', 'refunded'], true)) {
            return response()->json([
                'success' => true,
                'data' => new SalesReturnResource($return),
                'message' => '已处理，无需重复处理',
            ]);
        }

        if (!$return->sale_id) {
            return response()->json(['success' => false, 'message' => '该退货单未关联销售订单，无法生成库存/应收闭环'], 422);
        }

        if ($return->sale && $return->store_id !== null && $return->sale->store_id !== null
            && $return->sale->store_id !== $return->store_id
        ) {
            return $this->forbid('销售单与门店不匹配');
        }
        if ($return->customer && $return->store_id !== null && $return->customer->store_id !== null
            && $return->customer->store_id !== $return->store_id
        ) {
            return $this->forbid('客户与门店不匹配');
        }
        if ($return->warehouse && $return->store_id !== null && $return->warehouse->store_id !== null
            && $return->warehouse->store_id !== $return->store_id
        ) {
            return $this->forbid('仓库与门店不匹配');
        }

        $userId = $request->user()?->id ?? 1;

        try {
            DB::transaction(function () use ($return, $userId) {
                /** @var SalesOrder|null $sale */
                $sale = $return->sale;
                if (!$sale) {
                    throw ValidationException::withMessages(['sale_id' => ['关联销售订单不存在']]);
                }
                $saleTotal = (float)($sale->total_amount ?? 0);
                if ($saleTotal > 0 && (float)$return->total_amount > $saleTotal + 0.00001) {
                    throw ValidationException::withMessages([
                        'total_amount' => ['退货金额不能超过原销售单金额'],
                    ]);
                }

                // 1) 退回入库流水（幂等）
                $hasTx = InventoryTransaction::query()
                    ->where('reference_type', 'sales_return')
                    ->where('reference_id', $return->id)
                    ->exists();

                if (!$hasTx) {
                    $txOffset = 0;
                    foreach ($sale->items as $it) {
                        $qty = (float)$it->quantity;
                        $unit = $it->unit ?: ($it->product?->unit ?? '');
                        $unitCost = $it->product?->purchase_price;
                        $totalCost = $unitCost !== null ? $qty * (float)$unitCost : null;

                        InventoryTransaction::create([
                            'transaction_number' => $this->generateInventoryTransactionNumber($txOffset),
                            'product_id' => $it->product_id,
                            'warehouse_id' => $return->warehouse_id,
                            'store_id' => $return->store_id ?? $sale->store_id,
                            'transaction_type' => 'in',
                            'quantity' => $qty,
                            'unit' => $unit,
                            'unit_cost' => $unitCost,
                            'total_cost' => $totalCost,
                            'reference_type' => 'sales_return',
                            'reference_id' => $return->id,
                            'reason' => 'sales_return_in',
                            'created_by' => $userId,
                            'notes' => "销售退货入库：{$return->return_number}",
                        ]);
                        $txOffset++;
                    }
                }

                // 2) 冲减应收（按销售单一条应收）
                $receivable = AccountReceivable::query()
                    ->where('customer_id', $return->customer_id)
                    ->where('document_type', 'sales_order')
                    ->where('document_id', $return->sale_id)
                    ->lockForUpdate()
                    ->first();

                if ($receivable) {
                    $oldAmount = (float)$receivable->amount;
                    $oldPaid = (float)$receivable->paid_amount;
                    $reduce = min((float)$return->total_amount, $oldAmount);
                    $newAmount = max(0.0, $oldAmount - $reduce);
                    $newBalance = max(0.0, $newAmount - $oldPaid);
                    $today = now()->toDateString();
                    $due = $receivable->due_date?->format('Y-m-d') ?? $today;
                    $newStatus = $newBalance <= 0
                        ? 'paid'
                        : ($oldPaid > 0
                            ? 'partial'
                            : ($today > $due ? 'overdue' : 'unpaid'));

                    $receivable->forceFill([
                        'amount' => $newAmount,
                        'balance' => $newBalance,
                        'status' => $newStatus,
                    ])->save();

                    // 同步订单付款状态
                    $status = $newBalance <= 0 ? 'paid' : ($oldPaid > 0 ? 'partial' : 'unpaid');
                    SalesOrder::query()->whereKey($return->sale_id)->update(['payment_status' => $status]);
                }

                // 3) 更新退货单状态
                $return->forceFill(['status' => 'processed'])->save();

                $this->syncCustomerOutstanding((int)$return->customer_id);
            });

            $return->refresh()->load(['sale', 'customer', 'returnedBy', 'warehouse']);

            $this->audit($request, 'process', $return, null, null, '退货处理（入库+冲减应收）');

            return response()->json([
                'success' => true,
                'data' => new SalesReturnResource($return),
                'message' => '处理成功（已入库并冲减应收）',
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
     * 退款闭环：
     * - 计算应收“超收”部分并生成退款财务流水（payment）
     * - 回写应收 paid_amount/balance/status
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $return = SalesReturn::with(['sale.items.product', 'customer', 'returnedBy', 'warehouse'])->find($id);
        if (!$return) {
            return response()->json(['success' => false, 'message' => '退货单不存在'], 404);
        }

        // 权限校验
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $return->returned_by,
            $return->returnedBy?->department_id,
            $return->store_id,
            $return->warehouse_id
        )) {
            return $resp;
        }

        if ($return->status === 'refunded') {
            return response()->json([
                'success' => true,
                'data' => new SalesReturnResource($return),
                'message' => '该退货单已完成退款闭环',
            ]);
        }

        if (!$return->sale_id) {
            return response()->json(['success' => false, 'message' => '该退货单未关联销售订单，无法执行退款闭环'], 422);
        }

        $userId = $request->user()?->id ?? 1;
        $refundAmount = 0.0;

        try {
            DB::transaction(function () use ($return, $userId, &$refundAmount) {
                /** @var SalesOrder|null $sale */
                $sale = $return->sale;
                if (!$sale) {
                    throw ValidationException::withMessages(['sale_id' => ['关联销售订单不存在']]);
                }

                // ── 1) 若尚未处理，先执行处理逻辑（入库 + 冲减应收） ──
                if (in_array($return->status, ['pending', 'approved'], true)) {
                    $saleTotal = (float)($sale->total_amount ?? 0);
                    if ($saleTotal > 0 && (float)$return->total_amount > $saleTotal + 0.00001) {
                        throw ValidationException::withMessages([
                            'total_amount' => ['退货金额不能超过原销售单金额'],
                        ]);
                    }

                    // 退回入库流水（幂等）
                    $hasTx = InventoryTransaction::query()
                        ->where('reference_type', 'sales_return')
                        ->where('reference_id', $return->id)
                        ->exists();

                    if (!$hasTx && $sale->items->isNotEmpty()) {
                        $txOffset = 0;
                        foreach ($sale->items as $it) {
                            $qty = (float)$it->quantity;
                            $unit = $it->unit ?: ($it->product?->unit ?? '');
                            $unitCost = $it->product?->purchase_price;
                            $totalCost = $unitCost !== null ? $qty * (float)$unitCost : null;

                            InventoryTransaction::create([
                                'transaction_number' => $this->generateInventoryTransactionNumber($txOffset),
                                'product_id'   => $it->product_id,
                                'warehouse_id' => $return->warehouse_id,
                                'store_id'     => $return->store_id ?? $sale->store_id,
                                'transaction_type' => 'in',
                                'quantity'   => $qty,
                                'unit'       => $unit,
                                'unit_cost'  => $unitCost,
                                'total_cost' => $totalCost,
                                'reference_type' => 'sales_return',
                                'reference_id'   => $return->id,
                                'reason'     => 'sales_return_in',
                                'created_by' => $userId,
                                'notes'      => "销售退货入库：{$return->return_number}",
                            ]);
                            $txOffset++;
                        }
                    }

                    // 冲减应收
                    $receivable = AccountReceivable::query()
                        ->where('customer_id', $return->customer_id)
                        ->where('document_type', 'sales_order')
                        ->where('document_id', $return->sale_id)
                        ->lockForUpdate()
                        ->first();

                    if ($receivable) {
                        $oldAmount  = (float)$receivable->amount;
                        $oldPaid    = (float)$receivable->paid_amount;
                        $reduce     = min((float)$return->total_amount, $oldAmount);
                        $newAmount  = max(0.0, $oldAmount - $reduce);
                        $newBalance = max(0.0, $newAmount - $oldPaid);
                        $today = now()->toDateString();
                        $due = $receivable->due_date?->format('Y-m-d') ?? $today;
                        $newStatus = $newBalance <= 0
                            ? 'paid'
                            : ($oldPaid > 0 ? 'partial' : ($today > $due ? 'overdue' : 'unpaid'));

                        $receivable->forceFill([
                            'amount'  => $newAmount,
                            'balance' => $newBalance,
                            'status'  => $newStatus,
                        ])->save();

                        $payStatus = $newBalance <= 0 ? 'paid' : ($oldPaid > 0 ? 'partial' : 'unpaid');
                        SalesOrder::query()->whereKey($return->sale_id)->update(['payment_status' => $payStatus]);
                    }
                }

                // ── 2) 检查是否存在超收（已收 > 应收），需要退还给客户 ──
                $receivable = AccountReceivable::query()
                    ->where('customer_id', $return->customer_id)
                    ->where('document_type', 'sales_order')
                    ->where('document_id', $return->sale_id)
                    ->lockForUpdate()
                    ->first();

                if ($receivable) {
                    $amount   = (float)$receivable->amount;
                    $paid     = (float)$receivable->paid_amount;
                    $overpaid = max(0.0, $paid - $amount);

                    if ($overpaid > 0.01) {
                        // 生成退款财务流水
                        FinancialTransaction::create([
                            'transaction_number' => $this->generateFinancialTransactionNumber(),
                            'transaction_date'   => now()->toDateString(),
                            'type'     => 'payment',
                            'category' => 'customer_refund',
                            'amount'   => $overpaid,
                            'currency' => 'CNY',
                            'related_model_id'   => $receivable->id,
                            'related_model_type' => AccountReceivable::class,
                            'created_by' => $userId,
                            'status'      => 'posted',
                            'store_id'    => $return->store_id,
                            'description' => "退货退款 {$return->return_number}",
                            'notes'       => $return->reason ?: null,
                        ]);

                        // 回写应收的已收金额
                        $newPaid    = max(0.0, $paid - $overpaid);
                        $newBalance = max(0.0, $amount - $newPaid);
                        $today = now()->toDateString();
                        $due = $receivable->due_date?->format('Y-m-d') ?? $today;
                        $newStatus = $newBalance <= 0
                            ? 'paid'
                            : ($newPaid > 0 ? 'partial' : ($today > $due ? 'overdue' : 'unpaid'));

                        $receivable->forceFill([
                            'paid_amount' => $newPaid,
                            'balance'     => $newBalance,
                            'status'      => $newStatus,
                        ])->save();

                        $payStatus = $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
                        SalesOrder::query()->whereKey($return->sale_id)->update(['payment_status' => $payStatus]);

                        $refundAmount = $overpaid;
                    }
                }

                // ── 3) 标记退货单为已退款（退货流程闭环） ──
                $return->forceFill(['status' => 'refunded'])->save();
                $this->syncCustomerOutstanding((int)$return->customer_id);
            });

            $return->refresh()->load(['sale', 'customer', 'returnedBy', 'warehouse']);

            $this->audit($request, 'refund', $return, null, null,
                $refundAmount > 0
                    ? "退货退款：退还客户 ¥" . number_format($refundAmount, 2)
                    : '退货退款闭环（无需退还款项）'
            );

            $message = $refundAmount > 0
                ? "退款成功，已退还客户 ¥" . number_format($refundAmount, 2)
                : '退货流程已闭环（客户无需退还款项，应收已自动冲减）';

            return response()->json([
                'success' => true,
                'data' => new SalesReturnResource($return),
                'message' => $message,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '退款处理异常: ' . $e->getMessage(),
            ], 500);
        }
    }
}
