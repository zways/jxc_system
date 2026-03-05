<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\ProductCategoriesController;
use App\Http\Controllers\Api\SuppliersController;
use App\Http\Controllers\Api\CustomersController;
use App\Http\Controllers\Api\PurchaseOrdersController;
use App\Http\Controllers\Api\SalesOrdersController;
use App\Http\Controllers\Api\WarehousesController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\AccountsPayableController;
use App\Http\Controllers\Api\AccountsReceivableController;
use App\Http\Controllers\Api\FinancialTransactionsController;
use App\Http\Controllers\Api\InventoryAdjustmentsController;
use App\Http\Controllers\Api\InventoryTransactionsController;
use App\Http\Controllers\Api\ExchangeRecordsController;
use App\Http\Controllers\Api\InventoryCountsController;
use App\Http\Controllers\Api\SalesReturnsController;
use App\Http\Controllers\Api\StoresController;
use App\Http\Controllers\Api\BusinessAgentsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\DepartmentsController;
use App\Http\Controllers\Api\UnitsController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionsController;
use App\Http\Controllers\Api\AuditLogsController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\NotificationsController;

Route::prefix('v1')->group(function () {

    // =====================================================
    //  公开接口（无需鉴权）— 独立限流
    // =====================================================

    // 健康检查（存活探测，无需鉴权）
    Route::get('health', [HealthCheckController::class, 'ping']);

    // 支付异步回调（支付宝/微信服务器回调，无需鉴权）
    Route::post('payment/notify/alipay', [PaymentController::class, 'notifyAlipay']);
    Route::post('payment/notify/wechat', [PaymentController::class, 'notifyWechat']);

    // 登录：10 次/分钟（IP 维度），防止暴力破解
    Route::middleware(['throttle:login'])->group(function () {
        Route::post('auth/login', [AuthController::class, 'login']);
    });

    // 找回密码配置与操作（公开，独立限流）
    Route::get('auth/password-reset-config', [AuthController::class, 'passwordResetConfig']);
    Route::middleware(['throttle:password_reset'])->group(function () {
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    // 企业注册：5 次/小时（IP 维度），防止批量注册
    Route::middleware(['throttle:register'])->group(function () {
        Route::post('tenant/register', [TenantController::class, 'register']);
    });

    // =====================================================
    //  已认证接口 — 全局限流 60 次/分钟
    // =====================================================

    // 仅需要登录（不走 RBAC 自动权限拼接）
    // 说明：rbac.auto 会基于 URL 资源段拼 permission（如 suppliers.read）。
    // auth/* 并非业务资源，不应强制要求 auth.read/auth.create 之类权限，否则会导致普通用户无法 me/logout。
    Route::middleware(['auth.token', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // 通知中心（仅需登录）
        Route::get('notifications', [NotificationsController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationsController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationsController::class, 'markAllAsRead']);

        // 企业信息管理（需要登录，不走 RBAC）
        Route::get('tenant/current', [TenantController::class, 'show']);
        Route::put('tenant/current', [TenantController::class, 'update']);
        Route::get('tenant/list', [TenantController::class, 'index']);  // 超管专用
        Route::put('tenant/{id}/subscription', [TenantController::class, 'updateSubscription']);  // 超管：手动为企业续费/改套餐

        Route::get('payment/config', [PaymentController::class, 'config']);
        Route::post('payment/create-order', [PaymentController::class, 'createOrder']);

        // 深度健康检查（需登录，超管可查看详细诊断）
        Route::get('health/deep', [HealthCheckController::class, 'deep']);
    });

    // 需要登录 + 企业有效 + RBAC 权限
    Route::middleware(['auth.token', 'tenant.active', 'rbac.auto', 'throttle:api'])->group(function () {

        Route::apiResource('permissions', PermissionsController::class)->only(['index']);

        // 商品条码/编码查询（PDA 扫码，需在 apiResource 之前注册）
        Route::get('products/lookup', [ProductsController::class, 'lookup']);
        Route::apiResource('products', ProductsController::class);
        Route::apiResource('product-categories', ProductCategoriesController::class);
        Route::apiResource('suppliers', SuppliersController::class);
        Route::apiResource('customers', CustomersController::class);
        Route::apiResource('purchase-orders', PurchaseOrdersController::class);
        // 采购收货：触发库存入库 + 应付生成（需要 purchase-orders.update 权限）
        Route::put('purchase-orders/{id}/receive', [PurchaseOrdersController::class, 'receive']);
        // 采购取消/撤销：必要时回滚入库流水/应付（需要 purchase-orders.update 权限）
        Route::put('purchase-orders/{id}/cancel', [PurchaseOrdersController::class, 'cancel']);
        Route::apiResource('sales-orders', SalesOrdersController::class);
        // 销售发货：触发库存出库 + 应收生成（需要 sales-orders.update 权限）
        Route::put('sales-orders/{id}/deliver', [SalesOrdersController::class, 'deliver']);
        // 销售取消/撤销：必要时回滚出库流水/应收（需要 sales-orders.update 权限）
        Route::put('sales-orders/{id}/cancel', [SalesOrdersController::class, 'cancel']);
        Route::apiResource('warehouses', WarehousesController::class);
        Route::apiResource('accounts-payable', AccountsPayableController::class);
        // 付款：更新应付已付/余额，并生成财务流水（需要 accounts-payable.update 权限）
        Route::put('accounts-payable/{id}/pay', [AccountsPayableController::class, 'pay']);
        Route::apiResource('accounts-receivable', AccountsReceivableController::class);
        // 收款：更新应收已收/余额，并生成财务流水（需要 accounts-receivable.update 权限）
        Route::put('accounts-receivable/{id}/collect', [AccountsReceivableController::class, 'collect']);
        Route::apiResource('financial-transactions', FinancialTransactionsController::class);
        // 作废/冲销：将流水标记为 voided，并回滚关联的应收/应付（payment/receipt 场景）
        Route::put('financial-transactions/{id}/void', [FinancialTransactionsController::class, 'void']);
        Route::apiResource('inventory-adjustments', InventoryAdjustmentsController::class);
        Route::get('inventory-transactions/stock-summary', [InventoryTransactionsController::class, 'stockSummary']);
        Route::get('inventory-transactions/batches-available', [InventoryTransactionsController::class, 'batchesAvailable']);
        Route::get('inventory-transactions/serials-available', [InventoryTransactionsController::class, 'serialsAvailable']);
        Route::post('inventory-transactions/transfer', [InventoryTransactionsController::class, 'transfer']);
        Route::apiResource('inventory-transactions', InventoryTransactionsController::class);
        Route::apiResource('exchange-records', ExchangeRecordsController::class);
        // 换货完成：生成换货库存流水（需要 exchange-records.update 权限）
        Route::put('exchange-records/{id}/complete', [ExchangeRecordsController::class, 'complete']);
        Route::apiResource('inventory-counts', InventoryCountsController::class);
        Route::get('inventory-counts/{id}/items', [InventoryCountsController::class, 'items']);
        Route::put('inventory-counts/{id}/items', [InventoryCountsController::class, 'saveItems']);
        Route::post('inventory-counts/{id}/complete', [InventoryCountsController::class, 'complete']);
        Route::apiResource('sales-returns', SalesReturnsController::class);
        // 退货处理：生成退回入库流水并冲减应收（需要 sales-returns.update 权限）
        Route::put('sales-returns/{id}/process', [SalesReturnsController::class, 'process']);
        // 退货退款：生成退款财务流水并回写应收（需要 sales-returns.update 权限）
        Route::put('sales-returns/{id}/refund', [SalesReturnsController::class, 'refund']);
        Route::apiResource('stores', StoresController::class);
        Route::apiResource('business-agents', BusinessAgentsController::class);
        Route::apiResource('roles', RolesController::class);
        Route::get('roles/{role}/permissions', [RolesController::class, 'permissions']);
        Route::put('roles/{role}/permissions', [RolesController::class, 'syncPermissions']);
        Route::apiResource('departments', DepartmentsController::class);
        Route::apiResource('units', UnitsController::class);
        Route::apiResource('users', UsersController::class);

        Route::get('test-system', [TestController::class, 'testSystem']);

        // 审计日志（只读）
        Route::get('audit-logs', [AuditLogsController::class, 'index']);
        Route::get('audit-logs/{id}', [AuditLogsController::class, 'show']);

        // 报表相关路由 — 额外叠加报表限流（10 次/分钟）
        Route::middleware(['throttle:reports'])->prefix('reports')->group(function () {
            Route::get('overview', [ReportsController::class, 'overview']);
            Route::get('sales', [ReportsController::class, 'salesReport']);
            Route::get('purchase', [ReportsController::class, 'purchaseReport']);
            Route::get('inventory', [ReportsController::class, 'inventoryReport']);
            Route::get('finance', [ReportsController::class, 'financeReport']);
            Route::get('export', [ReportsController::class, 'exportReport']);
        });
    });
});
