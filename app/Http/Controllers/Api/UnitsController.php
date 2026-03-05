<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Unit::with('store');
        if (!$this->isSuperAdmin($request)) {
            $user = $request->user();
            if (!$user || $user->store_id === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('store_id', $user->store_id);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('symbol', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => UnitResource::collection($items),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ],
            'message' => '单位列表获取成功',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $scopedStoreId = $this->isSuperAdmin($request)
                ? $request->input('store_id')
                : $request->user()?->store_id;

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('units', 'name')->where(function ($q) use ($scopedStoreId) {
                        return $q->where('store_id', $scopedStoreId);
                    }),
                ],
                'symbol' => 'nullable|string|max:255',
                'remark' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validated) && $validated['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validated['store_id'] = $userStoreId;
            }
            $validated['store_id'] = $validated['store_id'] ?? ($request->user()?->store_id ?? null);

            $unit = Unit::create($validated);

            return response()->json([
                'success' => true,
                'data' => new UnitResource($unit),
                'message' => '单位创建成功',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $unit = Unit::with('store')->find($id);
        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => '单位不存在',
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $unit->store_id)) {
            return $this->forbid('无权访问该单位');
        }

        return response()->json([
            'success' => true,
            'data' => new UnitResource($unit),
            'message' => '单位详情获取成功',
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => '单位不存在',
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $unit->store_id)) {
            return $this->forbid('无权修改该单位');
        }

        try {
            $scopedStoreId = $this->isSuperAdmin($request)
                ? $request->input('store_id', $unit->store_id)
                : $request->user()?->store_id;

            $validated = $request->validate([
                'name' => [
                    'sometimes',
                    'string',
                    'max:255',
                    Rule::unique('units', 'name')
                        ->where(function ($q) use ($scopedStoreId) {
                            return $q->where('store_id', $scopedStoreId);
                        })
                        ->ignore($unit->id),
                ],
                'symbol' => 'nullable|string|max:255',
                'remark' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validated) && $validated['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                if (array_key_exists('store_id', $validated)) {
                    $validated['store_id'] = $userStoreId;
                }
            }

            $unit->update($validated);

            return response()->json([
                'success' => true,
                'data' => new UnitResource($unit),
                'message' => '单位更新成功',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '校验失败',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => '单位不存在',
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $unit->store_id)) {
            return $this->forbid('无权删除该单位');
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => '单位删除成功',
        ]);
    }
}

