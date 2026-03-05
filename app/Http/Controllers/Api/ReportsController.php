<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\InventoryTransaction;
use App\Models\FinancialTransaction;
use App\Models\AccountsReceivable;
use App\Models\AccountsPayable;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * 根据数据库驱动返回兼容的日期格式化表达式
     */
    private function dateFormatExpr(string $column, string $groupBy): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return match ($groupBy) {
                'week'  => "strftime('%Y-%W', {$column})",
                'month' => "strftime('%Y-%m', {$column})",
                default => "strftime('%Y-%m-%d', {$column})",
            };
        }

        // MySQL / MariaDB
        return match ($groupBy) {
            'week'  => "DATE_FORMAT({$column}, '%Y-%u')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    /**
     * 返回日期格式化为 '%Y-%m-%d' 的 SQL 表达式
     */
    private function dateExpr(string $column): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m-%d', {$column})";
        }

        return "DATE_FORMAT({$column}, '%Y-%m-%d')";
    }

    private function resolvedStoreId(Request $request): ?int
    {
        $storeId = $request->input('store_id');
        if ($this->isSuperAdmin($request)) {
            return $storeId ? (int)$storeId : null;
        }
        $userStoreId = $request->user()?->store_id;
        return $userStoreId ?? -1;
    }

    private function inventoryQuantitySubquery(?int $warehouseId = null, ?int $storeId = null)
    {
        $changeExpr = "CASE
            WHEN transaction_type='in' THEN quantity
            WHEN transaction_type='out' THEN -quantity
            WHEN transaction_type='adjust' THEN quantity
            ELSE quantity
        END";

        $query = InventoryTransaction::query()
            ->select('product_id', DB::raw("COALESCE(SUM($changeExpr),0) as qty"));

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->groupBy('product_id');
    }

    private function inventoryValue(?int $warehouseId = null, ?int $storeId = null): float
    {
        $qtySub = $this->inventoryQuantitySubquery($warehouseId, $storeId);

        $value = Product::query()
            ->leftJoinSub($qtySub, 'inv', 'products.id', 'inv.product_id')
            ->where('products.is_active', true)
            ->when($storeId, fn ($q) => $q->where('products.store_id', $storeId))
            ->selectRaw('COALESCE(SUM(products.purchase_price * COALESCE(inv.qty,0)),0) as total_value')
            ->value('total_value');

        return (float)($value ?? 0);
    }

    /**
     * 获取仪表盘概览数据
     */
    public function overview(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $storeId = $this->resolvedStoreId($request);

        // 销售统计（聚合无匹配时 first() 仍返回一行；为防其它情况做 null 安全）
        $salesData = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ')
            ->first();

        // 采购统计
        $purchaseData = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_purchases
            ')
            ->first();

        $totalSales = $salesData ? (float) $salesData->total_sales : 0;
        $totalPurchases = $purchaseData ? (float) $purchaseData->total_purchases : 0;

        // 库存总值（基于实际库存数量）
        $inventoryValue = $this->inventoryValue(null, $storeId);

        // 应收账款
        $receivableTotal = AccountsReceivable::where('status', '!=', 'paid')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('balance');

        // 应付账款
        $payableTotal = AccountsPayable::where('status', '!=', 'paid')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('balance');

        // 本月 vs 上月对比
        $lastMonthStart = Carbon::parse($startDate)->subMonth()->startOfMonth()->format('Y-m-d');
        $lastMonthEnd = Carbon::parse($startDate)->subMonth()->endOfMonth()->format('Y-m-d');

        $lastMonthSales = SalesOrder::whereBetween('order_date', [$lastMonthStart, $lastMonthEnd])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->sum('total_amount') ?: 0;

        $salesGrowth = $lastMonthSales > 0
            ? round((($totalSales - $lastMonthSales) / $lastMonthSales) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => [
                    'total_orders' => $salesData?->total_orders ?? 0,
                    'total_sales' => round($totalSales, 2),
                    'avg_order_value' => round($salesData?->avg_order_value ?? 0, 2),
                    'growth' => $salesGrowth
                ],
                'purchases' => [
                    'total_orders' => $purchaseData?->total_orders ?? 0,
                    'total_purchases' => round($totalPurchases, 2)
                ],
                'inventory' => [
                    'total_value' => round($inventoryValue, 2),
                    'product_count' => Product::where('is_active', true)
                        ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                        ->count()
                ],
                'finance' => [
                    'receivable' => round($receivableTotal ?? 0, 2),
                    'payable' => round($payableTotal ?? 0, 2),
                    'net_position' => round(($receivableTotal ?? 0) - ($payableTotal ?? 0), 2)
                ]
            ],
            'message' => '概览数据获取成功'
        ]);
    }

    /**
     * 销售报表
     */
    public function salesReport(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $groupBy = $request->input('group_by', 'day'); // day, week, month
        $storeId = $this->resolvedStoreId($request);

        // 销售趋势数据
        $periodExpr = $this->dateFormatExpr('order_date', $groupBy);

        $salesTrend = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw("{$periodExpr} as period, COUNT(*) as order_count, SUM(total_amount) as total_amount")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 订单类型分布
        $orderTypeDistribution = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('order_type, COUNT(*) as count, SUM(total_amount) as total_amount')
            ->groupBy('order_type')
            ->get();

        // 订单状态分布
        $statusDistribution = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        // 付款状态分布
        $paymentDistribution = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total_amount')
            ->groupBy('payment_status')
            ->get();

        // 客户销售排行
        $topCustomers = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->with('customer:id,name,customer_code')
            ->selectRaw('customer_id, COUNT(*) as order_count, SUM(total_amount) as total_amount')
            ->groupBy('customer_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        // 汇总统计
        $summary = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COALESCE(AVG(total_amount), 0) as avg_order_value,
                COALESCE(MAX(total_amount), 0) as max_order_value,
                COALESCE(MIN(total_amount), 0) as min_order_value
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_orders' => $summary->total_orders ?? 0,
                    'total_sales' => round($summary->total_sales ?? 0, 2),
                    'total_discount' => round($summary->total_discount ?? 0, 2),
                    'total_tax' => round($summary->total_tax ?? 0, 2),
                    'avg_order_value' => round($summary->avg_order_value ?? 0, 2),
                    'max_order_value' => round($summary->max_order_value ?? 0, 2),
                    'min_order_value' => round($summary->min_order_value ?? 0, 2)
                ],
                'trend' => $salesTrend,
                'order_type_distribution' => $orderTypeDistribution,
                'status_distribution' => $statusDistribution,
                'payment_distribution' => $paymentDistribution,
                'top_customers' => $topCustomers
            ],
            'message' => '销售报表获取成功'
        ]);
    }

    /**
     * 采购报表
     */
    public function purchaseReport(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $groupBy = $request->input('group_by', 'day');
        $storeId = $this->resolvedStoreId($request);

        // 采购趋势数据
        $periodExpr = $this->dateFormatExpr('order_date', $groupBy);

        $purchaseTrend = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw("{$periodExpr} as period, COUNT(*) as order_count, SUM(total_amount) as total_amount")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 订单状态分布
        $statusDistribution = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        // 付款状态分布
        $paymentDistribution = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total_amount')
            ->groupBy('payment_status')
            ->get();

        // 供应商采购排行
        $topSuppliers = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->with('supplier:id,name,supplier_code')
            ->selectRaw('supplier_id, COUNT(*) as order_count, SUM(total_amount) as total_amount')
            ->groupBy('supplier_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        // 汇总统计
        $summary = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_purchases,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COALESCE(SUM(shipping_cost), 0) as total_shipping,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_orders' => $summary->total_orders ?? 0,
                    'total_purchases' => round($summary->total_purchases ?? 0, 2),
                    'total_discount' => round($summary->total_discount ?? 0, 2),
                    'total_tax' => round($summary->total_tax ?? 0, 2),
                    'total_shipping' => round($summary->total_shipping ?? 0, 2),
                    'avg_order_value' => round($summary->avg_order_value ?? 0, 2)
                ],
                'trend' => $purchaseTrend,
                'status_distribution' => $statusDistribution,
                'payment_distribution' => $paymentDistribution,
                'top_suppliers' => $topSuppliers
            ],
            'message' => '采购报表获取成功'
        ]);
    }

    /**
     * 库存报表
     */
    public function inventoryReport(Request $request): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');
        $storeId = $this->resolvedStoreId($request);

        $qtySub = $this->inventoryQuantitySubquery($warehouseId ? (int)$warehouseId : null, $storeId);

        // 商品库存汇总（按分类）
        $inventoryByCategory = Product::with('category:id,name')
            ->leftJoinSub($qtySub, 'inv', 'products.id', 'inv.product_id')
            ->where('products.is_active', true)
            ->when($storeId, fn ($q) => $q->where('products.store_id', $storeId))
            ->selectRaw('products.category_id, COUNT(*) as product_count, COALESCE(SUM(products.purchase_price * COALESCE(inv.qty,0)),0) as total_value')
            ->groupBy('products.category_id')
            ->get();

        // 库存预警商品（低于最低库存）
        $lowStockProducts = Product::where('is_active', true)
            ->where('min_stock', '>', 0)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->select('id', 'code', 'name', 'unit', 'min_stock', 'max_stock', 'purchase_price', 'retail_price')
            ->orderBy('min_stock')
            ->limit(20)
            ->get();

        // 仓库库存分布
        $warehouseDistribution = Warehouse::where('is_active', true)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->select('id', 'code', 'name', 'type', 'location')
            ->get();

        // 最近库存变动
        $recentTransactions = InventoryTransaction::with(['product:id,code,name', 'warehouse:id,name'])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // 库存变动类型统计
        $transactionTypeStats = InventoryTransaction::selectRaw('transaction_type, COUNT(*) as count, SUM(quantity) as total_quantity')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->groupBy('transaction_type')
            ->get();

        // 商品总数和分类统计
        $productStats = [
            'total_products' => Product::where('is_active', true)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->count(),
            'total_categories' => DB::table('product_categories')
                ->where('is_active', true)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->count(),
            'total_value' => $this->inventoryValue($warehouseId ? (int)$warehouseId : null, $storeId)
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'product_stats' => $productStats,
                'inventory_by_category' => $inventoryByCategory,
                'low_stock_products' => $lowStockProducts,
                'warehouse_distribution' => $warehouseDistribution,
                'recent_transactions' => $recentTransactions,
                'transaction_type_stats' => $transactionTypeStats
            ],
            'message' => '库存报表获取成功'
        ]);
    }

    /**
     * 财务报表
     */
    public function financeReport(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $storeId = $this->resolvedStoreId($request);

        // 应收账款统计
        $receivableStats = AccountsReceivable::selectRaw('
                status,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as paid_amount,
                COALESCE(SUM(balance), 0) as balance
            ')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->groupBy('status')
            ->get();

        // 应付账款统计
        $payableStats = AccountsPayable::selectRaw('
                status,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as paid_amount,
                COALESCE(SUM(balance), 0) as balance
            ')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->groupBy('status')
            ->get();

        // 应收账款账龄分析
        $receivableAging = $this->calculateAgingAnalysis(AccountsReceivable::class, $storeId);

        // 应付账款账龄分析
        $payableAging = $this->calculateAgingAnalysis(AccountsPayable::class, $storeId);

        // 客户应收排行
        $topReceivableCustomers = AccountsReceivable::with('customer:id,name,customer_code')
            ->where('status', '!=', 'paid')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('customer_id, COUNT(*) as count, SUM(balance) as total_balance')
            ->groupBy('customer_id')
            ->orderByDesc('total_balance')
            ->limit(10)
            ->get();

        // 供应商应付排行
        $topPayableSuppliers = AccountsPayable::with('supplier:id,name,supplier_code')
            ->where('status', '!=', 'paid')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('supplier_id, COUNT(*) as count, SUM(balance) as total_balance')
            ->groupBy('supplier_id')
            ->orderByDesc('total_balance')
            ->limit(10)
            ->get();

        // 财务汇总
        $financeSummary = [
            'total_receivable' => AccountsReceivable::where('status', '!=', 'paid')
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->sum('balance'),
            'total_payable' => AccountsPayable::where('status', '!=', 'paid')
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->sum('balance'),
            'overdue_receivable' => AccountsReceivable::where('status', 'overdue')
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->sum('balance'),
            'overdue_payable' => AccountsPayable::where('status', 'overdue')
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->sum('balance'),
        ];
        $financeSummary['net_position'] = $financeSummary['total_receivable'] - $financeSummary['total_payable'];

        // 收入支出趋势（基于销售和采购）
        $dayExpr = $this->dateExpr('order_date');

        $incomeTrend = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw("{$dayExpr} as date, SUM(total_amount) as amount")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $expenseTrend = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw("{$dayExpr} as date, SUM(total_amount) as amount")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $financeSummary,
                'receivable_stats' => $receivableStats,
                'payable_stats' => $payableStats,
                'receivable_aging' => $receivableAging,
                'payable_aging' => $payableAging,
                'top_receivable_customers' => $topReceivableCustomers,
                'top_payable_suppliers' => $topPayableSuppliers,
                'income_trend' => $incomeTrend,
                'expense_trend' => $expenseTrend
            ],
            'message' => '财务报表获取成功'
        ]);
    }

    /**
     * 计算账龄分析
     */
    private function calculateAgingAnalysis(string $modelClass, ?int $storeId = null): array
    {
        $today = Carbon::now();

        $aging = [
            'current' => 0,      // 未到期
            '1_30_days' => 0,    // 1-30天
            '31_60_days' => 0,   // 31-60天
            '61_90_days' => 0,   // 61-90天
            'over_90_days' => 0  // 90天以上
        ];

        $records = $modelClass::where('status', '!=', 'paid')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get();

        foreach ($records as $record) {
            $dueDate = Carbon::parse($record->due_date);
            $daysOverdue = $today->diffInDays($dueDate, false);

            if ($daysOverdue >= 0) {
                $aging['current'] += $record->balance;
            } elseif ($daysOverdue >= -30) {
                $aging['1_30_days'] += $record->balance;
            } elseif ($daysOverdue >= -60) {
                $aging['31_60_days'] += $record->balance;
            } elseif ($daysOverdue >= -90) {
                $aging['61_90_days'] += $record->balance;
            } else {
                $aging['over_90_days'] += $record->balance;
            }
        }

        return array_map(fn($value) => round($value, 2), $aging);
    }

    /**
     * 导出报表数据（支持 JSON 和 CSV 格式）
     */
    public function exportReport(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $reportType = $request->input('report_type', 'sales');
        $format = $request->input('format', 'json');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $storeId = $this->resolvedStoreId($request);

        // 根据报表类型获取数据
        $data = match ($reportType) {
            'sales' => $this->getSalesExportData($startDate, $endDate, $storeId),
            'purchase' => $this->getPurchaseExportData($startDate, $endDate, $storeId),
            'inventory' => $this->getInventoryExportData($storeId),
            'finance' => $this->getFinanceExportData($storeId),
            default => []
        };

        if ($format === 'csv') {
            return $this->streamCsv($data, "{$reportType}_export.csv");
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => '导出数据生成成功'
        ]);
    }

    /**
     * 将数据以 CSV 流式输出（支持中文，带 BOM）
     */
    private function streamCsv(array $data, string $fileName): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // finance 类型返回的是嵌套数组，需要展平
        $rows = [];
        if (isset($data['receivable']) || isset($data['payable'])) {
            foreach ($data as $items) {
                if (is_array($items)) {
                    array_push($rows, ...$items);
                }
            }
        } else {
            $rows = $data;
        }

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            if (!empty($rows)) {
                // 提取表头（展平嵌套键）
                $headers = $this->flattenKeys($rows[0]);
                fputcsv($handle, $headers);

                foreach ($rows as $row) {
                    $flat = $this->flattenRow($row);
                    $line = [];
                    foreach ($headers as $h) {
                        $line[] = $flat[$h] ?? '';
                    }
                    fputcsv($handle, $line);
                }
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    private function flattenKeys(array $row, string $prefix = ''): array
    {
        $keys = [];
        foreach ($row as $k => $v) {
            $key = $prefix ? "{$prefix}.{$k}" : (string) $k;
            if (is_array($v) && !array_is_list($v)) {
                array_push($keys, ...$this->flattenKeys($v, $key));
            } else {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    private function flattenRow(array $row, string $prefix = ''): array
    {
        $flat = [];
        foreach ($row as $k => $v) {
            $key = $prefix ? "{$prefix}.{$k}" : (string) $k;
            if (is_array($v) && !array_is_list($v)) {
                $flat = array_merge($flat, $this->flattenRow($v, $key));
            } else {
                $flat[$key] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) ($v ?? '');
            }
        }
        return $flat;
    }

    private function getSalesExportData(string $startDate, string $endDate, ?int $storeId = null): array
    {
        return SalesOrder::with(['customer:id,name,customer_code', 'warehouse:id,name'])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('order_date', 'desc')
            ->get()
            ->toArray();
    }

    private function getPurchaseExportData(string $startDate, string $endDate, ?int $storeId = null): array
    {
        return PurchaseOrder::with(['supplier:id,name,supplier_code', 'warehouse:id,name'])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('order_date', 'desc')
            ->get()
            ->toArray();
    }

    private function getInventoryExportData(?int $storeId = null): array
    {
        return Product::with('category:id,name')
            ->where('is_active', true)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get()
            ->toArray();
    }

    private function getFinanceExportData(?int $storeId = null): array
    {
        return [
            'receivable' => AccountsReceivable::with('customer:id,name')
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->get()->toArray(),
            'payable' => AccountsPayable::with('supplier:id,name')
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->get()->toArray()
        ];
    }
}
