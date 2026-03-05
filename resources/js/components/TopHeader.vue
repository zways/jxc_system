<template>
    <div class="top-header">
        <div class="header-left">
            <el-button
                class="sidebar-toggle"
                @click="toggleSidebar"
                :icon="Menu"
                size="large"
            />
            <h1 class="logo">进销存管理系统</h1>
        </div>

        <div class="header-right">
            <el-button-group class="header-actions">
                <el-dropdown trigger="click" @command="createDocumentByType">
                    <el-button :icon="DocumentAdd" size="large" type="primary">
                        新增单据
                        <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="sales/order">销售订单</el-dropdown-item>
                            <el-dropdown-item command="purchase/order">采购单</el-dropdown-item>
                            <el-dropdown-item command="purchase/inbound">入库单</el-dropdown-item>
                            <el-dropdown-item command="sales/outbound">出库单</el-dropdown-item>
                            <el-dropdown-item command="inventory/transfer">调拨单</el-dropdown-item>
                            <el-dropdown-item command="sales/return">销售退货</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <el-button :icon="Download" size="large" @click="exportData">
                    导出数据
                </el-button>
            </el-button-group>

            <div class="notification-center">
                <el-badge :value="unreadNotifications" :hidden="unreadNotifications === 0" class="item">
                    <el-button
                        :icon="Bell"
                        circle
                        size="large"
                        @click="showNotifications"
                    />
                </el-badge>
            </div>

            <div class="user-info">
                <el-dropdown placement="bottom-end" trigger="click">
                    <div class="user-avatar">
                        <el-avatar :size="40" :src="userAvatar" />
                        <span class="username">{{ userName }}</span>
                    </div>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item @click="profile">
                                个人资料
                            </el-dropdown-item>
                            <el-dropdown-item v-if="!isSuperAdmin" @click="goTenantProfile">
                                企业信息
                            </el-dropdown-item>
                            <el-dropdown-item @click="settings">
                                系统设置
                            </el-dropdown-item>
                            <el-dropdown-item divided @click="logout">
                                退出登录
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>
    </div>

    <!-- 通知抽屉 -->
    <el-drawer
        v-model="notificationDrawerVisible"
        title="消息通知"
        direction="rtl"
        size="360px"
    >
        <div class="notification-list">
            <div
                v-for="item in notificationList"
                :key="item.id"
                class="notification-item"
                :class="{ unread: item.unread }"
                @click="markNotificationRead(item)"
            >
                <div class="notification-title">{{ item.title }}</div>
                <div class="notification-desc">{{ item.desc }}</div>
                <div class="notification-time">{{ item.time }}</div>
            </div>
            <el-empty v-if="notificationList.length === 0" description="暂无通知" />
        </div>
    </el-drawer>

    <!-- 个人资料弹窗 -->
    <el-dialog
        v-model="profileDialogVisible"
        title="个人资料"
        width="420px"
        destroy-on-close
    >
        <div class="profile-content">
            <div class="profile-avatar">
                <el-avatar :size="80" :src="userAvatar" />
            </div>
            <el-descriptions :column="1" border>
                <el-descriptions-item label="用户名">{{ userInfo.username || '-' }}</el-descriptions-item>
                <el-descriptions-item label="真实姓名">{{ userInfo.real_name || userInfo.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="邮箱">{{ userInfo.email || '-' }}</el-descriptions-item>
                <el-descriptions-item label="电话">{{ userInfo.phone || '-' }}</el-descriptions-item>
                <el-descriptions-item label="角色">{{ userInfo.role?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="部门">{{ userInfo.department?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="所属企业">{{ userInfo.store?.name || '-' }}</el-descriptions-item>
            </el-descriptions>
        </div>
    </el-dialog>
</template>

<script>
import { ref, computed, defineComponent, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { Menu, DocumentAdd, Download, Bell, ArrowDown } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { parsePaginatedResponse } from "../utils/api";
import { buildCsv, downloadCsv } from "../utils/exportCsv";

const LS_TOKEN = "auth_token";
const LS_USER = "auth_user";
const LS_PERMS = "auth_permissions";

/** 当前路由对应的导出配置：API、文件名、列 */
const EXPORT_CONFIG = {
    "/purchase/supplier": {
        api: "suppliers",
        fileName: "供应商列表.csv",
        columns: [
            { key: "supplier_code", label: "供应商编码" },
            { key: "name", label: "名称" },
            { key: "contact_person", label: "联系人" },
            { key: "phone", label: "电话" },
            { key: "address", label: "地址" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/purchase/order": {
        api: "purchase-orders",
        fileName: "采购单列表.csv",
        columns: [
            { key: "order_number", label: "订单号" },
            { key: "supplier.name", label: "供应商" },
            { key: "order_date", label: "订单日期" },
            { key: "total_amount", label: "总金额" },
            { key: "status", label: "状态" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/purchase/inbound": {
        api: "inventory-transactions",
        fileName: "入库记录.csv",
        params: { transaction_type: "in" },
        columns: [
            { key: "transaction_number", label: "流水号" },
            { key: "product.name", label: "商品" },
            { key: "warehouse.name", label: "仓库" },
            { key: "quantity", label: "数量" },
            { key: "unit_cost", label: "单价" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/sales/customer": {
        api: "customers",
        fileName: "客户列表.csv",
        columns: [
            { key: "customer_code", label: "客户编码" },
            { key: "name", label: "名称" },
            { key: "contact_person", label: "联系人" },
            { key: "phone", label: "电话" },
            { key: "address", label: "地址" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/sales/order": {
        api: "sales-orders",
        fileName: "销售订单列表.csv",
        columns: [
            { key: "order_number", label: "订单号" },
            { key: "customer.name", label: "客户" },
            { key: "order_date", label: "订单日期" },
            { key: "total_amount", label: "总金额" },
            { key: "status", label: "状态" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/sales/outbound": {
        api: "inventory-transactions",
        fileName: "出库记录.csv",
        params: { transaction_type: "out" },
        columns: [
            { key: "transaction_number", label: "流水号" },
            { key: "product.name", label: "商品" },
            { key: "warehouse.name", label: "仓库" },
            { key: "quantity", label: "数量" },
            { key: "unit_cost", label: "单价" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/sales/return": {
        api: "sales-returns",
        fileName: "销售退货列表.csv",
        columns: [
            { key: "return_number", label: "退货单号" },
            { key: "sale.order_number", label: "原订单号" },
            { key: "total_amount", label: "金额" },
            { key: "status", label: "状态" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/inventory/transfer": {
        api: "inventory-transactions",
        fileName: "调拨记录.csv",
        params: { transaction_type: "transfer" },
        columns: [
            { key: "transaction_number", label: "流水号" },
            { key: "product.name", label: "商品" },
            { key: "warehouse.name", label: "仓库" },
            { key: "quantity", label: "数量" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/finance/receivable": {
        api: "accounts-receivable",
        fileName: "应收账款列表.csv",
        columns: [
            { key: "document_type", label: "单据类型" },
            { key: "document_id", label: "单据ID" },
            { key: "amount", label: "金额" },
            { key: "balance", label: "余额" },
            { key: "status", label: "状态" },
            { key: "due_date", label: "到期日" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/finance/payable": {
        api: "accounts-payable",
        fileName: "应付账款列表.csv",
        columns: [
            { key: "document_type", label: "单据类型" },
            { key: "document_id", label: "单据ID" },
            { key: "amount", label: "金额" },
            { key: "balance", label: "余额" },
            { key: "status", label: "状态" },
            { key: "due_date", label: "到期日" },
            { key: "created_at", label: "创建时间" },
        ],
    },
    "/finance/transaction": {
        api: "financial-transactions",
        fileName: "财务流水.csv",
        columns: [
            { key: "transaction_number", label: "流水号" },
            { key: "type", label: "类型" },
            { key: "amount", label: "金额" },
            { key: "transaction_date", label: "交易日期" },
            { key: "created_at", label: "创建时间" },
        ],
    },
};

export default defineComponent({
    name: "TopHeader",
    setup() {
        const router = useRouter();
        const route = useRoute();

        const unreadNotifications = ref(0);
        const userName = ref("管理员");
        const userAvatar = ref(
            "https://cube.elemecdn.com/0/88/03b0d39583f48206768a7534e55bcpng.png"
        );
        const notificationDrawerVisible = ref(false);
        const profileDialogVisible = ref(false);
        const notificationList = ref([]);

        const updateUnreadCount = () => {
            unreadNotifications.value = notificationList.value.filter((n) => n.unread).length;
        };

        const loadNotificationsFromServer = async () => {
            try {
                const res = await window.axios.get("notifications", {
                    params: {
                        per_page: 20,
                    },
                });
                const { list } = parsePaginatedResponse(res);
                notificationList.value = (list || []).map((n) => ({
                    id: n.id,
                    title: n.title,
                    desc: n.body || n.description || "",
                    time: n.created_at,
                    unread: !n.is_read,
                }));
                updateUnreadCount();
            } catch (e) {
                console.error("加载通知失败", e);
            }
        };

        const toggleSidebar = () => {
            window.dispatchEvent(new Event("sidebar-toggle"));
        };

        const createDocumentByType = (path) => {
            router.push({ path: "/" + path, query: { action: "new" } });
        };

        const exportData = async () => {
            const path = route.path || "";
            const config = EXPORT_CONFIG[path];
            if (!config) {
                ElMessage.warning("当前页面不支持导出，请在有数据列表的页面使用导出功能。");
                return;
            }
            ElMessage.info("正在导出数据…");
            try {
                const perPage = 100;
                const all = [];
                let page = 1;
                let totalPages = 1;
                do {
                    const params = {
                        page,
                        per_page: perPage,
                        ...(config.params || {}),
                    };
                    const res = await window.axios.get(config.api, { params });
                    const { list, meta } = parsePaginatedResponse(res);
                    all.push(...(list || []));
                    if (meta.total != null && meta.per_page != null) {
                        totalPages = Math.ceil(meta.total / meta.per_page) || 1;
                    }
                    page += 1;
                } while (page <= totalPages);

                if (all.length === 0) {
                    ElMessage.warning("暂无数据可导出");
                    return;
                }
                const csvContent = buildCsv(all, config.columns);
                downloadCsv(csvContent, config.fileName);
                ElMessage.success(`导出成功，共 ${all.length} 条`);
            } catch (e) {
                console.error(e);
                ElMessage.error(e.response?.data?.message || "导出失败");
            }
        };

        const showNotifications = () => {
            notificationDrawerVisible.value = true;
        };

        const markNotificationRead = async (item) => {
            if (!item.unread) {
                return;
            }
            item.unread = false;
            updateUnreadCount();
            try {
                await window.axios.post(`notifications/${item.id}/read`);
            } catch (e) {
                console.error("标记通知已读失败", e);
            }
        };

        const userInfo = ref({});
        const isSuperAdmin = computed(() => {
            const roleCode = userInfo.value?.role?.code || userInfo.value?.role_code || "";
            return roleCode === "super_admin";
        });

        const profile = () => {
            profileDialogVisible.value = true;
        };

        const goTenantProfile = () => {
            router.push("/system/tenant-profile");
        };

        const settings = () => {
            router.push("/system/user");
        };

        const logout = () => {
            ElMessageBox.confirm("确定要退出登录吗？", "提示", {
                confirmButtonText: "确定",
                cancelButtonText: "取消",
                type: "warning",
            })
                .then(async () => {
                    try {
                        await window.axios.post("auth/logout");
                    } catch (_) {
                        // 忽略退出失败（token 可能已过期）
                    }
                    localStorage.removeItem(LS_TOKEN);
                    localStorage.removeItem(LS_USER);
                    localStorage.removeItem(LS_PERMS);
                    ElMessage.success("已退出登录");
                    router.replace("/login");
                })
                .catch(() => {});
        };

        const loadUserFromCache = () => {
            try {
                const raw = localStorage.getItem(LS_USER);
                const u = raw ? JSON.parse(raw) : null;
                if (u) {
                    userName.value = u.real_name || u.name || u.username || "用户";
                    userInfo.value = u;
                }
            } catch (_) {}
        };

        onMounted(() => {
            loadUserFromCache();
            loadNotificationsFromServer();
        });

        return {
            Menu,
            DocumentAdd,
            Download,
            Bell,
            ArrowDown,
            unreadNotifications,
            userName,
            userAvatar,
            notificationDrawerVisible,
            profileDialogVisible,
            notificationList,
            toggleSidebar,
            createDocumentByType,
            exportData,
            showNotifications,
            markNotificationRead,
            userInfo,
            isSuperAdmin,
            profile,
            goTenantProfile,
            settings,
            logout,
        };
    },
});
</script>

<style scoped>
.top-header {
    height: 60px;
    background-color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    box-shadow: 0 1px 4px rgba(0, 21, 41, 0.08);
    z-index: 100;
}

.header-left {
    display: flex;
    align-items: center;
}

.sidebar-toggle {
    margin-right: 15px;
}

.logo {
    margin: 0;
    font-size: 18px;
    font-weight: bold;
    color: #1890ff;
}

.header-right {
    display: flex;
    align-items: center;
}

.header-actions {
    margin-right: 20px;
}

.notification-center {
    margin-right: 20px;
}

.user-info {
    display: flex;
    align-items: center;
}

.user-avatar {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.username {
    margin-left: 10px;
    font-size: 14px;
    color: #333;
}

.notification-list {
    padding: 0 8px;
}
.notification-item {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: background 0.2s;
}
.notification-item:hover {
    background: var(--el-fill-color-light);
}
.notification-item.unread {
    background: var(--el-color-primary-light-9);
}
.notification-title {
    font-weight: 500;
    color: var(--el-text-color-primary);
}
.notification-desc {
    font-size: 12px;
    color: var(--el-text-color-regular);
    margin-top: 4px;
}
.notification-time {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

.profile-content {
    padding: 8px 0;
}
.profile-avatar {
    text-align: center;
    margin-bottom: 20px;
}
</style>
