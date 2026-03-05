<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\AccountsPayable;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuppliersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::with('store');
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
                    ->orWhere('supplier_code', 'LIKE', "%{$search}%")
                    ->orWhere('contact_person', 'LIKE', "%{$search}%");
            });
        }

        // 按状态筛选
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $suppliers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => SupplierResource::collection($suppliers),
                'meta' => [
                    'current_page' => $suppliers->currentPage(),
                    'per_page' => $suppliers->perPage(),
                    'total' => $suppliers->total(),
                    'last_page' => $suppliers->lastPage(),
                ]
            ],
            'message' => '供应商列表获取成功'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $storeId = $request->user()?->store_id;
            $validatedData = $request->validate([
                'supplier_code' => [
                    'required', 'string',
                    Rule::unique('suppliers', 'supplier_code')->where('store_id', $storeId),
                ],
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'tax_number' => 'nullable|string|max:50',
                'credit_limit' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|string|max:255',
                'rating' => 'nullable|integer|min:1|max:5',
                'notes' => 'nullable|string',
                'is_active' => 'boolean',
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

            $supplier = Supplier::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new SupplierResource($supplier),
                'message' => '供应商创建成功'
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
        $supplier = Supplier::with('store')->find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => '供应商不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $supplier->store_id)) {
            return $this->forbid('无权访问该供应商');
        }

        return response()->json([
            'success' => true,
            'data' => new SupplierResource($supplier),
            'message' => '供应商详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => '供应商不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $supplier->store_id)) {
            return $this->forbid('无权修改该供应商');
        }

        try {
            $storeId = $supplier->store_id ?? $request->user()?->store_id;
            $validatedData = $request->validate([
                'supplier_code' => [
                    'sometimes', 'string',
                    Rule::unique('suppliers', 'supplier_code')->where('store_id', $storeId)->ignore($id),
                ],
                'name' => 'sometimes|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'tax_number' => 'nullable|string|max:50',
                'credit_limit' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|string|max:255',
                'rating' => 'nullable|integer|min:1|max:5',
                'notes' => 'nullable|string',
                'is_active' => 'boolean',
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

            $supplier->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new SupplierResource($supplier),
                'message' => '供应商更新成功'
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
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => '供应商不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $supplier->store_id)) {
            return $this->forbid('无权删除该供应商');
        }

        $sid = (int)$supplier->id;
        $refs = [
            [PurchaseOrder::class, '采购单'],
            [AccountsPayable::class, '应付单'],
        ];
        foreach ($refs as [$model, $label]) {
            if ($model::where('supplier_id', $sid)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "该供应商下仍有{$label}记录，不可删除"
                ], 400);
            }
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => '供应商删除成功'
        ]);
    }
}
