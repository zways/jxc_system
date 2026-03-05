<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountsPayableResource;
use App\Models\AccountPayable;
use App\Models\AccountsPayable;
use App\Models\FinancialTransaction;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AccountsPayableController extends Controller
{
    private function ensureAccountScope(Request $request, AccountsPayable $account): ?JsonResponse
    {
        if ($this->isSuperAdmin($request)) {
            return null;
        }
        if ($account->document_type !== 'purchase_order') {
            return $this->forbid('无权操作该应付');
        }
        $order = PurchaseOrder::with('createdBy')->find($account->document_id);
        if (!$order) {
            return $this->forbid('无权操作该应付');
        }
        $resp = $this->ensureOwnerOrSuperAdmin(
            $request,
            $order->created_by,
            $order->createdBy?->department_id,
            $order->store_id,
            $order->warehouse_id,
            '无权操作该应付'
        );
        if ($resp) {
            return $resp;
        }

        // 关联门店一致性
        if ($account->store_id !== null && $order->store_id !== null && $account->store_id !== $order->store_id) {
            return $this->forbid('应付与采购单门店不匹配');
        }
        $supplier = Supplier::find($account->supplier_id);
        if ($supplier && $supplier->store_id !== null && $account->store_id !== null && $supplier->store_id !== $account->store_id) {
            return $this->forbid('应付与供应商门店不匹配');
        }
        return null;
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

    private function syncSupplierOutstanding(int $supplierId): void
    {
        $outstanding = AccountPayable::query()
            ->where('supplier_id', $supplierId)
            ->where('status', '!=', 'paid')
            ->sum('balance');
        Supplier::query()->whereKey($supplierId)->update(['outstanding_amount' => $outstanding]);
    }

    private function syncPurchaseOrderPaymentStatusIfNeeded(AccountsPayable $account): void
    {
        if ($account->document_type !== 'purchase_order') return;
        $order = PurchaseOrder::query()->find($account->document_id);
        if (!$order) return;

        $paid = (float)$account->paid_amount;
        $balance = (float)$account->balance;
        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $order->forceFill(['payment_status' => $status])->save();
    }

    /**
     * 付款：更新应付已付/余额/状态，并生成财务流水（payment）。
     */
    public function pay(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $precheck = AccountsPayable::query()->find($id);
        if (!$precheck) {
            return response()->json(['success' => false, 'message' => '应付记录不存在'], 404);
        }
        if ($resp = $this->ensureAccountScope($request, $precheck)) {
            return $resp;
        }

        $userId = $request->user()?->id ?? 1;

        try {
            $account = DB::transaction(function () use ($id, $validated, $userId) {
                /** @var AccountsPayable|null $acc */
                $acc = AccountsPayable::query()->lockForUpdate()->find($id);
                if (!$acc) {
                    throw ValidationException::withMessages(['id' => ['应付记录不存在']]);
                }

                $amount = (float)$acc->amount;
                $paid = (float)$acc->paid_amount;
                $balance = max(0.0, (float)$acc->balance);

                $payAmount = (float)$validated['amount'];
                if ($payAmount > $balance + 0.00001) {
                    throw ValidationException::withMessages(['amount' => ["付款金额不能大于剩余应付（剩余 {$balance}）"]]);
                }

                $newPaid = $paid + $payAmount;
                $newBalance = max(0.0, $amount - $newPaid);
                $today = now()->toDateString();
                $due = $acc->due_date?->format('Y-m-d') ?? $today;
                $newStatus = $newBalance <= 0
                    ? 'paid'
                    : ($newPaid > 0
                        ? 'partial'
                        : ($today > $due ? 'overdue' : 'unpaid'));

                $acc->forceFill([
                    'paid_amount' => $newPaid,
                    'balance' => $newBalance,
                    'status' => $newStatus,
                    'notes' => $validated['notes'] ?? $acc->notes,
                ])->save();

                // 生成财务流水（付款）
                $txDate = $validated['transaction_date'] ?? $today;
                $desc = '付款';
                if ($acc->document_type === 'purchase_order') {
                    $po = PurchaseOrder::query()->find($acc->document_id);
                    if ($po) $desc .= " - 采购单 {$po->order_number}";
                }

                FinancialTransaction::create([
                    'transaction_number' => $this->generateFinancialTransactionNumber(),
                    'transaction_date' => $txDate,
                    'type' => 'payment',
                    'category' => 'supplier_payment',
                    'amount' => $payAmount,
                    'currency' => 'CNY',
                    'related_model_id' => $acc->id,
                    'related_model_type' => AccountPayable::class,
                    'created_by' => $userId,
                    'store_id' => $acc->store_id,
                    'status' => 'posted',
                    'description' => $desc,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->syncSupplierOutstanding((int)$acc->supplier_id);
                $this->syncPurchaseOrderPaymentStatusIfNeeded($acc);

                return $acc;
            });

            $this->audit($request, 'pay', $account, null, ['paid_amount' => (float)$account->paid_amount], '应付付款');

            return response()->json([
                'success' => true,
                'data' => new AccountsPayableResource($account),
                'message' => '付款成功',
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
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountsPayable::with(['supplier']);
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('document_type', 'purchase_order')
                            ->whereIn('document_id', PurchaseOrder::query()
                                ->where('created_by', $user->id)
                                ->select('id'));
                    });
                    if ($user->department_id !== null) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('document_type', 'purchase_order')
                                ->whereIn('document_id', PurchaseOrder::query()
                                    ->whereHas('createdBy', function ($u) use ($user) {
                                        $u->where('department_id', $user->department_id);
                                    })
                                    ->select('id'));
                        });
                    }
                    if ($user->store_id !== null) {
                        $q->orWhere('store_id', $user->store_id);
                    }
                    if ($user->warehouse_id !== null) {
                        $q->orWhere(function ($sub) use ($user) {
                            $sub->where('document_type', 'purchase_order')
                                ->whereIn('document_id', PurchaseOrder::query()
                                    ->where('warehouse_id', $user->warehouse_id)
                                    ->select('id'));
                        });
                    }
                });
            }
        }

        // 搜索功能
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('document_type', 'LIKE', "%{$search}%")
                    ->orWhere('document_id', 'LIKE', "%{$search}%")
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

        // 按金额范围筛选
        if ($request->filled('min_amount') && $request->filled('max_amount')) {
            $query->whereBetween('amount', [
                $request->input('min_amount'),
                $request->input('max_amount')
            ]);
        }

        // 按日期范围筛选
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('due_date', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $accounts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => AccountsPayableResource::collection($accounts),
                'meta' => [
                    'current_page' => $accounts->currentPage(),
                    'per_page' => $accounts->perPage(),
                    'total' => $accounts->total(),
                    'last_page' => $accounts->lastPage(),
                ]
            ],
            'message' => '应付列表获取成功'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                'store_id' => 'nullable|exists:stores,id',
                'document_type' => 'required|string',
                'document_id' => 'required|integer',
                'document_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'paid_amount' => 'required|numeric|min:0',
                'balance' => 'required|numeric|min:0',
                'due_date' => 'required|date',
                'status' => 'required|string|in:unpaid,partial,paid,overdue',
                'notes' => 'nullable|string',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $supplier = Supplier::find($validatedData['supplier_id']);
            $targetStoreId = $validatedData['store_id'];
            $order = null;
            if ($validatedData['document_type'] === 'purchase_order') {
                $order = PurchaseOrder::find($validatedData['document_id']);
            }
            if ($targetStoreId === null) {
                $targetStoreId = $supplier?->store_id ?? $order?->store_id;
                $validatedData['store_id'] = $targetStoreId;
            }
            if ($targetStoreId !== null) {
                if ($supplier && $supplier->store_id !== null && $supplier->store_id !== $targetStoreId) {
                    return $this->forbid('供应商与门店不匹配');
                }
                if ($order && $order->store_id !== null && $order->store_id !== $targetStoreId) {
                    return $this->forbid('采购单与门店不匹配');
                }
            }

            $account = AccountsPayable::create($validatedData);
            $this->syncSupplierOutstanding((int)$account->supplier_id);
            $this->syncPurchaseOrderPaymentStatusIfNeeded($account);

            return response()->json([
                'success' => true,
                'data' => new AccountsPayableResource($account),
                'message' => '应付记录创建成功'
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
        $account = AccountsPayable::with(['supplier'])->find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => '应付记录不存在'
            ], 404);
        }
        if ($resp = $this->ensureAccountScope($request, $account)) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => new AccountsPayableResource($account),
            'message' => '应付详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $precheck = AccountsPayable::query()->find($id);
        if (!$precheck) {
            return response()->json([
                'success' => false,
                'message' => '应付记录不存在'
            ], 404);
        }
        if ($resp = $this->ensureAccountScope($request, $precheck)) {
            return $resp;
        }

        try {
            $account = DB::transaction(function () use ($request, $id) {
                /** @var AccountsPayable|null $acc */
                $acc = AccountsPayable::query()->lockForUpdate()->find($id);
                if (!$acc) {
                    return null;
                }

                $oldSupplierId = (int)$acc->supplier_id;
                $validatedData = $request->validate([
                    'supplier_id' => 'required|exists:suppliers,id',
                    'store_id' => 'nullable|exists:stores,id',
                    'document_type' => 'required|string',
                    'document_id' => 'required|integer',
                    'document_date' => 'required|date',
                    'amount' => 'required|numeric|min:0.01',
                    'paid_amount' => 'required|numeric|min:0',
                    'balance' => 'required|numeric|min:0',
                    'due_date' => 'required|date',
                    'status' => 'required|string|in:unpaid,partial,paid,overdue',
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

                $supplier = Supplier::find($validatedData['supplier_id']);
                $targetStoreId = array_key_exists('store_id', $validatedData) ? $validatedData['store_id'] : $acc->store_id;
                $order = null;
                if ($validatedData['document_type'] === 'purchase_order') {
                    $order = PurchaseOrder::find($validatedData['document_id']);
                }
                if ($targetStoreId === null) {
                    $targetStoreId = $supplier?->store_id ?? $order?->store_id;
                    $validatedData['store_id'] = $targetStoreId;
                }
                if ($targetStoreId !== null) {
                    if ($supplier && $supplier->store_id !== null && $supplier->store_id !== $targetStoreId) {
                        return $this->forbid('供应商与门店不匹配');
                    }
                    if ($order && $order->store_id !== null && $order->store_id !== $targetStoreId) {
                        return $this->forbid('采购单与门店不匹配');
                    }
                }

                $acc->update($validatedData);

                $newSupplierId = (int)$acc->supplier_id;
                if ($oldSupplierId !== $newSupplierId) {
                    $this->syncSupplierOutstanding($oldSupplierId);
                }
                $this->syncSupplierOutstanding($newSupplierId);
                $this->syncPurchaseOrderPaymentStatusIfNeeded($acc);

                return $acc;
            });

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => '应付记录不存在'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new AccountsPayableResource($account),
                'message' => '应付记录更新成功'
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
        $account = AccountsPayable::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => '应付记录不存在'
            ], 404);
        }
        if ($resp = $this->ensureAccountScope($request, $account)) {
            return $resp;
        }

        $supplierId = (int)$account->supplier_id;
        $account->delete();
        $this->syncSupplierOutstanding($supplierId);

        return response()->json([
            'success' => true,
            'message' => '应付记录删除成功'
        ]);
    }
}
