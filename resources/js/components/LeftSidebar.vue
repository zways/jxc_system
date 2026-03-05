<template>
    <div class="sidebar-container" :class="{ collapsed: isCollapsed }">
        <el-scrollbar>
            <el-menu
                :default-active="activeMenu"
                :collapse="isCollapsed"
                :unique-opened="true"
                :router="true"
                class="sidebar-menu"
                @select="handleMenuSelect"
            >
                <template v-for="item in visibleMenuItems" :key="item.path">
                    <el-sub-menu
                        v-if="item.children && item.children.length > 0"
                        :index="item.path"
                    >
                        <template #title>
                            <el-icon v-if="item.iconName === 'House'"
                                ><house
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'ShoppingCart'"
                                ><shopping-cart
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'Goods'"
                                ><goods
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'User'"
                                ><user
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'CreditCard'"
                                ><credit-card
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'DataAnalysis'"
                                ><data-analysis
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'Setting'"
                                ><setting
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'Document'"
                                ><document
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'Wallet'"
                                ><wallet
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'Box'"
                                ><box
                            /></el-icon>
                            <el-icon v-if="item.iconName === 'Files'"
                                ><files
                            /></el-icon>
                            <span>{{ item.title }}</span>
                        </template>
                        <el-menu-item
                            v-for="child in item.children"
                            :key="child.path"
                            :index="child.path"
                        >
                            <el-icon v-if="child.iconName === 'House'"
                                ><house
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'ShoppingCart'"
                                ><shopping-cart
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'Goods'"
                                ><goods
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'User'"
                                ><user
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'CreditCard'"
                                ><credit-card
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'DataAnalysis'"
                                ><data-analysis
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'Setting'"
                                ><setting
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'Document'"
                                ><document
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'Wallet'"
                                ><wallet
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'Box'"
                                ><box
                            /></el-icon>
                            <el-icon v-if="child.iconName === 'Files'"
                                ><files
                            /></el-icon>
                            <span>{{ child.title }}</span>
                        </el-menu-item>
                    </el-sub-menu>
                    <el-menu-item v-else :index="item.path">
                        <el-icon v-if="item.iconName === 'House'"
                            ><house
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'ShoppingCart'"
                            ><shopping-cart
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'Goods'"
                            ><goods
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'User'"
                            ><user
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'CreditCard'"
                            ><credit-card
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'DataAnalysis'"
                            ><data-analysis
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'Setting'"
                            ><setting
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'Document'"
                            ><document
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'Wallet'"
                            ><wallet
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'Box'"
                            ><box
                        /></el-icon>
                        <el-icon v-if="item.iconName === 'Files'"
                            ><files
                        /></el-icon>
                            <span>{{ item.title }}</span>
                    </el-menu-item>
                </template>
            </el-menu>
        </el-scrollbar>
    </div>
</template>

<script>
import { ref, defineComponent, onMounted, onBeforeUnmount, watch, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    House,
    ShoppingCart,
    Goods,
    User,
    CreditCard,
    DataAnalysis,
    Setting,
    Document,
    Wallet,
    Box,
    Files,
} from "@element-plus/icons-vue";

const LS_PERMS = "auth_permissions";

