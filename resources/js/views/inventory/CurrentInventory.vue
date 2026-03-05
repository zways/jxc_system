<template>
    <div class="current-inventory-container">
        <div class="page-header">
            <h3>实时库存管理</h3>
            <div class="header-actions">
                <!-- 超管专用：企业筛选 -->
                <el-select
                    v-if="isSuperAdmin"
                    v-model="storeFilter"
                    placeholder="全部企业"
                    clearable
                    filterable
                    style="width: 180px; margin-right: 12px"
                    @change="onStoreChange"
                >
                    <el-option v-for="s in stores" :key="s.id" :label="s.name" :value="s.id" />
                </el-select>
                <el-input
                    v-model="searchKeyword"
                    placeholder="商品名称/编码"
                    style="width: 240px; margin-right: 12px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-select v-model="warehouseFilter" placeholder="仓库" clearable style="width: 160px; margin-right: 12px" @change="handleSearch">
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
            </div>
        </div>

        <!-- 超管提示信息 -->
        <el-alert
            v-if="isSuperAdmin && !storeFilter"
            type="info"
            :closable="false"
            show-icon
            style="margin-bottom: 16px"
        >
            当前为超管视角，显示所有企业的库存汇总。选择企业可查看单个企业数据。
        </el-alert>

        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="code" label="商品编码" width="120" />
                <el-table-column prop="name" label="商品名称" min-width="140" />
                <el-table-column label="分类" width="120">
                    <template #default="{ row }">{{ row.category?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="unit" label="单位" width="80" />
                <el-table-column prop="min_stock" label="最低库存" width="100" align="right" />
                <el-table-column prop="max_stock" label="最高库存" width="100" align="right" />
                <el-table-column label="当前库存" width="120" align="right">
                    <template #default="{ row }">
                        <span :class="{ 'stock-low': isLowStock(row), 'stock-over': isOverStock(row) }">
                            {{ stockMap[row.id] ?? "-" }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? "启用" : "禁用" }}</el-tag>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-container">
                <el-pagination
                    v-model:current-page="pagination.currentPage"
                    v-model:page-size="pagination.pageSize"
                    :page-sizes="[10, 20, 50]"
                    :total="pagination.total"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, defineComponent } from "vue";
import { ElMessage } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";

const LS_USER = "auth_user";

export default defineComponent({
    name: "CurrentInventory",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const warehouseFilter = ref(null);
        const storeFilter = ref(null);
        const list = ref([]);
        const stockMap = ref({});
        const warehouses = ref([]);
        const stores = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });

        // 检测是否超管
        const isSuperAdmin = computed(() => {
            try {
                const raw = localStorage.getItem(LS_USER);
                const u = raw ? JSON.parse(raw) : null;
                return (u?.role?.code || u?.role_code || "") === "super_admin";
            } catch (_) {
                return false;
            }
        });

        // 加载企业列表（超管专用）
        const loadStores = async () => {
            if (!isSuperAdmin.value) return;
            try {
                const res = await window.axios.get("tenant/list", { params: { per_page: 100 } });
                const data = res.data?.data || {};
                stores.value = data.data || data || [];
            } catch (_) {
                stores.value = [];
            }
        };

        const loadWarehouses = async () => {
            try {
                const params = { per_page: 200 };
                if (isSuperAdmin.value && storeFilter.value) {
                    params.store_id = storeFilter.value;
                }
                const res = await window.axios.get("warehouses", { params });
                const { list: data } = parsePaginatedResponse(res);
                warehouses.value = data || [];
            } catch (_) {
                warehouses.value = [];
            }
        };

        const loadStockMap = async () => {
            try {
                const params = {
                    warehouse_id: warehouseFilter.value || undefined,
                };
                // 超管可按企业筛选
                if (isSuperAdmin.value && storeFilter.value) {
                    params.store_id = storeFilter.value;
                }
                const res = await window.axios.get("inventory-transactions/stock-summary", { params });
                stockMap.value = res.data?.data || {};
            } catch (_) {
                stockMap.value = {};
            }
        };

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };
        const loadList = async () => {
            loading.value = true;
            try {
                const params = {
                    page: pagination.currentPage,
                    per_page: pagination.pageSize,
                    search: searchKeyword.value || undefined,
                };
                // 超管可按企业筛选产品
                if (isSuperAdmin.value && storeFilter.value) {
                    params.store_id = storeFilter.value;
                }
                const res = await window.axios.get("products", { params });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data;
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
                await loadStockMap();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "加载失败");
                list.value = [];
            } finally {
                loading.value = false;
            }
        };

        // 切换企业时重新加载仓库和数据
        const onStoreChange = () => {
            warehouseFilter.value = null;
            pagination.currentPage = 1;
            loadWarehouses();
            loadList();
        };

        // 库存预警判定
        const isLowStock = (row) => {
            const stock = stockMap.value[row.id];
            return stock != null && row.min_stock > 0 && stock < row.min_stock;
        };
        const isOverStock = (row) => {
            const stock = stockMap.value[row.id];
            return stock != null && row.max_stock > 0 && stock > row.max_stock;
        };

        onMounted(() => {
            loadStores();
            loadWarehouses();
            loadList();
        });

        return {
            loading,
            searchKeyword,
            warehouseFilter,
            storeFilter,
            list,
            stockMap,
            warehouses,
            stores,
            pagination,
            isSuperAdmin,
            handleSearch,
            loadList,
            onStoreChange,
            isLowStock,
            isOverStock,
        };
    },
});
</script>

<style scoped>
.current-inventory-container {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.data-card {
    min-height: 400px;
}
.pagination-container {
    margin-top: 16px;
    text-align: right;
}
/* 库存预警样式 */
.stock-low {
    color: #f56c6c;
    font-weight: 600;
}
.stock-over {
    color: #e6a23c;
    font-weight: 600;
}
</style>
