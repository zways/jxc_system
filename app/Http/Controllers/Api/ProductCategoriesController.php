<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProductCategoriesController extends Controller
{
    private function isSelfOrDescendant(int $categoryId, int $parentId): bool
    {
        $currentId = $parentId;
        $guard = 0;

        while ($currentId && $guard < 100) {
            if ($currentId === $categoryId) {
                return true;
            }
            $currentId = (int)ProductCategory::query()
                ->whereKey($currentId)
                ->value('parent_id');
            $guard++;
        }

        return false;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::with('store');
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
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // 按状态筛选
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $categories = $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ProductCategoryResource::collection($categories),
                'meta' => [
                    'current_page' => $categories->currentPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'last_page' => $categories->lastPage(),
                ]
            ],
            'message' => '商品分类列表获取成功'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:product_categories,id',
                'level' => 'nullable|integer|min:1',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (array_key_exists('parent_id', $validatedData) && $validatedData['parent_id'] !== null) {
                $parent = ProductCategory::find((int)$validatedData['parent_id']);
                if ($parent && !$this->isSuperAdmin($request) && !$this->isSameStore($request, $parent->store_id)) {
                    return $this->forbid('无权使用该父分类');
                }
            }
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $category = ProductCategory::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new ProductCategoryResource($category),
                'message' => '商品分类创建成功'
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
        $category = ProductCategory::with('store')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => '商品分类不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $category->store_id)) {
            return $this->forbid('无权访问该分类');
        }

        return response()->json([
            'success' => true,
            'data' => new ProductCategoryResource($category),
            'message' => '商品分类详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = ProductCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => '商品分类不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $category->store_id)) {
            return $this->forbid('无权修改该分类');
        }

        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:product_categories,id',
                'level' => 'nullable|integer|min:1',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (array_key_exists('parent_id', $validatedData) && $validatedData['parent_id'] !== null) {
                $parentId = (int)$validatedData['parent_id'];
                if ($this->isSelfOrDescendant((int)$id, $parentId)) {
                    throw ValidationException::withMessages([
                        'parent_id' => ['父分类不能是自身或其子级'],
                    ]);
                }
                $parent = ProductCategory::find($parentId);
                if ($parent && !$this->isSuperAdmin($request) && !$this->isSameStore($request, $parent->store_id)) {
                    return $this->forbid('无权使用该父分类');
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

            $category->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new ProductCategoryResource($category),
                'message' => '商品分类更新成功'
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
        $category = ProductCategory::with(['children', 'products'])->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => '商品分类不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $category->store_id)) {
            return $this->forbid('无权删除该分类');
        }

        // 检查是否有子分类或商品关联
        $childCount = $category->children->count();
        $productCount = $category->products->count();

        if ($childCount > 0) {
            return response()->json([
                'success' => false,
                'message' => '存在子分类，无法删除'
            ], 400);
        }

        if ($productCount > 0) {
            return response()->json([
                'success' => false,
                'message' => '该分类下有关联商品，无法删除'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => '商品分类删除成功'
        ]);
    }
}