export default defineComponent({
    name: "LeftSidebar",
    setup() {
        const route = useRoute();
        const router = useRouter();
        const isCollapsed = ref(false);
        const activeMenu = ref(route.path || "/dashboard");

        // 仅对叶子菜单路径跳转（父级如 /purchase 无对应路由，不跳转）
        const parentOnlyPaths = ["/purchase", "/sales", "/inventory", "/finance", "/system"];
        const handleMenuSelect = (path) => {
            if (path && path !== route.path && !parentOnlyPaths.includes(path)) {
                router.push(path);
            }
        };

        // 监听侧边栏切换事件
        const handleSidebarToggle = () => {
            isCollapsed.value = !isCollapsed.value;
        };

        onMounted(() => {
            window.addEventListener("sidebar-toggle", handleSidebarToggle);
        });

        onBeforeUnmount(() => {
            window.removeEventListener("sidebar-toggle", handleSidebarToggle);
        });

        // 菜单项定义
        const menuItems = [
            {
                path: "/dashboard",
                title: "首页仪表盘",
                iconName: "House",
                permission: "dashboard.read",
            },
            {
                path: "/purchase",
                title: "采购管理",
                iconName: "ShoppingCart",
                children: [
                    {
                        path: "/purchase/supplier",
                        title: "供应商管理",
                        iconName: "User",
                        permission: "suppliers.read",
                    },
                    {
                        path: "/purchase/order",
                        title: "采购订单",
                        iconName: "Document",
                        permission: "purchase-orders.read",
                    },
                    {
                        path: "/purchase/inbound",
                        title: "入库记录",
                        iconName: "Box",
                        permission: "inventory-transactions.read",
                    },
                ],
            },
            {
                path: "/sales",
                title: "销售管理",
                iconName: "CreditCard",
                children: [
                    {
                        path: "/sales/customer",
                        title: "客户管理",
                        iconName: "User",
                        permission: "customers.read",
                    },
                    {
                        path: "/sales/order",
                        title: "销售订单",
                        iconName: "Document",
                        permission: "sales-orders.read",
                    },
                    {
                        path: "/sales/outbound",
                        title: "出库记录",
                        iconName: "Box",
                        permission: "inventory-transactions.read",
                    },
                    {
                        path: "/sales/return",
                        title: "退货处理",
                        iconName: "Files",
                        permission: "sales-returns.read",
                    },
                    {
                        path: "/sales/exchange",
                        title: "换货管理",
                        iconName: "Files",
                        permission: "exchange-records.read",
                    },
                ],
            },
            {
                path: "/inventory",
                title: "库存管理",
                iconName: "Goods",
                children: [
                    {
                        path: "/inventory/current",
                        title: "实时库存",
                        iconName: "Box",
                        permission: "inventory-transactions.read",
                    },
                    {
                        path: "/inventory/transfer",
                        title: "调拨管理",
                        iconName: "Box",
                        permission: "inventory-transactions.read",
                    },
                    {
                        path: "/inventory/count",
                        title: "盘点功能",
                        iconName: "Document",
                        permission: "inventory-counts.read",
                    },
                    {
                        path: "/inventory/adjustment",
                        title: "库存调整",
                        iconName: "Document",
                        permission: "inventory-adjustments.read",
                    },
                ],
            },
            {
                path: "/finance",
                title: "财务管理",
                iconName: "Wallet",
                children: [
                    {
                        path: "/finance/receivable",
                        title: "应收账款",
                        iconName: "CreditCard",
                        permission: "accounts-receivable.read",
                    },
                    {
                        path: "/finance/payable",
                        title: "应付账款",
                        iconName: "CreditCard",
                        permission: "accounts-payable.read",
                    },
                    {
                        path: "/finance/transaction",
                        title: "收支明细",
                        iconName: "Document",
                        permission: "financial-transactions.read",
                    },
                    {
                        path: "/finance/reconciliation",
                        title: "对账功能",
                        iconName: "Document",
                        permission: "financial-transactions.read",
                    },
                ],
            },
            {
                path: "/reports",
                title: "报表中心",
                iconName: "DataAnalysis",
                permission: "reports.read",
            },
            {
                path: "/system",
                title: "系统设置",
                iconName: "Setting",
                children: [
                    {
                        path: "/system/user",
                        title: "用户管理",
                        iconName: "User",
                        permission: "users.read",
                    },
                    {
                        path: "/system/department",
                        title: "部门管理",
                        iconName: "User",
                        permission: "departments.read",
                    },
                    {
                        path: "/system/role",
                        title: "角色配置",
                        iconName: "User",
                        permission: "roles.read",
                    },
                    {
                        path: "/system/product",
                        title: "商品管理",
                        iconName: "Goods",
                        permission: "products.read",
                    },
                    {
                        path: "/system/warehouse",
                        title: "仓库管理",
                        iconName: "Box",
                        permission: "warehouses.read",
                    },
                    {
                        path: "/system/store",
                        title: "门店管理",
                        iconName: "House",
                        permission: "stores.read",
                    },
                    {
                        path: "/system/agent",
                        title: "业务员管理",
                        iconName: "User",
                        permission: "business-agents.read",
                    },
                    {
                        path: "/system/category",
                        title: "商品分类",
                        iconName: "Goods",
                        permission: "product-categories.read",
                    },
                    {
                        path: "/system/unit",
                        title: "单位设置",
                        iconName: "Document",
                        permission: "units.read",
                    },
                    {
                        path: "/system/audit-log",
                        title: "操作日志",
                        iconName: "Files",
                        permission: "audit-logs.read",
                    },
                    {
                        path: "/system/tenant-profile",
                        title: "企业信息",
                        iconName: "House",
                        tenantOnly: true,
                    },
                    {
                        path: "/system/tenant-list",
                        title: "企业管理",
                        iconName: "User",
                        superAdminOnly: true,
                    },
                ],
            },
        ];

        const getPermissions = () => {
            try {
                const raw = localStorage.getItem(LS_PERMS);
                return raw ? JSON.parse(raw) : [];
            } catch (_) {
                return [];
            }
        };

        const hasPerm = (perm) => {
            if (!perm) return true;
            const perms = getPermissions();
            return Array.isArray(perms) && perms.includes(perm);
        };

        const isSuperAdmin = () => {
            try {
                const raw = localStorage.getItem("auth_user");
                const u = raw ? JSON.parse(raw) : null;
                const roleCode = u?.role?.code || u?.role_code || "";
                return roleCode === "super_admin";
            } catch (_) {
                return false;
            }
        };

        const visibleMenuItems = computed(() => {
            const filterItem = (item) => {
                if (item.children && item.children.length > 0) {
                    const children = item.children.filter((c) => filterItem(c));
                    return children.length > 0 ? { ...item, children } : null;
                }
                // 超管专属菜单（如企业管理）
                if (item.superAdminOnly) return isSuperAdmin() ? item : null;
                // 仅企业用户可见（非超管），如企业信息
                if (item.tenantOnly) return !isSuperAdmin() ? item : null;
                return hasPerm(item.permission) ? item : null;
            };

            return menuItems.map(filterItem).filter(Boolean);
        });

        // 监听路由变化，更新激活菜单
        watch(
            () => route.path,
            (newPath) => {
                activeMenu.value = newPath;
            },
            { immediate: true }
        );

        return {
            isCollapsed,
            activeMenu,
            visibleMenuItems,
            handleMenuSelect,
        };
    },
});
</script>

<style scoped>
.sidebar-container {
    width: 256px;
    height: 100%;
    background-color: #ffffff;
    border-right: 1px solid #e6e6e6;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.sidebar-container.collapsed {
    width: 64px;
}

.sidebar-menu {
    border: none;
    height: 100%;
}

.sidebar-container :deep(.el-scrollbar) {
    height: 100%;
}

.sidebar-menu:not(.el-menu--collapse) {
    width: 256px;
}
</style>
