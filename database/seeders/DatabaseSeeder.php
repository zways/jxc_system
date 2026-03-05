<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\SuppliersTableSeeder;
use Database\Seeders\CustomersTableSeeder;
use Database\Seeders\ProductCategoriesTableSeeder;
use Database\Seeders\ProductsTableSeeder;
use Database\Seeders\WarehousesTableSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\PurchaseOrdersSeeder;
use Database\Seeders\SalesOrdersSeeder;
use Database\Seeders\AccountsReceivableSeeder;
use Database\Seeders\AccountsPayableSeeder;
use Database\Seeders\FinancialTransactionsSeeder;
use Database\Seeders\InventoryTransactionsSeeder;
use Database\Seeders\InventoryCountsSeeder;
use Database\Seeders\InventoryAdjustmentsSeeder;
use Database\Seeders\BusinessAgentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoresTableSeeder;
use Database\Seeders\PurchaseOrderItemsSeeder;
use Database\Seeders\SalesOrderItemsSeeder;
use Database\Seeders\SalesReturnsSeeder;
use Database\Seeders\ExchangeRecordsSeeder;
use Database\Seeders\AssignTestDataToEnterpriseStoreSeeder;
use Database\Seeders\EnterpriseUserSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            DepartmentSeeder::class,
            UnitSeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,
            BusinessAgentSeeder::class,
            ProductCategoriesTableSeeder::class,
            SuppliersTableSeeder::class,
            CustomersTableSeeder::class,
            WarehousesTableSeeder::class,
            StoresTableSeeder::class,
            ProductsTableSeeder::class,
            PurchaseOrdersSeeder::class,
            PurchaseOrderItemsSeeder::class,
            SalesOrdersSeeder::class,
            SalesOrderItemsSeeder::class,
            AccountsReceivableSeeder::class,
            AccountsPayableSeeder::class,
            FinancialTransactionsSeeder::class,
            InventoryTransactionsSeeder::class,
            InventoryCountsSeeder::class,
            InventoryAdjustmentsSeeder::class,
            SalesReturnsSeeder::class,
            ExchangeRecordsSeeder::class,
            AssignTestDataToEnterpriseStoreSeeder::class,
            EnterpriseUserSeeder::class,
        ]);
    }
}
