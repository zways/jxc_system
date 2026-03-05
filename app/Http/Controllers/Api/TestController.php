<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    public function testSystem(Request $request): JsonResponse
    {
        $storeId = null;
        if (!$this->isSuperAdmin($request)) {
            $storeId = $request->user()?->store_id ?? -1;
        }

        // 获取一些统计数据
        $productCount = Product::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->count();
        $supplierCount = Supplier::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->count();
        $customerCount = Customer::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->count();
        $warehouseCount = Warehouse::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->count();

        $recentProducts = Product::with('category')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->latest()->take(5)->get();
        $recentSuppliers = Supplier::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->latest()->take(5)->get();
        $recentCustomers = Customer::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->latest()->take(5)->get();

        return response()->json([
            'success' => true,
            'message' => '进销存管理系统基础功能测试通过',
            'data' => [
                'system_info' => [
                    'product_count' => $productCount,
                    'supplier_count' => $supplierCount,
                    'customer_count' => $customerCount,
                    'warehouse_count' => $warehouseCount,
                ],
                'recent_data' => [
                    'recent_products' => $recentProducts,
                    'recent_suppliers' => $recentSuppliers,
                    'recent_customers' => $recentCustomers,
                ]
            ]
        ]);
    }
}
