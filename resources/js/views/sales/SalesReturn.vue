<template>
    <div class="sales-return-container">
        <div class="page-header">
            <h3>销售退货管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="退货单号/客户"
                    style="width: 240px; margin-right: 12px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-select v-model="statusFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="待处理" value="pending" />
                    <el-option label="已审批" value="approved" />
                    <el-option label="已处理" value="processed" />
                    <el-option label="已退款" value="refunded" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" :icon="Plus" @click="openAdd">新增退货单</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="return_number" label="退货单号" width="150" />
                <el-table-column label="客户" min-width="120">
                    <template #default="{ row }">{{ row.customer?.name || "-" }}</template>
                </el-table-column>
                <el-table-column label="销售订单" width="120">
                    <template #default="{ row }">{{ row.sale?.order_number || "-" }}</template>
                </el-table-column>
                <el-table-column label="退回仓库" width="130">
                    <template #default="{ row }">{{ row.warehouse?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="return_date" label="退货日期" width="120" />
                <el-table-column prop="total_amount" label="退货金额" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.total_amount || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" label="退货原因" min-width="140" show-overflow-tooltip />
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="320" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">查看</el-button>
                        <el-button
                            v-if="row.status === 'pending' || row.status === 'approved'"
                            size="small"
                            type="primary"
                            @click="editRow(row)"
                        >编辑</el-button>
                        <el-button
                            v-if="row.status === 'pending' || row.status === 'approved'"
                            size="small"
                            type="success"
                            @click="processRow(row)"
                        >处理入库</el-button>
                        <el-button
                            v-if="row.status !== 'refunded'"
                            size="small"
                            type="warning"
                            @click="refundRow(row)"
                        >{{ row.status === 'processed' ? '退款闭环' : '一键退款' }}</el-button>
                        <el-button
                            v-if="row.status === 'pending' || row.status === 'approved'"
                            size="small"
                            type="danger"
                            @click="removeRow(row)"
                        >删除</el-button>
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
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
                <el-form-item label="客户" prop="customer_id">
                    <el-select v-model="form.customer_id" :disabled="isViewMode" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="销售订单" prop="sale_id">
                    <el-select v-model="form.sale_id" :disabled="isViewMode" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="s in salesOrders" :key="s.id" :label="s.order_number" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="退回仓库" prop="warehouse_id">
                    <el-select v-model="form.warehouse_id" :disabled="isViewMode" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="退货日期" prop="return_date">
                    <el-date-picker v-model="form.return_date" :disabled="isViewMode" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>
                <el-form-item label="退货金额" prop="total_amount">
                    <el-input-number v-model="form.total_amount" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item label="退货原因" prop="reason">
                    <el-input v-model="form.reason" :disabled="isViewMode" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="备注" prop="notes">
                    <el-input v-model="form.notes" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitForm">{{ isViewMode ? "关闭" : "确定" }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, onMounted, onActivated, watch, defineComponent } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Plus } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";

