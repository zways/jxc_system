<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessAgentResource;
use App\Models\BusinessAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessAgentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BusinessAgent::with('store');
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
                $q->where('agent_code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // 按状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min($request->input('per_page', 15), 100); // 最大100条每页
        $agents = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => BusinessAgentResource::collection($agents),
                'meta' => [
                    'current_page' => $agents->currentPage(),
                    'per_page' => $agents->perPage(),
                    'total' => $agents->total(),
                    'last_page' => $agents->lastPage(),
                ]
            ],
            'message' => '业务员列表获取成功'
        ]);
    }

    /**
     * 生成业务员编号（企业隔离）
     */
    private function generateAgentCode(?int $storeId = null): string
    {
        $prefix = 'AGT';
        $query = BusinessAgent::where('agent_code', 'like', "{$prefix}%");
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }
        $lastAgent = $query->orderBy('agent_code', 'desc')->first();
        
        if ($lastAgent) {
            $lastNumber = intval(substr($lastAgent->agent_code, 3));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return "{$prefix}{$newNumber}";
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $storeId = $request->user()?->store_id;
            $validatedData = $request->validate([
                'agent_code' => [
                    'nullable', 'string',
                    Rule::unique('business_agents', 'agent_code')->where('store_id', $storeId),
                ],
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'territory' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,inactive',
                'notes' => 'nullable|string',
                'store_id' => 'nullable|exists:stores,id',
            ]);

            // 自动生成编号（企业隔离）
            if (empty($validatedData['agent_code'])) {
                $validatedData['agent_code'] = $this->generateAgentCode($storeId);
            }
            // 设置默认值
            $validatedData['status'] = $validatedData['status'] ?? 'active';
            $validatedData['commission_rate'] = $validatedData['commission_rate'] ?? 0;
            $validatedData['territory'] = $validatedData['territory'] ?? '';
            if (!$this->isSuperAdmin($request)) {
                $userStoreId = $request->user()?->store_id;
                if (array_key_exists('store_id', $validatedData) && $validatedData['store_id'] !== $userStoreId) {
                    return response()->json(['success' => false, 'message' => '无权设置该门店'], 403);
                }
                $validatedData['store_id'] = $userStoreId;
            }
            $validatedData['store_id'] = $validatedData['store_id'] ?? ($request->user()?->store_id ?? null);

            $agent = BusinessAgent::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => new BusinessAgentResource($agent),
                'message' => '业务员创建成功'
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
        $agent = BusinessAgent::with('store')->find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => '业务员不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $agent->store_id)) {
            return $this->forbid('无权访问该业务员');
        }

        return response()->json([
            'success' => true,
            'data' => new BusinessAgentResource($agent),
            'message' => '业务员详情获取成功'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $agent = BusinessAgent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => '业务员不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $agent->store_id)) {
            return $this->forbid('无权修改该业务员');
        }

        try {
            $validatedData = $request->validate([
                'agent_code' => [
                    'sometimes', 'string',
                    Rule::unique('business_agents', 'agent_code')->where('store_id', $agent->store_id ?? $request->user()?->store_id)->ignore($id),
                ],
                'name' => 'sometimes|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'territory' => 'nullable|string|max:255',
                'status' => 'sometimes|string|in:active,inactive',
                'notes' => 'nullable|string',
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

            $agent->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => new BusinessAgentResource($agent),
                'message' => '业务员更新成功'
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
        $agent = BusinessAgent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => '业务员不存在'
            ], 404);
        }
        if (!$this->isSuperAdmin($request) && !$this->isSameStore($request, $agent->store_id)) {
            return $this->forbid('无权删除该业务员');
        }

        $agent->delete();

        return response()->json([
            'success' => true,
            'message' => '业务员删除成功'
        ]);
    }
}
