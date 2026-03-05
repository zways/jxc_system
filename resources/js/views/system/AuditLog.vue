<template>
    <div class="audit-log-container">
        <div class="page-header">
            <h3>操作日志</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="搜索操作人/描述/对象"
                    style="width: 240px; margin-right: 10px"
                    :prefix-icon="Search"
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                    clearable
                    @clear="handleSearch"
                />
                <el-select
                    v-model="filterAction"
                    placeholder="操作类型"
                    clearable
                    style="width: 130px; margin-right: 10px"
                    @change="handleSearch"
                >
                    <el-option label="创建" value="create" />
                    <el-option label="更新" value="update" />
                    <el-option label="删除" value="delete" />
                    <el-option label="恢复" value="restore" />
                    <el-option label="作废" value="void" />
                    <el-option label="付款" value="pay" />
                    <el-option label="收款" value="collect" />
                    <el-option label="处理" value="process" />
                    <el-option label="登录" value="login" />
                    <el-option label="登出" value="logout" />
                </el-select>
                <el-date-picker
                    v-model="dateRange"
                    type="daterange"
                    range-separator="至"
                    start-placeholder="开始日期"
                    end-placeholder="结束日期"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    style="margin-right: 10px"
                    @change="handleSearch"
                />
                <el-button type="primary" :icon="Search" @click="handleSearch"
                    >查询</el-button
                >
            </div>
        </div>

        <el-card class="data-card">
            <el-table
                :data="logList"
                v-loading="loading"
                style="width: 100%"
                row-key="id"
                border
            >
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="created_at" label="操作时间" width="170" />
                <el-table-column prop="user_name" label="操作人" width="100">
                    <template #default="{ row }">
                        {{ row.user_name || '系统' }}
                    </template>
                </el-table-column>
                <el-table-column prop="action_label" label="操作类型" width="90">
                    <template #default="{ row }">
                        <el-tag :type="getActionTagType(row.action)" size="small">
                            {{ row.action_label }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="model_type_label"
                    label="对象类型"
                    width="100"
                />
                <el-table-column
                    prop="model_label"
                    label="对象名称"
                    width="160"
                    show-overflow-tooltip
                />
                <el-table-column
                    prop="description"
                    label="描述"
                    min-width="200"
                    show-overflow-tooltip
                >
                    <template #default="{ row }">
                        {{ row.description || '-' }}
                    </template>
                </el-table-column>
                <el-table-column
                    prop="ip_address"
                    label="IP地址"
                    width="130"
                />
                <el-table-column label="操作" width="80" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            size="small"
                            link
                            type="primary"
                            @click="viewDetail(row)"
                            >详情</el-button
                        >
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper">
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :page-sizes="[20, 50, 100]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="fetchLogs"
                    @current-change="fetchLogs"
                />
            </div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="操作日志详情" width="650px">
            <el-descriptions :column="2" border v-if="currentLog">
                <el-descriptions-item label="ID">{{
                    currentLog.id
                }}</el-descriptions-item>
                <el-descriptions-item label="操作时间">{{
                    currentLog.created_at
                }}</el-descriptions-item>
                <el-descriptions-item label="操作人">{{
                    currentLog.user_name || '系统'
                }}</el-descriptions-item>
                <el-descriptions-item label="操作类型">
                    <el-tag
                        :type="getActionTagType(currentLog.action)"
                        size="small"
                    >
                        {{ currentLog.action_label }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="对象类型">{{
                    currentLog.model_type_label
                }}</el-descriptions-item>
                <el-descriptions-item label="对象名称">{{
                    currentLog.model_label
                }}</el-descriptions-item>
                <el-descriptions-item label="IP地址">{{
                    currentLog.ip_address
                }}</el-descriptions-item>
                <el-descriptions-item label="描述" :span="2">{{
                    currentLog.description || '-'
                }}</el-descriptions-item>
            </el-descriptions>

            <div
                v-if="
                    currentLog &&
                    (currentLog.old_values || currentLog.new_values)
                "
                style="margin-top: 16px"
            >
                <h4 style="margin-bottom: 10px">变更详情</h4>
                <el-table
                    :data="changeDetails"
                    border
                    size="small"
                    style="width: 100%"
                >
                    <el-table-column prop="field" label="字段" width="150" />
                    <el-table-column
                        prop="oldVal"
                        label="变更前"
                        show-overflow-tooltip
                    />
                    <el-table-column
                        prop="newVal"
                        label="变更后"
                        show-overflow-tooltip
                    />
                </el-table>
            </div>

            <template #footer>
                <el-button @click="detailVisible = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import { ElMessage } from "element-plus";
import { Search } from "@element-plus/icons-vue";

export default {
    name: "AuditLog",
    setup() {
        const loading = ref(false);
        const logList = ref([]);
        const currentPage = ref(1);
        const pageSize = ref(20);
        const total = ref(0);
        const searchKeyword = ref("");
        const filterAction = ref("");
        const dateRange = ref(null);
        const detailVisible = ref(false);
        const currentLog = ref(null);

        const getActionTagType = (action) => {
            const map = {
                create: "success",
                update: "warning",
                delete: "danger",
                restore: "info",
                void: "danger",
                pay: "success",
                collect: "success",
                process: "",
                login: "info",
                logout: "info",
            };
            return map[action] || "info";
        };

        const changeDetails = computed(() => {
            if (!currentLog.value) return [];
            const oldVals = currentLog.value.old_values || {};
            const newVals = currentLog.value.new_values || {};
            const allKeys = new Set([
                ...Object.keys(oldVals),
                ...Object.keys(newVals),
            ]);
            return Array.from(allKeys).map((key) => ({
                field: key,
                oldVal:
                    oldVals[key] !== undefined
                        ? JSON.stringify(oldVals[key])
                        : "-",
                newVal:
                    newVals[key] !== undefined
                        ? JSON.stringify(newVals[key])
                        : "-",
            }));
        });

        const fetchLogs = async () => {
            loading.value = true;
            try {
                const params = {
                    page: currentPage.value,
                    per_page: pageSize.value,
                };
                if (searchKeyword.value) {
                    params.search = searchKeyword.value;
                }
                if (filterAction.value) {
                    params.action = filterAction.value;
                }
                if (dateRange.value && dateRange.value.length === 2) {
                    params.start_date = dateRange.value[0];
                    params.end_date = dateRange.value[1];
                }
                const response = await window.axios.get("audit-logs", {
                    params,
                });
                if (response.data.success) {
                    logList.value = response.data.data.data;
                    total.value = response.data.data.meta.total;
                }
            } catch (error) {
                console.error("获取操作日志失败:", error);
                ElMessage.error("获取操作日志失败");
            } finally {
                loading.value = false;
            }
        };

        const handleSearch = () => {
            currentPage.value = 1;
            fetchLogs();
        };

        const viewDetail = (row) => {
            currentLog.value = row;
            detailVisible.value = true;
        };

        onMounted(() => {
            fetchLogs();
        });

        return {
            loading,
            logList,
            currentPage,
            pageSize,
            total,
            searchKeyword,
            filterAction,
            dateRange,
            detailVisible,
            currentLog,
            changeDetails,
            Search,
            getActionTagType,
            fetchLogs,
            handleSearch,
            viewDetail,
        };
    },
};
</script>

<style scoped>
.audit-log-container {
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

.header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0;
}

.data-card {
    margin-bottom: 20px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
</style>