export default defineComponent({
    name: "SalesReturn",
    setup() {
        const route = useRoute();
        const router = useRouter();
        const loading = ref(false);
        const searchKeyword = ref("");
        const statusFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增销售退货");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const customers = ref([]);
        const salesOrders = ref([]);
        const warehouses = ref([]);
        const form = reactive({
            id: null,
            customer_id: null,
            sale_id: null,
            warehouse_id: null,
            return_date: "",
            total_amount: 0,
            reason: "",
            notes: "",
            status: "pending",
        });
        const formRules = {
            customer_id: [{ required: true, message: "请选择客户", trigger: "change" }],
            sale_id: [{ required: true, message: "请选择销售订单", trigger: "change" }],
            warehouse_id: [{ required: true, message: "请选择仓库", trigger: "change" }],
            return_date: [{ required: true, message: "请选择退货日期", trigger: "change" }],
            total_amount: [{ required: true, message: "请输入退货金额", trigger: "blur" }],
        };

        const formatDate = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const statusText = (s) => ({ pending: "待处理", approved: "已审批", processed: "已处理", refunded: "已退款" }[s] || s);
        const statusTagType = (s) => ({ pending: "warning", approved: "primary", processed: "success", refunded: "info" }[s] || "info");

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };
        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("sales-returns", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        status: statusFilter.value || undefined,
                    },
                });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data;
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "加载失败");
                list.value = [];
            } finally {
                loading.value = false;
            }
        };

        const loadCustomers = async () => {
            try {
                const res = await window.axios.get("customers", { params: { per_page: 500 } });
                const { list: data } = parsePaginatedResponse(res);
                customers.value = data || [];
            } catch (_) {
                customers.value = [];
            }
        };

        const loadSalesOrders = async () => {
            try {
                const res = await window.axios.get("sales-orders", { params: { per_page: 500 } });
                const { list: data } = parsePaginatedResponse(res);
                salesOrders.value = data || [];
            } catch (_) {
                salesOrders.value = [];
            }
        };

        const loadWarehouses = async () => {
            try {
                const res = await window.axios.get("warehouses", { params: { per_page: 500 } });
                const { list: data } = parsePaginatedResponse(res);
                warehouses.value = data || [];
            } catch (_) {
                warehouses.value = [];
            }
        };

        const openAdd = () => {
            form.id = null;
            form.customer_id = null;
            form.sale_id = null;
            form.warehouse_id = null;
            form.return_date = new Date().toISOString().slice(0, 10);
            form.total_amount = 0;
            form.reason = "";
            form.notes = "";
            form.status = "pending";
            dialogTitle.value = "新增销售退货";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
            loadCustomers();
            loadSalesOrders();
            loadWarehouses();
        };

        const viewDetail = (row) => {
            form.id = row.id;
            form.customer_id = row.customer_id;
            form.sale_id = row.sale_id || null;
            form.warehouse_id = row.warehouse_id;
            form.return_date = row.return_date;
            form.total_amount = Number(row.total_amount || 0);
            form.reason = row.reason || "";
            form.notes = row.notes || "";
            form.status = row.status || "pending";
            dialogTitle.value = "退货详情";
            isEdit.value = false;
            isViewMode.value = true;
            dialogVisible.value = true;
            loadCustomers();
            loadSalesOrders();
            loadWarehouses();
        };

        const editRow = (row) => {
            form.id = row.id;
            form.customer_id = row.customer_id;
            form.sale_id = row.sale_id || null;
            form.warehouse_id = row.warehouse_id;
            form.return_date = row.return_date;
            form.total_amount = Number(row.total_amount || 0);
            form.reason = row.reason || "";
            form.notes = row.notes || "";
            form.status = row.status || "pending";
            dialogTitle.value = "编辑销售退货";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
            loadCustomers();
            loadSalesOrders();
            loadWarehouses();
        };

        const processRow = async (row) => {
            if (!row?.id) return;
            try {
                await ElMessageBox.confirm(
                    `确定处理退货单「${row.return_number || row.id}」吗？\n系统将按关联销售单明细生成退回入库流水，并冲减应收。`,
                    "处理确认",
                    { type: "warning", confirmButtonText: "确定处理", cancelButtonText: "取消" }
                );
                await window.axios.put(`sales-returns/${row.id}/process`);
                ElMessage.success("处理成功");
                loadList();
            } catch (e) {
                if (e === "cancel" || e?.message === "cancel") return;
                const msg =
                    e.response?.data?.message ||
                    (e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
                    "处理失败";
                ElMessage.error(msg);
            }
        };

        const refundRow = async (row) => {
            if (!row?.id) return;
            const isUnprocessed = row.status === 'pending' || row.status === 'approved';
            try {
                const confirmMsg = isUnprocessed
                    ? `确定对退货单「${row.return_number || row.id}」执行一键退款吗？\n系统将自动完成：入库、冲减应收、退还超收（如有），并标记流程闭环。`
                    : `确定对退货单「${row.return_number || row.id}」完成退款闭环吗？\n若客户有超收金额将自动生成退款流水，否则直接标记退货完成。`;
                await ElMessageBox.confirm(
                    confirmMsg,
                    isUnprocessed ? "一键退款确认" : "退款闭环确认",
                    { type: "warning", confirmButtonText: "确定执行", cancelButtonText: "取消" }
                );
                const res = await window.axios.put(`sales-returns/${row.id}/refund`);
                ElMessage.success(res.data?.message || "退款成功");
                loadList();
            } catch (e) {
                if (e === "cancel" || e?.message === "cancel") return;
                const msg =
                    e.response?.data?.message ||
                    (e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
                    "退款失败";
                ElMessage.error(msg);
            }
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除退货单 "${row.return_number}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`sales-returns/${row.id}`);
                ElMessage.success("删除成功");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        const submitForm = async () => {
            if (isViewMode.value) {
                dialogVisible.value = false;
                return;
            }
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            try {
                const payload = {
                    customer_id: form.customer_id,
                    sale_id: form.sale_id || null,
                    warehouse_id: form.warehouse_id,
                    return_date: form.return_date,
                    total_amount: form.total_amount,
                    status: form.status || "pending",
                    reason: form.reason,
                    notes: form.notes || null,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`sales-returns/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("sales-returns", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "提交失败");
            }
        };

        // 检查路由参数并打开新增弹窗
        const checkNewAction = () => {
            if (route.query.action === "new") {
                openAdd();
                router.replace({ path: route.path, query: {} });
            }
        };

        onMounted(() => {
            loadList();
            checkNewAction();
        });

        // keep-alive 缓存组件重新激活时触发
        onActivated(() => {
            checkNewAction();
        });

        // 监听路由变化
        watch(
            () => route.query.action,
            (newAction) => {
                if (newAction === "new") {
                    openAdd();
                    router.replace({ path: route.path, query: {} });
                }
            }
        );

        return {
            Plus,
            loading,
            searchKeyword,
            statusFilter,
            list,
            pagination,
            dialogVisible,
            form,
            formRules,
            formRef,
            customers,
            salesOrders,
            formatDate,
            statusText,
            statusTagType,
            loadList,
            handleSearch,
            openAdd,
            submitForm,
            dialogTitle,
            isViewMode,
            warehouses,
            viewDetail,
            editRow,
            processRow,
            refundRow,
            removeRow,
        };
    },
});
</script>

<style scoped>
.sales-return-container {
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
</style>
