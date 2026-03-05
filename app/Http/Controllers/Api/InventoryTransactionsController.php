<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InventoryTransactionsController extends Controller
{
    /**
     * 生成库存流水号
     */
    private function generateTransactionNumber(): string
    {
        $prefix = 'IT';
        $date = date('Ymd');
        $last = InventoryTransaction::withoutGlobalScopes()->withTrashed()->where('transaction_number', 'like', "{$prefix}{$date}%")
            ->orderBy('transaction_number', 'desc')
            ->first();

        $newNumber = '0001';
        if ($last) {
            $lastNumber = intval(substr($last->transaction_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        }
        return "{$prefix}{$date}{$newNumber}";
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventoryTransaction::with(['product', 'warehouse']);
        $this->scopeByOwner($request, $query, 'created_by', 'createdBy', 'warehouse_id', 'store_id');

        // 搜索功能（流水号/商品名/批次号/序列号）
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'LIKE', "%{$search}%")
                    ->orWhere('batch_number', 'LIKE', "%{$search}%")
                    ->orWhere('serial_number', 'LIKE', "%{$search}%")
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

        // 按交易类型筛选（调拨在库中存为 in/out + reference_type=transfer，此处统一按 reference_type 查）
        if ($request->filled('transaction_type')) {
            $type = $request->input('transaction_type');
            if ($type === 'transfer') {
                $query->where('reference_type', 'transfer');
            } else {
                $query->where('transaction_type', $type);
            }
        }

        // 按日期范围筛选（使用 created_at，表无 transaction_date 字段）
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->input('start_date') . ' 00:00:00',
                $request->input('end_date') . ' 23:59:59'
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => InventoryTransactionResource::collection($transactions),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ]
            ],
            'message' => '库存流水列表获取成功'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $rules = [
                'transaction_number' => 'nullable|string|unique:inventory_transactions,transaction_number',
                'product_id' => 'required|exists:products,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'store_id' => 'nullable|exists:stores,id',
                'transaction_type' => 'required|string|in:in,out,adjust,transfer',
                'quantity' => 'required|numeric|not_in:0',
                'unit' => 'nullable|string|max:50',
                'unit_cost' => 'nullable|numeric|min:0',
                'total_cost' => 'nullable|numeric|min:0',
                'reference_type' => 'nullable|string|max:50',
                'reference_id' => 'nullable|integer',
                'batch_number' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'serial_numbers' => 'nullable|array',
                'serial_numbers.*' => 'string|max:100',
                'production_date' => 'nullable|date',
                'expiry_date' => 'nullable|date',
                'reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ];
            $validatedData = $request->validate($rules);

            $product = Product::find($validatedData['product_id']);
            if ($product && $product->track_batch && in_array($validatedData['transaction_type'], ['in', 'out'], true)) {
                if (empty($validatedData['batch_number']) && empty($validatedData['serial_numbers'])) {
                    throw ValidationException::withMessages(['batch_number' => ['该商品启用批次管理，请填写批次号']]);
                }
            }
            if ($product && $product->track_serial && in_array($validatedData['transaction_type'], ['in', 'out'], true)) {
                $serials = $validatedData['serial_numbers'] ?? (isset($validatedData['serial_number']) ? [$validatedData['serial_number']] : []);
                $serials = array_values(array_filter(array_map(function ($s) { return is_string($s) ? trim($s) : ''; }, $serials)));
                if (empty($serials)) {
                    throw ValidationException::withMessages(['serial_numbers' => ['该商品启用序列号管理，请填写序列号']]);
                }
                $qty = (int) $validatedData['quantity'];
                if (count($serials) !== $qty) {
                    throw ValidationException::withMessages(['serial_numbers' => ['序列号数量须与数量一致（当前数量：' . $qty . '）']]);
                }
            }

            if (in_array($validatedData['transaction_type'], ['in', 'out', 'transfer'], true)
                && (float)$validatedData['quantity'] <= 0
            ) {
                throw ValidationException::withMessages([
                    'quantity' => ['入库/出库/调拨数量必须大于0'],
                ]);
            }

            $validatedData['created_by'] = $request->user()?->id ?? 1;
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    throw ValidationException::withMessages(['store_id' => ['无权设置该门店']]);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $warehouse = Warehouse::find($validatedData['warehouse_id']);
            $targetStoreId = $validatedData['store_id'];
            if ($targetStoreId === null) {
                $targetStoreId = $product?->store_id ?? $warehouse?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($product && $product->store_id !== null && $product->store_id !== $targetStoreId) {
                    throw ValidationException::withMessages(['product_id' => ['商品与门店不匹配']]);
                }
                if ($warehouse && $warehouse->store_id !== null && $warehouse->store_id !== $targetStoreId) {
                    throw ValidationException::withMessages(['warehouse_id' => ['仓库与门店不匹配']]);
                }
            }

            if (empty($validatedData['unit'])) {
                $validatedData['unit'] = $product?->unit ?? '';
            }

            $serialNumbers = $validatedData['serial_numbers'] ?? null;
            if (is_array($serialNumbers) && count($serialNumbers) > 0) {
                // 按序列号逐条创建：每条 quantity=1，一条一个 serial_number
                $created = [];
                foreach ($serialNumbers as $sn) {
                    $sn = is_string($sn) ? trim($sn) : '';
                    if ($sn === '') {
                        continue;
                    }
                    $row = array_merge($validatedData, [
                        'transaction_number' => null,
                        'quantity' => 1,
                        'serial_number' => $sn,
                        'batch_number' => $validatedData['batch_number'] ?? null,
                        'production_date' => $validatedData['production_date'] ?? null,
                        'expiry_date' => $validatedData['expiry_date'] ?? null,
                    ]);
                    unset($row['serial_numbers']);
                    if (empty($row['transaction_number'])) {
                        $row['transaction_number'] = $this->generateTransactionNumber();
                    }
                    $row['total_cost'] = isset($row['unit_cost']) ? (float) $row['unit_cost'] * 1 : null;
                    $created[] = InventoryTransaction::create($row);
                }
                return response()->json([
                    'success' => true,
                    'data' => InventoryTransactionResource::collection(collect($created)),
                    'message' => '库存流水创建成功',
                ], 201);
            }

            if (empty($validatedData['transaction_number'])) {
                $validatedData['transaction_number'] = $this->generateTransactionNumber();
            }
            if (isset($validatedData['unit_cost']) && !isset($validatedData['total_cost'])) {
                $validatedData['total_cost'] = $validatedData['unit_cost'] * $validatedData['quantity'];
            }
            unset($validatedData['serial_numbers']);
            $transaction = InventoryTransaction::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new InventoryTransactionResource($transaction),
                'message' => '库存流水创建成功'
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
        $transaction = InventoryTransaction::with(['product', 'warehouse', 'createdBy'])->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => '库存流水不存在'
            ], 404);
        }
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $transaction->created_by,
            $transaction->createdBy?->department_id,
            $transaction->store_id,
            $transaction->warehouse_id
        )) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new InventoryTransactionResource($transaction),
            'message' => '库存流水详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $transaction = InventoryTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => '库存流水不存在'
            ], 404);
        }
        $transaction->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $transaction->created_by,
            $transaction->createdBy?->department_id,
            $transaction->store_id,
            $transaction->warehouse_id
        )) {
            return $resp;
        }

        try {
            $validatedData = $request->validate([
                'transaction_number' => 'nullable|string|unique:inventory_transactions,transaction_number,' . $id,
                'product_id' => 'required|exists:products,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'transaction_type' => 'required|string|in:in,out,adjust,transfer',
                'quantity' => 'required|numeric|not_in:0',
                'unit' => 'nullable|string|max:50',
                'unit_cost' => 'nullable|numeric|min:0',
                'total_cost' => 'nullable|numeric|min:0',
                'reference_type' => 'nullable|string|max:50',
                'reference_id' => 'nullable|integer',
                'batch_number' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'production_date' => 'nullable|date',
                'expiry_date' => 'nullable|date',
                'reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);
            $product = Product::find($validatedData['product_id']);
            if ($product && $product->track_batch && in_array($validatedData['transaction_type'], ['in', 'out'], true) && empty($validatedData['batch_number'])) {
                throw ValidationException::withMessages(['batch_number' => ['该商品启用批次管理，请填写批次号']]);
            }
            if ($product && $product->track_serial && in_array($validatedData['transaction_type'], ['in', 'out'], true) && empty($validatedData['serial_number'])) {
                throw ValidationException::withMessages(['serial_number' => ['该商品启用序列号管理，请填写序列号']]);
            }

            if (in_array($validatedData['transaction_type'], ['in', 'out', 'transfer'], true)
                && (float)$validatedData['quantity'] <= 0
            ) {
                throw ValidationException::withMessages([
                    'quantity' => ['入库/出库/调拨数量必须大于0'],
                ]);
            }

            if (empty($validatedData['transaction_number'])) {
                $validatedData['transaction_number'] = $transaction->transaction_number ?: $this->generateTransactionNumber();
            }
            if (empty($validatedData['unit'])) {
                $p = Product::find($validatedData['product_id']);
                $validatedData['unit'] = $p?->unit ?? ($transaction->unit ?? '');
            }

            // 自动计算 total_cost（如果提供了 unit_cost）
            if (isset($validatedData['unit_cost']) && !isset($validatedData['total_cost'])) {
                $validatedData['total_cost'] = $validatedData['unit_cost'] * $validatedData['quantity'];
            }

            $transaction->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new InventoryTransactionResource($transaction),
                'message' => '库存流水更新成功'
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
     * 可用批次列表（指定商品+仓库下库存余额>0 的批次）
     * GET inventory-transactions/batches-available?product_id=1&warehouse_id=1
     */
    public function batchesAvailable(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'   => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);
        $query = InventoryTransaction::query()
            ->where('product_id', $request->input('product_id'))
            ->where('warehouse_id', $request->input('warehouse_id'));
        $this->scopeByOwner($request, $query, 'created_by', 'createdBy', 'warehouse_id', 'store_id');

        $rows = $query->selectRaw("
                batch_number,
                MIN(production_date) AS production_date,
                MIN(expiry_date) AS expiry_date,
                SUM(CASE WHEN transaction_type = 'in' THEN quantity WHEN transaction_type = 'out' THEN -quantity ELSE quantity END) AS quantity
            ")
            ->whereNotNull('batch_number')
            ->where('batch_number', '!=', '')
            ->groupBy('batch_number')
            ->havingRaw('SUM(CASE WHEN transaction_type = \'in\' THEN quantity WHEN transaction_type = \'out\' THEN -quantity ELSE quantity END) > 0')
            ->orderBy('batch_number')
            ->get();

        $list = $rows->map(fn ($r) => [
            'batch_number'   => $r->batch_number,
            'production_date' => $r->production_date?->format('Y-m-d'),
            'expiry_date'    => $r->expiry_date?->format('Y-m-d'),
            'quantity'       => round((float) $r->quantity, 2),
        ]);

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * 可用序列号列表（指定商品+仓库下库存余额>0 的序列号）
     * GET inventory-transactions/serials-available?product_id=1&warehouse_id=1
     */
    public function serialsAvailable(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'   => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);
        $query = InventoryTransaction::query()
            ->where('product_id', $request->input('product_id'))
            ->where('warehouse_id', $request->input('warehouse_id'));
        $this->scopeByOwner($request, $query, 'created_by', 'createdBy', 'warehouse_id', 'store_id');

        $rows = $query->selectRaw("
                serial_number,
                SUM(CASE WHEN transaction_type = 'in' THEN quantity WHEN transaction_type = 'out' THEN -quantity ELSE quantity END) AS quantity
            ")
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->groupBy('serial_number')
            ->havingRaw('SUM(CASE WHEN transaction_type = \'in\' THEN quantity WHEN transaction_type = \'out\' THEN -quantity ELSE quantity END) > 0')
            ->orderBy('serial_number')
            ->get();

        $list = $rows->map(fn ($r) => ['serial_number' => $r->serial_number]);

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * 原子调拨操作 — 在同一个数据库事务中完成出库+入库
     * 支持批次管理：传 batch_number、production_date、expiry_date 带出带入
     * 支持序列号管理：传 serial_numbers 数组，按序列号逐条出库+入库
     */
    public function transfer(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id'        => 'required|exists:products,id',
                'from_warehouse_id' => 'required|exists:warehouses,id',
                'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
                'quantity'          => 'required|numeric|min:0.01',
                'unit_cost'         => 'nullable|numeric|min:0',
                'batch_number'      => 'nullable|string|max:100',
                'production_date'   => 'nullable|date',
                'expiry_date'       => 'nullable|date',
                'serial_numbers'    => 'nullable|array',
                'serial_numbers.*'  => 'string|max:100',
                'reason'            => 'nullable|string|max:255',
                'notes'             => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors'  => $e->errors(),
            ], 422);
        }

        $userId = $request->user()?->id ?? 1;
        $product = Product::find($validated['product_id']);
        $unitName = $product?->unit ?? '';
        $serialNumbers = isset($validated['serial_numbers']) ? array_values(array_filter(array_map('trim', $validated['serial_numbers']))) : [];

        if ($product && $product->track_batch && empty($validated['batch_number']) && empty($serialNumbers)) {
            throw ValidationException::withMessages(['batch_number' => ['该商品启用批次管理，请选择或填写批次号']]);
        }
        if ($product && $product->track_serial) {
            if (empty($serialNumbers)) {
                throw ValidationException::withMessages(['serial_numbers' => ['该商品启用序列号管理，请选择要调拨的序列号']]);
            }
            if (count($serialNumbers) !== (int) $validated['quantity']) {
                throw ValidationException::withMessages(['serial_numbers' => ['序列号数量须与调拨数量一致']]);
            }
        }

        try {
            $result = \DB::transaction(function () use ($validated, $userId, $unitName, $serialNumbers) {
                $batchNumber = $validated['batch_number'] ?? null;
                $productionDate = $validated['production_date'] ?? null;
                $expiryDate = $validated['expiry_date'] ?? null;

                if (!empty($serialNumbers)) {
                    $outs = [];
                    $ins = [];
                    foreach ($serialNumbers as $sn) {
                        $outNumber = $this->generateTransactionNumber();
                        $out = InventoryTransaction::create([
                            'transaction_number' => $outNumber,
                            'product_id'         => $validated['product_id'],
                            'warehouse_id'       => $validated['from_warehouse_id'],
                            'transaction_type'   => 'out',
                            'quantity'           => 1,
                            'unit'               => $unitName,
                            'unit_cost'          => $validated['unit_cost'] ?? null,
                            'total_cost'         => isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
                            'reference_type'     => 'transfer',
                            'serial_number'     => $sn,
                            'batch_number'      => $batchNumber,
                            'production_date'   => $productionDate,
                            'expiry_date'       => $expiryDate,
                            'reason'            => $validated['reason'] ?? 'warehouse_transfer',
                            'notes'              => $validated['notes'] ?? '调拨出库',
                            'created_by'         => $userId,
                        ]);
                        $inNumber = $this->generateTransactionNumber();
                        $in = InventoryTransaction::create([
                            'transaction_number' => $inNumber,
                            'product_id'         => $validated['product_id'],
                            'warehouse_id'       => $validated['to_warehouse_id'],
                            'transaction_type'   => 'in',
                            'quantity'           => 1,
                            'unit'               => $unitName,
                            'unit_cost'          => $validated['unit_cost'] ?? null,
                            'total_cost'         => isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
                            'reference_type'     => 'transfer',
                            'reference_id'       => $out->id,
                            'serial_number'     => $sn,
                            'batch_number'      => $batchNumber,
                            'production_date'   => $productionDate,
                            'expiry_date'       => $expiryDate,
                            'reason'             => $validated['reason'] ?? 'warehouse_transfer',
                            'notes'              => $validated['notes'] ?? '调拨入库',
                            'created_by'         => $userId,
                        ]);
                        $outs[] = $out;
                        $ins[] = $in;
                    }
                    return ['out' => $outs, 'in' => $ins];
                }

                $outNumber = $this->generateTransactionNumber();
                $out = InventoryTransaction::create([
                    'transaction_number' => $outNumber,
                    'product_id'         => $validated['product_id'],
                    'warehouse_id'       => $validated['from_warehouse_id'],
                    'transaction_type'   => 'out',
                    'quantity'           => $validated['quantity'],
                    'unit'               => $unitName,
                    'unit_cost'          => $validated['unit_cost'] ?? null,
                    'total_cost'         => isset($validated['unit_cost']) ? $validated['unit_cost'] * $validated['quantity'] : null,
                    'reference_type'     => 'transfer',
                    'batch_number'      => $batchNumber,
                    'production_date'   => $productionDate,
                    'expiry_date'       => $expiryDate,
                    'reason'             => $validated['reason'] ?? 'warehouse_transfer',
                    'notes'              => $validated['notes'] ?? '调拨出库',
                    'created_by'         => $userId,
                ]);

                $inNumber = $this->generateTransactionNumber();
                $in = InventoryTransaction::create([
                    'transaction_number' => $inNumber,
                    'product_id'         => $validated['product_id'],
                    'warehouse_id'       => $validated['to_warehouse_id'],
                    'transaction_type'   => 'in',
                    'quantity'           => $validated['quantity'],
                    'unit'               => $unitName,
                    'unit_cost'          => $validated['unit_cost'] ?? null,
                    'total_cost'         => isset($validated['unit_cost']) ? $validated['unit_cost'] * $validated['quantity'] : null,
                    'reference_type'     => 'transfer',
                    'reference_id'       => $out->id,
                    'batch_number'      => $batchNumber,
                    'production_date'   => $productionDate,
                    'expiry_date'       => $expiryDate,
                    'reason'             => $validated['reason'] ?? 'warehouse_transfer',
                    'notes'              => $validated['notes'] ?? '调拨入库',
                    'created_by'         => $userId,
                ]);

                return ['out' => $out, 'in' => $in];
            });

            $outData = $result['out'];
            $inData = $result['in'];
            $outResource = is_array($outData)
                ? InventoryTransactionResource::collection(collect($outData))
                : new InventoryTransactionResource($outData);
            $inResource = is_array($inData)
                ? InventoryTransactionResource::collection(collect($inData))
                : new InventoryTransactionResource($inData);
            return response()->json([
                'success' => true,
                'data'    => [
                    'out' => $outResource,
                    'in'  => $inResource,
                ],
                'message' => '调拨成功',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '调拨失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 库存汇总：按商品 ID 聚合所有库存流水，返回每个商品的当前库存量。
     * GET /api/v1/inventory-transactions/stock-summary
     *
     * 超管增强：
     *  - 支持 ?store_id=X 按企业筛选
     *  - 支持 ?detail=1  返回带商品/仓库名称和企业维度的详细列表
     */
    public function stockSummary(Request $request): JsonResponse
    {
        $query = InventoryTransaction::query();

        // 企业隔离 — scopeByOwner 已支持超管通过 ?store_id 缩小范围
        $this->scopeByOwner($request, $query, 'created_by', 'createdBy', 'warehouse_id', 'store_id');

        // 可选按仓库筛选
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        // ── 详细模式（超管可用）：按 product + warehouse + store 分组，附带名称 ──
        if ($request->boolean('detail') && $this->isSuperAdmin($request)) {
            $rows = $query->selectRaw("
                    product_id, warehouse_id, store_id,
                    SUM(CASE
                        WHEN transaction_type = 'in'  THEN quantity
                        WHEN transaction_type = 'out' THEN -quantity
                        ELSE quantity
                    END) AS current_stock
                ")
                ->groupBy('product_id', 'warehouse_id', 'store_id')
                ->having('current_stock', '!=', 0)
                ->get();

            // 批量预加载名称，避免 N+1
            $productIds   = $rows->pluck('product_id')->unique();
            $warehouseIds = $rows->pluck('warehouse_id')->unique();
            $storeIds     = $rows->pluck('store_id')->unique();

            $products   = Product::whereIn('id', $productIds)->pluck('name', 'id');
            $prodCodes  = Product::whereIn('id', $productIds)->pluck('code', 'id');
            $warehouses = Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id');
            $stores     = \App\Models\Store::whereIn('id', $storeIds)->pluck('name', 'id');

            $list = $rows->map(fn ($r) => [
                'product_id'     => $r->product_id,
                'product_code'   => $prodCodes[$r->product_id] ?? '',
                'product_name'   => $products[$r->product_id] ?? '',
                'warehouse_id'   => $r->warehouse_id,
                'warehouse_name' => $warehouses[$r->warehouse_id] ?? '',
                'store_id'       => $r->store_id,
                'store_name'     => $stores[$r->store_id] ?? '',
                'current_stock'  => round((float) $r->current_stock, 2),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $list->values(),
                'message' => '库存汇总获取成功',
            ]);
        }

        // ── 简洁模式（默认）：向前兼容，返回 {product_id: stock} 映射 ──
        $rows = $query->selectRaw("
            product_id,
            SUM(CASE
                WHEN transaction_type = 'in'  THEN quantity
                WHEN transaction_type = 'out' THEN -quantity
                ELSE quantity
            END) AS current_stock
        ")
            ->groupBy('product_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->product_id] = round((float) $row->current_stock, 2);
        }

        return response()->json([
            'success' => true,
            'data' => $map,
            'message' => '库存汇总获取成功',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $transaction = InventoryTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => '库存流水不存在'
            ], 404);
        }
        $transaction->loadMissing('createdBy');
        if ($resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $transaction->created_by,
            $transaction->createdBy?->department_id,
            $transaction->store_id,
            $transaction->warehouse_id
        )) {
            return $resp;
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => '库存流水删除成功'
        ]);
    }
}
