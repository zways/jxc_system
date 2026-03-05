<?php

namespace App\Http\Controllers;

use App\Traits\Auditable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use Auditable;
    protected function isSuperAdmin(Request $request): bool
    {
        return ($request->user()?->role?->code ?? null) === 'super_admin';
    }

    protected function isSameDepartment(Request $request, ?int $departmentId): bool
    {
        $user = $request->user();
        if (!$user || $departmentId === null || $user->department_id === null) {
            return false;
        }
        return (int)$user->department_id === (int)$departmentId;
    }

    protected function isSameStore(Request $request, ?int $storeId): bool
    {
        $user = $request->user();
        if (!$user || $storeId === null || $user->store_id === null) {
            return false;
        }
        return (int)$user->store_id === (int)$storeId;
    }

    protected function isSameWarehouse(Request $request, ?int $warehouseId): bool
    {
        $user = $request->user();
        if (!$user || $warehouseId === null || $user->warehouse_id === null) {
            return false;
        }
        return (int)$user->warehouse_id === (int)$warehouseId;
    }

    protected function forbid(string $message = 'Forbidden.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    protected function ensureOwnerOrSuperAdmin(
        Request $request,
        ?int $ownerId,
        ?int $ownerDepartmentId = null,
        ?int $storeId = null,
        ?int $warehouseId = null,
        string $message = '无权访问此资源'
    ): ?JsonResponse {
        if ($this->isSuperAdmin($request)) {
            return null;
        }
        $userId = $request->user()?->id;
        if ($userId && $ownerId !== null && $ownerId === $userId) {
            return null;
        }
        if ($this->isSameDepartment($request, $ownerDepartmentId)) {
            return null;
        }
        if ($this->isSameStore($request, $storeId)) {
            return null;
        }
        if ($this->isSameWarehouse($request, $warehouseId)) {
            return null;
        }
        return $this->forbid($message);
    }

    protected function ensureSameDepartmentOrSelfOrSuperAdmin(
        Request $request,
        ?int $targetUserId,
        ?int $targetDepartmentId,
        ?int $targetStoreId = null,
        ?int $targetWarehouseId = null,
        string $message = '无权访问此资源'
    ): ?JsonResponse {
        if ($this->isSuperAdmin($request)) {
            return null;
        }
        $user = $request->user();
        if (!$user) {
            return $this->forbid($message);
        }
        if ($targetUserId !== null && $targetUserId === $user->id) {
            return null;
        }
        if ($this->isSameDepartment($request, $targetDepartmentId)) {
            return null;
        }
        if ($this->isSameStore($request, $targetStoreId)) {
            return null;
        }
        if ($this->isSameWarehouse($request, $targetWarehouseId)) {
            return null;
        }
        return $this->forbid($message);
    }

    protected function scopeByOwner(
        Request $request,
        $query,
        string $ownerColumn = 'created_by',
        ?string $ownerRelation = null,
        ?string $warehouseColumn = null,
        ?string $storeColumn = null
    ): void
    {
        if ($this->isSuperAdmin($request)) {
            // 超管可通过 ?store_id=X 自愿缩小范围到某个企业
            if ($storeColumn && $request->filled('store_id')) {
                $query->where($storeColumn, (int) $request->input('store_id'));
            }
            return;
        }
        $userId = $request->user()?->id;
        $user = $request->user();
        if (!$userId) {
            $query->whereRaw('1 = 0');
            return;
        }
        $query->where(function ($q) use ($ownerColumn, $ownerRelation, $warehouseColumn, $storeColumn, $user) {
            $q->where($ownerColumn, $user->id);
            if ($ownerRelation && $user->department_id !== null) {
                $q->orWhereHas($ownerRelation, function ($sub) use ($user) {
                    $sub->where('department_id', $user->department_id);
                });
            }
            if ($warehouseColumn && $user->warehouse_id !== null) {
                $q->orWhere($warehouseColumn, $user->warehouse_id);
            }
            if ($storeColumn && $user->store_id !== null) {
                $q->orWhere($storeColumn, $user->store_id);
            }
        });
    }

    protected function scopeUsersByDepartmentOrSelf(Request $request, $query): void
    {
        if ($this->isSuperAdmin($request)) {
            return;
        }
        $user = $request->user();
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }
        if ($user->department_id !== null || $user->store_id !== null || $user->warehouse_id !== null) {
            $query->where(function ($q) use ($user) {
                if ($user->department_id !== null) {
                    $q->orWhere('department_id', $user->department_id);
                }
                if ($user->store_id !== null) {
                    $q->orWhere('store_id', $user->store_id);
                }
                if ($user->warehouse_id !== null) {
                    $q->orWhere('warehouse_id', $user->warehouse_id);
                }
            });
            return;
        }
        $query->where('id', $user->id);
    }
}
