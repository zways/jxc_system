<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinancialTransactionResource;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\FinancialTransaction;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FinancialTransactionsController extends Controller
{
    private function resolveRelatedStoreId(?string $relatedType, ?int $relatedId): ?int
    {
        if (!$relatedType || !$relatedId) {
            return null;
        }
        return match ($relatedType) {
            AccountPayable::class => AccountPayable::query()->whereKey($relatedId)->value('store_id'),
            AccountReceivable::class => AccountReceivable::query()->whereKey($relatedId)->value('store_id'),
            PurchaseOrder::class => PurchaseOrder::query()->whereKey($relatedId)->value('store_id'),
            SalesOrder::class => SalesOrder::query()->whereKey($relatedId)->value('store_id'),
            Supplier::class => Supplier::query()->whereKey($relatedId)->value('store_id'),
            Customer::class => Customer::query()->whereKey($relatedId)->value('store_id'),
            default => null,
        };
    }

    private function ensureTransactionScope(Request $request, FinancialTransaction $transaction): ?JsonResponse
    {
        if ($this->isSuperAdmin($request)) {
            return null;
        }
        $transaction->loadMissing('createdBy');
        $resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $transaction->created_by,
            $transaction->createdBy?->department_id,
            $transaction->store_id,
            null,
            '无权操作该流水'
        );
        if ($resp) {
            return $resp;
        }

        $relatedStoreId = $this->resolveRelatedStoreId(
            $transaction->related_model_type,
            $transaction->related_model_id
        );
        if ($relatedStoreId !== null
            && $transaction->store_id !== null
            && $relatedStoreId !== $transaction->store_id
        ) {
            return $this->forbid('关联对象与门店不匹配');
        }
        return null;
    }

    private function syncSupplierOutstanding(int $supplierId): void
    {
        $outstanding = AccountPayable::query()
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'paid')
            ->sum('balance');
        Supplier::query()->whereKey($supplierId)->update(['outstanding_amount' => $outstanding]);
    }

    private function syncCustomerOutstanding(int $customerId): void
    {
        $outstanding = AccountReceivable::query()
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'paid')
            ->sum('balance');
        Customer::query()->whereKey($customerId)->update(['outstanding_amount' => $outstanding]);
    }

    private function syncPurchaseOrderPaymentStatusIfNeeded(AccountPayable $account): void
    {
        if ($account->document_type !== 'purchase_order') return;
        $order = PurchaseOrder::query()->find($account->document_id);
        if (!$order) return;

        $paid = (float)$account->paid_amount;
        $balance = (float)$account->balance;
        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $order->forceFill(['payment_status' => $status])->save();
    }

    private function syncSalesOrderPaymentStatusIfNeeded(AccountReceivable $account): void
    {
        if ($account->document_type !== 'sales_order') return;
        $order = SalesOrder::query()->find($account->document_id);
        if (!$order) return;

        $paid = (float)$account->paid_amount;
        $balance = (float)$account->balance;
        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $order->forceFill(['payment_status' => $status])->save();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FinancialTransaction::with(['createdBy']);
        $this->scopeByOwner($request, $query, 'created_by', 'createdBy', null, 'store_id');

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // 按交易类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // 按类别筛选
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // 按状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 按金额范围筛选
        if ($request->filled('min_amount') && $request->filled('max_amount')) {
            $query->whereBetween('amount', [
                $request->input('min_amount'),
                $request->input('max_amount')
            ]);
        }

        // 按日期范围筛选
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => FinancialTransactionResource::collection($transactions),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ]
            ],
            'message' => '流水列表获取成功'
        ]);
    }

    /**
     * 生成交易单号
     */
    private function generateTransactionNumber(): string
    {
        $prefix = 'FT';
        $date = date('Ymd');
        // withoutGlobalScopes: 单号必须全局唯一，不受 TenantScope 影响
        $lastTransaction = FinancialTransaction::withoutGlobalScopes()->withTrashed()
            ->where('transaction_number', 'like', "{$prefix}{$date}%")
            ->orderBy('transaction_number', 'desc')
            ->first();
        
        if ($lastTransaction) {
            $lastNumber = intval(substr($lastTransaction->transaction_number, -4));
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
                'type' => 'required|string|in:revenue,expense,payment,receipt',
                'category' => 'nullable|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'nullable|string|size:3',
                'transaction_date' => 'nullable|date',
                'description' => 'nullable|string|max:255',
                'related_model_id' => 'nullable|integer',
                'related_model_type' => 'nullable|string',
                'status' => 'nullable|string|in:draft,posted,voided',
                'store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
            ]);

            // 自动生成交易单号
            $validatedData['transaction_number'] = $this->generateTransactionNumber();
            // 设置默认值
            $validatedData['transaction_date'] = $validatedData['transaction_date'] ?? now()->toDateString();
            $validatedData['currency'] = $validatedData['currency'] ?? 'CNY';
            $validatedData['status'] = $validatedData['status'] ?? 'draft';
            // 以当前登录用户为准（避免全部落到默认用户导致审计失真）
            $validatedData['created_by'] = $request->user()?->id ?? 1;
            $validatedData['category'] = $validatedData['category'] ?? 'general';
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    throw ValidationException::withMessages(['store_id' => ['无权设置该门店']]);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $relatedStoreId = $this->resolveRelatedStoreId(
                $validatedData['related_model_type'] ?? null,
                $validatedData['related_model_id'] ?? null
            );
            if ($validatedData['store_id'] === null && $relatedStoreId !== null) {
                $validatedData['store_id'] = $relatedStoreId;
            }
            if ($relatedStoreId !== null && $validatedData['store_id'] !== null && $relatedStoreId !== $validatedData['store_id']) {
                throw ValidationException::withMessages(['store_id' => ['关联对象与门店不匹配']]);
            }

            $transaction = FinancialTransaction::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new FinancialTransactionResource($transaction),
                'message' => '流水创建成功'
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
        $transaction = FinancialTransaction::with(['createdBy'])->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => '流水不存在'
            ], 404);
        }
        if ($resp = $this->ensureTransactionScope($request, $transaction)) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new FinancialTransactionResource($transaction),
            'message' => '流水详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $transaction = FinancialTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => '流水不存在'
            ], 404);
        }
        if ($resp = $this->ensureTransactionScope($request, $transaction)) {
            return $resp;
        }

        try {
            $validatedData = $request->validate([
                'transaction_number' => 'sometimes|string|unique:financial_transactions,transaction_number,' . $id,
                'type' => 'sometimes|string|in:revenue,expense,payment,receipt',
                'category' => 'sometimes|string|max:255',
                'amount' => 'sometimes|numeric|min:0.01',
                'currency' => 'sometimes|string|size:3',
                'transaction_date' => 'sometimes|date',
                'description' => 'nullable|string|max:255',
                'related_model_id' => 'nullable|integer',
                'related_model_type' => 'nullable|string',
                'status' => 'sometimes|string|in:draft,posted,voided',
                'store_id' => 'nullable|exists:stores,id',
                'notes' => 'nullable|string',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    throw ValidationException::withMessages(['store_id' => ['无权设置该门店']]);
                }
                if (array_key_exists('store_id', $validatedData)) {
                    $validatedData['store_id'] = $userStoreId;
                }
            }

            $relatedType = $validatedData['related_model_type'] ?? $transaction->related_model_type;
            $relatedId = $validatedData['related_model_id'] ?? $transaction->related_model_id;
            $targetStoreId = array_key_exists('store_id', $validatedData) ? $validatedData['store_id'] : $transaction->store_id;
            $relatedStoreId = $this->resolveRelatedStoreId($relatedType, $relatedId);
            if ($targetStoreId === null && $relatedStoreId !== null) {
                $targetStoreId = $relatedStoreId;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($relatedStoreId !== null && $targetStoreId !== null && $relatedStoreId !== $targetStoreId) {
                throw ValidationException::withMessages(['store_id' => ['关联对象与门店不匹配']]);
            }

            $transaction->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new FinancialTransactionResource($transaction),
                'message' => '流水更新成功'
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
        $transaction = FinancialTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => '流水不存在'
            ], 404);
        }
        if ($resp = $this->ensureTransactionScope($request, $transaction)) {
            return $resp;
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => '流水删除成功'
        ]);
    }

    /**
     * 作废/冲销流水：将 status 置为 voided。
     * 若该流水为 payment/receipt 且关联应付/应收，则同步回滚已付/已收与余额状态。
     */
    public function void(Request $request, string $id): JsonResponse
    {
        try {
            // 先快速判断是否存在（避免在非事务中使用 lockForUpdate）
            if (!FinancialTransaction::query()->whereKey($id)->exists()) {
                return response()->json(['success' => false, 'message' => '流水不存在'], 404);
            }

            $precheck = FinancialTransaction::query()->find($id);
            if ($precheck && ($resp = $this->ensureTransactionScope($request, $precheck))) {
                return $resp;
            }

            $preVoidCheck = FinancialTransaction::query()->find($id);
            if ($preVoidCheck && $preVoidCheck->status === 'voided') {
                return response()->json(['success' => false, 'message' => '该流水已作废，请勿重复操作'], 422);
            }

            /** @var FinancialTransaction $tx */
            $tx = DB::transaction(function () use ($id) {
                /** @var FinancialTransaction $tx */
                $tx = FinancialTransaction::query()->lockForUpdate()->findOrFail($id);

                if ($tx->status === 'voided') {
                    return $tx; // 并发场景兜底
                }

                $tx->forceFill(['status' => 'voided'])->save();

                // 回滚应付
                if ($tx->type === 'payment'
                    && $tx->related_model_type === AccountPayable::class
                    && $tx->related_model_id
                ) {
                    /** @var AccountPayable|null $ap */
                    $ap = AccountPayable::query()->lockForUpdate()->find($tx->related_model_id);
                    if ($ap) {
                        $amount = (float)$ap->amount;
                        $paid = (float)$ap->paid_amount;
                        $rollback = (float)$tx->amount;
                        $newPaid = max(0.0, $paid - $rollback);
                        $newBalance = max(0.0, $amount - $newPaid);
                        $today = now()->toDateString();
                        $due = $ap->due_date?->format('Y-m-d') ?? $today;
                        $newStatus = $newBalance <= 0 ? 'paid' : ($today > $due ? 'overdue' : 'unpaid');

                        $ap->forceFill([
                            'paid_amount' => $newPaid,
                            'balance' => $newBalance,
                            'status' => $newStatus,
                        ])->save();

                        $this->syncSupplierOutstanding((int)$ap->supplier_id);
                        $this->syncPurchaseOrderPaymentStatusIfNeeded($ap);
                    }
                }

                // 回滚“供应商退款”（receipt 关联应付）：作废后需要把已付加回去
                if ($tx->type === 'receipt'
                    && $tx->related_model_type === AccountPayable::class
                    && $tx->related_model_id
                ) {
                    /** @var AccountPayable|null $ap */
                    $ap = AccountPayable::query()->lockForUpdate()->find($tx->related_model_id);
                    if ($ap) {
                        $amount = (float)$ap->amount;
                        $paid = (float)$ap->paid_amount;
                        $rollback = (float)$tx->amount;
                        $newPaid = $paid + $rollback;
                        $newBalance = max(0.0, $amount - $newPaid);
                        $today = now()->toDateString();
                        $due = $ap->due_date?->format('Y-m-d') ?? $today;
                        $newStatus = $newBalance <= 0 ? 'paid' : ($today > $due ? 'overdue' : 'unpaid');

                        $ap->forceFill([
                            'paid_amount' => $newPaid,
                            'balance' => $newBalance,
                            'status' => $newStatus,
                        ])->save();

                        $this->syncSupplierOutstanding((int)$ap->supplier_id);
                        $this->syncPurchaseOrderPaymentStatusIfNeeded($ap);
                    }
                }

                // 回滚应收
                if ($tx->type === 'receipt'
                    && $tx->related_model_type === AccountReceivable::class
                    && $tx->related_model_id
                ) {
                    /** @var AccountReceivable|null $ar */
                    $ar = AccountReceivable::query()->lockForUpdate()->find($tx->related_model_id);
                    if ($ar) {
                        $amount = (float)$ar->amount;
                        $paid = (float)$ar->paid_amount;
                        $rollback = (float)$tx->amount;
                        $newPaid = max(0.0, $paid - $rollback);
                        $newBalance = max(0.0, $amount - $newPaid);
                        $today = now()->toDateString();
                        $due = $ar->due_date?->format('Y-m-d') ?? $today;
                        $newStatus = $newBalance <= 0 ? 'paid' : ($today > $due ? 'overdue' : 'unpaid');

                        $ar->forceFill([
                            'paid_amount' => $newPaid,
                            'balance' => $newBalance,
                            'status' => $newStatus,
                        ])->save();

                        $this->syncCustomerOutstanding((int)$ar->customer_id);
                        $this->syncSalesOrderPaymentStatusIfNeeded($ar);
                    }
                }

                // 回滚“客户退款”（payment 关联应收）：作废后需要把已收加回去
                if ($tx->type === 'payment'
                    && $tx->related_model_type === AccountReceivable::class
                    && $tx->related_model_id
                ) {
                    /** @var AccountReceivable|null $ar */
                    $ar = AccountReceivable::query()->lockForUpdate()->find($tx->related_model_id);
                    if ($ar) {
                        $amount = (float)$ar->amount;
                        $paid = (float)$ar->paid_amount;
                        $rollback = (float)$tx->amount;
                        $newPaid = $paid + $rollback;
                        $newBalance = max(0.0, $amount - $newPaid);
                        $today = now()->toDateString();
                        $due = $ar->due_date?->format('Y-m-d') ?? $today;
                        $newStatus = $newBalance <= 0 ? 'paid' : ($today > $due ? 'overdue' : 'unpaid');

                        $ar->forceFill([
                            'paid_amount' => $newPaid,
                            'balance' => $newBalance,
                            'status' => $newStatus,
                        ])->save();

                        $this->syncCustomerOutstanding((int)$ar->customer_id);
                        $this->syncSalesOrderPaymentStatusIfNeeded($ar);
                    }
                }
                return $tx;
            });

            $this->audit($request, 'void', $tx, null, null, '财务流水作废');

            return response()->json([
                'success' => true,
                'data' => new FinancialTransactionResource($tx),
                'message' => $tx->status === 'voided' ? '作废成功' : 'OK',
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
