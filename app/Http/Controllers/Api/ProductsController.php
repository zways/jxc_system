<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'store']);
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user || $user->store_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        // 搜索功能（支持名称、编码、分类、条形码）
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('barcode', 'LIKE', "%{$search}%")
                    ->orWhereHas('category', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // 按分类筛选
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // 按状态筛选
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ProductResource::collection($products),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ]
            ],
            'message' => '商品列表获取成功'
        ]);
    }

    /**
     * 按条码或商品编码精确查询（PDA 扫码用）
     * GET /products/lookup?barcode=xxx 或 ?code=xxx
     */
    public function lookup(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'store'])->where('is_active', true);
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user || $user->store_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        $barcode = $request->input('barcode');
        $code = $request->input('code');
        if ($barcode) {
            $query->where('barcode', $barcode);
        } elseif ($code) {
            $query->where('code', $code);
        } else {
            return response()->json(['success' => false, 'message' => '请提供 barcode 或 code 参数'], 422);
        }

        $product = $query->first();
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => $barcode ? "未找到条形码为 {$barcode} 的商品" : "未找到编码为 {$code} 的商品",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
            'message' => '查询成功',
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
                'code' => [
                    'required', 'string',
                    Rule::unique('products', 'code')->where('store_id', $storeId),
                ],
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:product_categories,id',
                'barcode' => 'nullable|string|unique:products,barcode',
                'specification' => 'nullable|string',
                'unit' => 'required|string',
                'second_unit' => 'nullable|string',
                'conversion_rate' => 'nullable|numeric|min:0',
                'purchase_price' => 'nullable|numeric|min:0',
                'retail_price' => 'nullable|numeric|min:0',
                'wholesale_price' => 'nullable|numeric|min:0',
                'min_stock' => 'nullable|numeric|min:0',
                'max_stock' => 'nullable|numeric|min:0',
                'track_serial' => 'boolean',
                'track_batch' => 'boolean',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            $category = ProductCategory::find($validatedData['category_id']);
            if ($category && !$this->isSuperAdmin($request) && !$this->isSameStore($request, $category->store_id)) {
                return $this->forbid('无权使用该商品分类');
            }

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $product = Product::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product),
                'message' => '商品创建成功'
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
        $product = Product::with(['category', 'store'])->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => '商品不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $product->store_id)) {
            return $this->forbid('无权访问该商品');
        }

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
            'message' => '商品详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => '商品不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $product->store_id)) {
            return $this->forbid('无权修改该商品');
        }

        try {
            $storeId = $product->store_id ?? $request->user()?->store_id;
            $validatedData = $request->validate([
                'code' => [
                    'sometimes', 'string',
                    Rule::unique('products', 'code')->where('store_id', $storeId)->ignore($id),
                ],
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'sometimes|exists:product_categories,id',
                'barcode' => 'nullable|string|unique:products,barcode,' . $id,
                'specification' => 'nullable|string',
                'unit' => 'sometimes|string',
                'second_unit' => 'nullable|string',
                'conversion_rate' => 'nullable|numeric|min:0',
                'purchase_price' => 'nullable|numeric|min:0',
                'retail_price' => 'nullable|numeric|min:0',
                'wholesale_price' => 'nullable|numeric|min:0',
                'min_stock' => 'nullable|numeric|min:0',
                'max_stock' => 'nullable|numeric|min:0',
                'track_serial' => 'boolean',
                'track_batch' => 'boolean',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (array_key_exists('category_id', $validatedData)) {
                $category = ProductCategory::find($validatedData['category_id']);
                if ($category && !$this->isSuperAdmin($request) && !$this->isSameStore($request, $category->store_id)) {
                    return $this->forbid('无权使用该商品分类');
                }
            }

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                if (array_key_exists('store_id', $validatedData)) {
                    $validatedData['store_id'] = $userStoreId;
                }
            }

            $product->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product),
                'message' => '商品更新成功'
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
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => '商品不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $product->store_id)) {
            return $this->forbid('无权删除该商品');
        }

        $pid = (int)$product->id;
        $refs = [
            [PurchaseOrderItem::class, '采购单明细'],
            [SalesOrderItem::class, '销售单明细'],
            [InventoryTransaction::class, '库存流水'],
        ];
        foreach ($refs as [$model, $label]) {
            if ($model::where('product_id', $pid)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "该商品仍有{$label}记录，不可删除"
                ], 400);
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => '商品删除成功'
        ]);
    }
}
