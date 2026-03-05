import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'

const LS_TOKEN = 'auth_token'
const LS_PERMS = 'auth_permissions'

const routes = [
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/LoginView.vue'),
    meta: { public: true }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('../views/RegisterView.vue'),
    meta: { public: true }
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('../views/ForgotPasswordView.vue'),
    meta: { public: true }
  },
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: () => import('../views/ResetPasswordView.vue'),
    meta: { public: true }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: DashboardView,
    meta: { permission: 'dashboard.read' }
  },
  {
    path: '/purchase/supplier',
    name: 'SupplierManagement',
    component: () => import('../views/purchase/SupplierManagement.vue'),
    meta: { permission: 'suppliers.read' }
  },
  {
    path: '/purchase/order',
    name: 'PurchaseOrder',
    component: () => import('../views/purchase/PurchaseOrder.vue'),
    meta: { permission: 'purchase-orders.read' }
  },
  {
    path: '/purchase/inbound',
    name: 'InboundRecord',
    component: () => import('../views/purchase/InboundRecord.vue'),
    meta: { permission: 'inventory-transactions.read' }
  },
  {
    path: '/sales/customer',
    name: 'CustomerManagement',
    component: () => import('../views/sales/CustomerManagement.vue'),
    meta: { permission: 'customers.read' }
  },
  {
    path: '/sales/order',
    name: 'SalesOrder',
    component: () => import('../views/sales/SalesOrder.vue'),
    meta: { permission: 'sales-orders.read' }
  },
  {
    path: '/sales/outbound',
    name: 'OutboundRecord',
    component: () => import('../views/sales/OutboundRecord.vue'),
    meta: { permission: 'inventory-transactions.read' }
  },
  {
    path: '/sales/return',
    name: 'SalesReturn',
    component: () => import('../views/sales/SalesReturn.vue'),
    meta: { permission: 'sales-returns.read' }
  },
  {
    path: '/sales/exchange',
    name: 'ExchangeRecord',
    component: () => import('../views/sales/ExchangeRecord.vue'),
    meta: { permission: 'exchange-records.read' }
  },
  {
    path: '/inventory/current',
    name: 'CurrentInventory',
    component: () => import('../views/inventory/CurrentInventory.vue'),
    meta: { permission: 'inventory-transactions.read' }
  },
  {
    path: '/inventory/transfer',
    name: 'TransferManagement',
    component: () => import('../views/inventory/TransferManagement.vue'),
    meta: { permission: 'inventory-transactions.read' }
  },
  {
    path: '/inventory/count',
    name: 'InventoryCount',
    component: () => import('../views/inventory/InventoryCount.vue'),
    meta: { permission: 'inventory-counts.read' }
  },
  {
    path: '/inventory/adjustment',
    name: 'InventoryAdjustment',
    component: () => import('../views/inventory/InventoryAdjustment.vue'),
    meta: { permission: 'inventory-adjustments.read' }
  },
  {
    path: '/finance/receivable',
    name: 'AccountsReceivable',
    component: () => import('../views/finance/AccountsReceivable.vue'),
    meta: { permission: 'accounts-receivable.read' }
  },
  {
    path: '/finance/payable',
    name: 'AccountsPayable',
    component: () => import('../views/finance/AccountsPayable.vue'),
    meta: { permission: 'accounts-payable.read' }
  },
  {
    path: '/finance/transaction',
    name: 'FinancialTransaction',
    component: () => import('../views/finance/FinancialTransaction.vue'),
    meta: { permission: 'financial-transactions.read' }
  },
  {
    path: '/finance/reconciliation',
    name: 'Reconciliation',
    component: () => import('../views/finance/Reconciliation.vue'),
    meta: { permission: 'financial-transactions.read' }
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('../views/ReportsView.vue'),
    meta: { permission: 'reports.read' }
  },
  {
    path: '/system/user',
    name: 'UserManagement',
    component: () => import('../views/system/UserManagement.vue'),
    meta: { permission: 'users.read' }
  },
  {
    path: '/system/department',
    name: 'DepartmentManagement',
    component: () => import('../views/system/DepartmentManagement.vue'),
    meta: { permission: 'departments.read' }
  },
  {
    path: '/system/role',
    name: 'RoleConfiguration',
    component: () => import('../views/system/RoleConfiguration.vue'),
    meta: { permission: 'roles.read' }
  },
  {
    path: '/system/product',
    name: 'ProductManagement',
    component: () => import('../views/system/ProductManagement.vue'),
    meta: { permission: 'products.read' }
  },
  {
    path: '/system/warehouse',
    name: 'WarehouseManagement',
    component: () => import('../views/system/WarehouseManagement.vue'),
    meta: { permission: 'warehouses.read' }
  },
  {
    path: '/system/store',
    name: 'StoreManagement',
    component: () => import('../views/system/StoreManagement.vue'),
    meta: { permission: 'stores.read' }
  },
  {
    path: '/system/agent',
    name: 'BusinessAgentManagement',
    component: () => import('../views/system/BusinessAgentManagement.vue'),
    meta: { permission: 'business-agents.read' }
  },
  {
    path: '/system/category',
    name: 'ProductCategory',
    component: () => import('../views/system/ProductCategory.vue'),
    meta: { permission: 'product-categories.read' }
  },
  {
    path: '/system/unit',
    name: 'UnitSetting',
    component: () => import('../views/system/UnitSetting.vue'),
    meta: { permission: 'units.read' }
  },
  {
    path: '/system/audit-log',
    name: 'AuditLog',
    component: () => import('../views/system/AuditLog.vue'),
    meta: { permission: 'audit-logs.read' }
  },
  {
    path: '/system/tenant-profile',
    name: 'TenantProfile',
    component: () => import('../views/system/TenantProfile.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/system/tenant-list',
    name: 'TenantList',
    component: () => import('../views/system/TenantList.vue'),
    meta: { requiresAuth: true, superAdminOnly: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: {
      template: `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:80vh;color:#909399">
        <h1 style="font-size:72px;margin:0;color:#dcdfe6">404</h1>
        <p style="font-size:18px;margin:16px 0">页面不存在</p>
        <el-button type="primary" @click="$router.replace('/dashboard')">返回首页</el-button>
      </div>`
    },
    meta: { public: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

const LS_AUTH_USER = 'auth_user'

function getPerms() {
  try {
    const raw = localStorage.getItem(LS_PERMS)
    const arr = raw ? JSON.parse(raw) : []
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}

function isSuperAdmin() {
  try {
    const raw = localStorage.getItem(LS_AUTH_USER)
    const u = raw ? JSON.parse(raw) : null
    const roleCode = u?.role?.code ?? u?.role_code ?? ''
    return roleCode === 'super_admin'
  } catch {
    return false
  }
}

router.beforeEach((to) => {
  const isPublic = Boolean(to.meta && to.meta.public)
  const token = localStorage.getItem(LS_TOKEN)

  if (!isPublic && !token) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if ((to.path === '/login' || to.path === '/register') && token) {
    return { path: '/dashboard' }
  }

  // 仅超管可访问（如企业管理）：非超管直接访问 URL 时重定向到工作台
  if (!isPublic && token && to.meta?.superAdminOnly && !isSuperAdmin()) {
    return { path: '/dashboard' }
  }

  // 有 token 但无对应权限：阻止直接输入 URL 进入
  const required = to.meta && to.meta.permission
  if (!isPublic && token && required) {
    const perms = getPerms()
    if (!perms.includes(required)) {
      return { path: '/dashboard' }
    }
  }
})

export default router