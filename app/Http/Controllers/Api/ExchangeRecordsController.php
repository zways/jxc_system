<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExchangeRecordResource;
use App\Models\ExchangeRecord;
use App\Models\InventoryTransaction;
use App\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ExchangeRecordsController extends Controller
{
    private function generateExchangeNumber(): string
    {
        $prefix = 'EX';
        $date = date('Ymd');
        $last = ExchangeRecord::withoutGlobalScopes()->withTrashed()->where('exchange_number', 'like', "{$prefix}{$date}%")
            ->orderBy('exchange_number', 'desc')
            ->first();

        $newNumber = '0001';
        if ($last) {
            $lastNumber = intval(substr($last->exchange_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }
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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExchangeRecord::with(['sale', 'customer', 'exchangedBy']);
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('exchanged_by', $user->id);
                    if ($user->department_id !== null) {
                        $q->orWhereHas('exchangedBy', function ($sub) use ($user) {
                            $sub->where('department_id', $user->department_id);
                        });
                    }
                    if ($user->store_id !== null) {
                        $q->orWhere('store_id', $user->store_id);
                    }
                    if ($user->warehouse_id !== null) {
                        $q->orWhereHas('sale', function ($sub) use ($user) {
                            $sub->where('warehouse_id', $user->warehouse_id);
                        });
                    }
                });
            }
        }

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('exchange_number', 'LIKE', "%{$search}%")
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
            $query->whereBetween('exchange_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $exchanges = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ExchangeRecordResource::collection($exchanges),
                'meta' => [
                    'current_page' => $exchanges->currentPage(),
                    'per_page' => $exchanges->perPage(),
                    'total' => $exchanges->total(),
                    'last_page' => $exchanges->lastPage(),
                ]
            ],
            'message' => '换货单列表获取成功'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'exchange_number' => 'nullable|string|unique:exchange_records,exchange_number',
                'sale_id' => 'required|exists:sales_orders,id',
                'customer_id' => 'required|exists:customers,id',
                'exchange_date' => 'required|date',
                'status' => 'nullable|string|in:pending,completed,cancelled',
                'reason' => 'required|string|max:255',
                'store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
            ]);

            if (empty($validatedData['exchange_number'] ?? null)) {
                $validatedData['exchange_number'] = $this->generateExchangeNumber();
            }
            $validatedData['status'] = $validatedData['status'] ?? 'pending';
            $validatedData['exchanged_by'] = $request->user()?->id ?? 1;
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
            $targetStoreId = $validatedData['store_id'];
            if ($targetStoreId === null) {
                $targetStoreId = $sale?->store_id ?? $customer?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($sale && $sale->store_id !== null && $sale->store_id !== $targetStoreId) {
                    return $this->forbid('销售单与门店不匹配');
                }
                if ($customer && $customer->store_id !== null && $customer->store_id !== $targetStoreId) {
                    return $this->forbid('客户与门店不匹配');
                }
            }

            $exchange = ExchangeRecord::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new ExchangeRecordResource($exchange),
                'message' => '换货单创建成功'
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
        $exchange = ExchangeRecord::with(['sale', 'customer', 'exchangedBy'])->find($id);

        if (!$exchange) {
            return response()->json([
                'success' => false,
                'message' => '换货单不存在'
            ], 404);
        }
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $exchange->exchanged_by,
            $exchange->exchangedBy?->department_id,
            $exchange->store_id,
            $exchange->sale?->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new ExchangeRecordResource($exchange),
            'message' => '换货单详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $exchange = ExchangeRecord::find($id);

        if (!$exchange) {
            return response()->json([
                'success' => false,
                'message' => '换货单不存在'
            ], 404);
        }
        $exchange->loadMissing(['exchangedBy', 'sale']);
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $exchange->exchanged_by,
            $exchange->exchangedBy?->department_id,
            $exchange->store_id,
            $exchange->sale?->warehouse_id
        )) {
            return $resp;
        }

        try {
            $validatedData = $request->validate([
                'exchange_number' => 'sometimes|string|unique:exchange_records,exchange_number,' . $id,
                'sale_id' => 'required|exists:sales_orders,id',
                'customer_id' => 'required|exists:customers,id',
                'exchange_date' => 'required|date',
                'status' => 'required|string|in:pending,completed,cancelled',
                'reason' => 'required|string|max:255',
                'store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
            ]);

            // exchanged_by 永远取当前用户（审计字段）
            $validatedData['exchanged_by'] = $request->user()?->id ?? ($exchange->exchanged_by ?? 1);
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                if (array_key_exists('store_id', $validatedData)) {
                    $validatedData['store_id'] = $userStoreId;
                }
            }

            $saleId = $validatedData['sale_id'] ?? $exchange->sale_id;
            $customerId = $validatedData['customer_id'] ?? $exchange->customer_id;
            $targetStoreId = array_key_exists('store_id', $validatedData) ? $validatedData['store_id'] : $exchange->store_id;
            $sale = $saleId ? SalesOrder::find($saleId) : null;
            $customer = $customerId ? Customer::find($customerId) : null;
            if ($targetStoreId === null) {
                $targetStoreId = $sale?->store_id ?? $customer?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($sale && $sale->store_id !== null && $sale->store_id !== $targetStoreId) {
                    return $this->forbid('销售单与门店不匹配');
                }
                if ($customer && $customer->store_id !== null && $customer->store_id !== $targetStoreId) {
                    return $this->forbid('客户与门店不匹配');
                }
            }

            $exchange->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new ExchangeRecordResource($exchange),
                'message' => '换货单更新成功'
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
        $exchange = ExchangeRecord::find($id);

        if (!$exchange) {
            return response()->json([
                'success' => false,
                'message' => '换货单不存在'
            ], 404);
        }
        $exchange->loadMissing(['exchangedBy', 'sale']);
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $exchange->exchanged_by,
            $exchange->exchangedBy?->department_id,
            $exchange->store_id,
            $exchange->sale?->warehouse_id
        )) {
            return $resp;
        }

        $exchange->delete();

        return response()->json([
            'success' => true,
            'message' => '换货单删除成功'
        ]);
    }

    /**
     * 换货完成闭环（简化实现）：
     * - 基于原销售单明细生成“退回入库(in)”与“发出出库(out)”两组库存流水，便于审计追溯
     * - warehouse_id 使用原销售单 warehouse_id
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $exchange = ExchangeRecord::with(['sale.items.product', 'customer'])->find($id);
        if (!$exchange) {
            return response()->json(['success' => false, 'message' => '换货单不存在'], 404);
        }
        $exchange->loadMissing(['exchangedBy', 'sale']);
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $exchange->exchanged_by,
            $exchange->exchangedBy?->department_id,
            $exchange->store_id,
            $exchange->sale?->warehouse_id
        )) {
            return $resp;
        }

        if ($exchange->status === 'completed') {
            return response()->json([
                'success' => true,
                'data' => new ExchangeRecordResource($exchange),
                'message' => '已完成',
            ]);
        }

        if ($exchange->sale && $exchange->store_id !== null && $exchange->sale->store_id !== null
            && $exchange->sale->store_id !== $exchange->store_id
        ) {
            return $this->forbid('销售单与门店不匹配');
        }
        if ($exchange->customer && $exchange->store_id !== null && $exchange->customer->store_id !== null
            && $exchange->customer->store_id !== $exchange->store_id
        ) {
            return $this->forbid('客户与门店不匹配');
        }

        $userId = $request->user()?->id ?? 1;

        try {
            DB::transaction(function () use ($exchange, $userId) {
                $sale = $exchange->sale;
                if (!$sale) {
                    throw ValidationException::withMessages(['sale_id' => ['关联销售订单不存在']]);
                }
                if (!$sale->warehouse_id) {
                    throw ValidationException::withMessages(['warehouse' => ['原销售单未指定仓库，无法生成换货库存流水']]);
                }

                $hasTx = InventoryTransaction::query()
                    ->where('reference_type', 'exchange_record')
                    ->where('reference_id', $exchange->id)
                    ->exists();
                if (!$hasTx) {
                    $txOffset = 0;
                    $targetStoreId = $exchange->store_id ?? $sale->store_id;
                    foreach ($sale->items as $it) {
                        $qty = (float)$it->quantity;
                        $unit = $it->unit ?: ($it->product?->unit ?? '');
                        $unitCost = $it->product?->purchase_price;
                        $totalCost = $unitCost !== null ? $qty * (float)$unitCost : null;
                        if ($it->product && $it->product->store_id !== null && $targetStoreId !== null
                            && $it->product->store_id !== $targetStoreId
                        ) {
                            throw ValidationException::withMessages(['items' => ['商品与门店不匹配']]);
                        }

                        // 1) 退回入库
                        InventoryTransaction::create([
                            'transaction_number' => $this->generateInventoryTransactionNumber($txOffset),
                            'product_id' => $it->product_id,
                            'warehouse_id' => $sale->warehouse_id,
                            'store_id' => $targetStoreId,
                            'transaction_type' => 'in',
                            'quantity' => $qty,
                            'unit' => $unit,
                            'unit_cost' => $unitCost,
                            'total_cost' => $totalCost,
                            'reference_type' => 'exchange_record',
                            'reference_id' => $exchange->id,
                            'reason' => 'exchange_in',
                            'created_by' => $userId,
                            'notes' => "换货退回入库：{$exchange->exchange_number}",
                        ]);
                        $txOffset++;

                        // 2) 发出出库（简化：按同数量发出）
                        InventoryTransaction::create([
                            'transaction_number' => $this->generateInventoryTransactionNumber($txOffset),
                            'product_id' => $it->product_id,
                            'warehouse_id' => $sale->warehouse_id,
                            'store_id' => $targetStoreId,
                            'transaction_type' => 'out',
                            'quantity' => $qty,
                            'unit' => $unit,
                            'unit_cost' => $unitCost,
                            'total_cost' => $totalCost,
                            'reference_type' => 'exchange_record',
                            'reference_id' => $exchange->id,
                            'reason' => 'exchange_out',
                            'created_by' => $userId,
                            'notes' => "换货发出出库：{$exchange->exchange_number}",
                        ]);
                        $txOffset++;
                    }
                }

                $exchange->forceFill([
                    'status' => 'completed',
                    'exchanged_by' => $userId,
                ])->save();
            });

            $exchange->refresh()->load(['sale', 'customer', 'exchangedBy']);

            $this->audit($request, 'process', $exchange, null, null, '换货完成');

            return response()->json([
                'success' => true,
                'data' => new ExchangeRecordResource($exchange),
                'message' => '换货完成成功',
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
