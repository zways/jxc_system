<template>
    <div class="exchange-record-container">
        <div class="page-header">
            <h3>换货管理</h3>
            <div class="header-actions">
                <el-input v-model="searchKeyword" placeholder="换货单号/客户/原因" style="width: 260px; margin-right: 12px" clearable @keyup="(e) => e.key === 'Enter' && handleSearch()" />
                <el-select v-model="statusFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="待处理" value="pending" />
                    <el-option label="已完成" value="completed" />
                    <el-option label="已取消" value="cancelled" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增换货单</el-button>
            </div>
        </div>

        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="exchange_number" label="换货单号" width="160" />
                <el-table-column label="客户" min-width="140">
                    <template #default="{ row }">{{ row.customer?.name || "-" }}</template>
                </el-table-column>
                <el-table-column label="销售订单" width="140">
                    <template #default="{ row }">{{ row.sale?.order_number || "-" }}</template>
                </el-table-column>
                <el-table-column prop="exchange_date" label="换货日期" width="120" />
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }"><el-tag :type="statusTag(row.status)">{{ statusText(row.status) }}</el-tag></template>
                </el-table-column>
                <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
                <el-table-column prop="notes" label="备注" min-width="140" show-overflow-tooltip />
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">查看</el-button>
                        <el-button size="small" type="primary" @click="editRow(row)">编辑</el-button>
                        <el-button
                            v-if="row.status !== 'completed' && row.status !== 'cancelled'"
                            size="small"
                            type="success"
                            @click="completeRow(row)"
                        >完成</el-button>
                        <el-button size="small" type="danger" @click="removeRow(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-container">
                <el-pagination
                    v-model:current-page="pagination.currentPage"
                    v-model:page-size="pagination.pageSize"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="pagination.total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="650px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item label="换货单号" prop="exchange_number">
                    <el-input v-model="form.exchange_number" :disabled="isViewMode" placeholder="如：EX202602070001" />
                </el-form-item>
                <el-form-item label="客户" prop="customer_id">
                    <el-select v-model="form.customer_id" :disabled="isViewMode" filterable style="width: 100%" placeholder="请选择">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="销售订单" prop="sale_id">
                    <el-select v-model="form.sale_id" :disabled="isViewMode" filterable style="width: 100%" placeholder="请选择">
                        <el-option v-for="s in salesOrders" :key="s.id" :label="s.order_number" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="换货日期" prop="exchange_date">
                    <el-date-picker v-model="form.exchange_date" :disabled="isViewMode" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>
                <el-form-item label="状态" prop="status">
                    <el-select v-model="form.status" :disabled="isViewMode" style="width: 100%">
                        <el-option label="待处理" value="pending" />
                        <el-option label="已完成" value="completed" />
                        <el-option label="已取消" value="cancelled" />
                    </el-select>
                </el-form-item>
                <el-form-item label="原因" prop="reason">
                    <el-input v-model="form.reason" :disabled="isViewMode" placeholder="请输入原因" />
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
import { ref, reactive, onMounted, defineComponent } from "vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";

export default defineComponent({
    name: "ExchangeRecord",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const statusFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });

        const customers = ref([]);
        const salesOrders = ref([]);

        const dialogVisible = ref(false);
        const dialogTitle = ref("新增换货单");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const form = reactive({
            id: null,
            exchange_number: "",
            sale_id: null,
            customer_id: null,
            exchange_date: "",
            status: "pending",
            reason: "",
            exchanged_by: 1,
            notes: "",
        });

        const rules = {
            sale_id: [{ required: true, message: "请选择销售订单", trigger: "change" }],
            customer_id: [{ required: true, message: "请选择客户", trigger: "change" }],
            exchange_date: [{ required: true, message: "请选择日期", trigger: "change" }],
            status: [{ required: true, message: "请选择状态", trigger: "change" }],
            reason: [{ required: true, message: "请输入原因", trigger: "blur" }],
        };

        const formatDateTime = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const statusText = (s) => ({ pending: "待处理", completed: "已完成", cancelled: "已取消" }[s] || s);
        const statusTag = (s) => ({ pending: "warning", completed: "success", cancelled: "info" }[s] || "info");

        const loadCustomers = async () => {
            try {
                const res = await window.axios.get("customers", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                customers.value = data || [];
            } catch (_) {
                customers.value = [];
            }
        };
        const loadSalesOrders = async () => {
            try {
                const res = await window.axios.get("sales-orders", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                salesOrders.value = data || [];
            } catch (_) {
                salesOrders.value = [];
            }
        };

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("exchange-records", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        status: statusFilter.value || undefined,
                    },
                });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data || [];
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                list.value = [];
                ElMessage.error(e.response?.data?.message || "加载换货列表失败");
            } finally {
                loading.value = false;
            }
        };

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };

        const resetForm = () => {
            form.id = null;
            form.exchange_number = "";
            form.sale_id = null;
            form.customer_id = null;
            form.exchange_date = new Date().toISOString().slice(0, 10);
            form.status = "pending";
            form.reason = "";
            form.exchanged_by = 1;
            form.notes = "";
        };

        const openAdd = async () => {
            resetForm();
            dialogTitle.value = "新增换货单";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
            await Promise.all([loadCustomers(), loadSalesOrders()]);
        };

        const editRow = async (row) => {
            resetForm();
            Object.assign(form, {
                id: row.id,
                exchange_number: row.exchange_number,
                sale_id: row.sale_id,
                customer_id: row.customer_id,
                exchange_date: row.exchange_date,
                status: row.status || "pending",
                reason: row.reason || "",
                exchanged_by: row.exchanged_by || 1,
                notes: row.notes || "",
            });
            dialogTitle.value = "编辑换货单";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
            await Promise.all([loadCustomers(), loadSalesOrders()]);
        };

        const viewDetail = async (row) => {
            await editRow(row);
            dialogTitle.value = "换货详情";
            isEdit.value = false;
            isViewMode.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除换货单 "${row.exchange_number}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`exchange-records/${row.id}`);
                ElMessage.success("删除成功");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        const completeRow = async (row) => {
            if (!row?.id) return;
            try {
                await ElMessageBox.confirm(
                    `确定将换货单「${row.exchange_number || row.id}」标记为已完成吗？\n系统将基于原销售单明细生成换货库存流水（退回入库 + 发出出库）。`,
                    "完成确认",
                    { type: "warning", confirmButtonText: "确定完成", cancelButtonText: "取消" }
                );
                await window.axios.put(`exchange-records/${row.id}/complete`);
                ElMessage.success("完成成功");
                loadList();
            } catch (e) {
                if (e === "cancel" || e?.message === "cancel") return;
                const msg =
                    e.response?.data?.message ||
                    (e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
                    "完成失败";
                ElMessage.error(msg);
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
                    exchange_number: form.exchange_number,
                    sale_id: form.sale_id,
                    customer_id: form.customer_id,
                    exchange_date: form.exchange_date,
                    status: form.status,
                    reason: form.reason,
                    notes: form.notes || null,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`exchange-records/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("exchange-records", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        onMounted(async () => {
            await Promise.all([loadCustomers(), loadSalesOrders()]);
            loadList();
        });

        return {
            loading,
            searchKeyword,
            statusFilter,
            list,
            pagination,
            customers,
            salesOrders,
            dialogVisible,
            dialogTitle,
            isViewMode,
            form,
            rules,
            formRef,
            formatDateTime,
            statusText,
            statusTag,
            loadList,
            handleSearch,
            openAdd,
            editRow,
            viewDetail,
            removeRow,
            completeRow,
            submitForm,
        };
    },
});
</script>

<style scoped>
.exchange-record-container {
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

