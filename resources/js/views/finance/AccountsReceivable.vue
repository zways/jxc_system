<template>
    <div class="accounts-receivable-container">
        <div class="page-header">
            <h3>应收账款管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="客户/单据"
                    style="width: 240px; margin-right: 12px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-select v-model="statusFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="未收" value="unpaid" />
                    <el-option label="已收" value="paid" />
                    <el-option label="逾期" value="overdue" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增应收</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column label="客户" min-width="140">
                    <template #default="{ row }">{{ row.customer?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="document_type" label="单据类型" width="100" />
                <el-table-column prop="document_id" label="单据ID" width="90" />
                <el-table-column prop="document_date" label="单据日期" width="120" />
                <el-table-column prop="amount" label="金额" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.amount || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="paid_amount" label="已收" width="90" align="right">
                    <template #default="{ row }">¥{{ Number(row.paid_amount || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="balance" label="余额" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.balance || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="due_date" label="到期日" width="120" />
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">查看</el-button>
                        <el-button size="small" type="primary" @click="editRow(row)">编辑</el-button>
                        <el-button
                            size="small"
                            type="success"
                            :disabled="Number(row.balance || 0) <= 0"
                            @click="collectRow(row)"
                        >收款</el-button>
                        <el-button size="small" type="danger" @click="removeRow(row)">删除</el-button>
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

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="680px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item label="客户" prop="customer_id">
                    <el-select v-model="form.customer_id" :disabled="isViewMode" filterable style="width: 100%" placeholder="请选择">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="单据类型" prop="document_type">
                            <el-input v-model="form.document_type" :disabled="isViewMode" placeholder="如：sales_order" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="单据ID" prop="document_id">
                            <el-input-number v-model="form.document_id" :disabled="isViewMode" :min="1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="单据日期" prop="document_date">
                            <el-date-picker v-model="form.document_date" :disabled="isViewMode" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="到期日" prop="due_date">
                            <el-date-picker v-model="form.due_date" :disabled="isViewMode" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="金额" prop="amount">
                            <el-input-number v-model="form.amount" :disabled="isViewMode" :min="0.01" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="已收" prop="paid_amount">
                            <el-input-number v-model="form.paid_amount" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="备注" prop="notes">
                    <el-input v-model="form.notes" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
                </el-form-item>
                <el-alert
                    v-if="!isViewMode"
                    title="余额与状态会在提交时自动计算（余额=金额-已收）"
                    type="info"
                    :closable="false"
                    show-icon
                />
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
    name: "AccountsReceivable",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const statusFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增应收");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const customers = ref([]);
        const form = reactive({
            id: null,
            customer_id: null,
            document_type: "",
            document_id: null,
            document_date: "",
            amount: 0,
            paid_amount: 0,
            due_date: "",
            notes: "",
        });
        const rules = {
            customer_id: [{ required: true, message: "请选择客户", trigger: "change" }],
            document_type: [{ required: true, message: "请输入单据类型", trigger: "blur" }],
            document_id: [{ required: true, message: "请输入单据ID", trigger: "blur" }],
            document_date: [{ required: true, message: "请选择单据日期", trigger: "change" }],
            due_date: [{ required: true, message: "请选择到期日", trigger: "change" }],
            amount: [{ required: true, message: "请输入金额", trigger: "blur" }],
            paid_amount: [{ required: false }],
        };

        const formatDate = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const statusText = (s) => ({ unpaid: "未收", paid: "已收", overdue: "逾期" }[s] || s);
        const statusTagType = (s) => ({ unpaid: "warning", paid: "success", overdue: "danger" }[s] || "info");

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };
        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("accounts-receivable", {
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

        onMounted(() => loadList());

        const loadCustomers = async () => {
            try {
                const res = await window.axios.get("customers", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                customers.value = data || [];
            } catch (_) {
                customers.value = [];
            }
        };

        const resetForm = () => {
            form.id = null;
            form.customer_id = null;
            form.document_type = "";
            form.document_id = null;
            form.document_date = new Date().toISOString().slice(0, 10);
            form.amount = 0;
            form.paid_amount = 0;
            form.due_date = new Date().toISOString().slice(0, 10);
            form.notes = "";
        };

        const openAdd = async () => {
            resetForm();
            dialogTitle.value = "新增应收";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
            await loadCustomers();
        };

        const editRow = async (row) => {
            resetForm();
            Object.assign(form, {
                id: row.id,
                customer_id: row.customer_id || row.customer?.id || null,
                document_type: row.document_type,
                document_id: Number(row.document_id || 0) || null,
                document_date: row.document_date,
                amount: Number(row.amount || 0),
                paid_amount: Number(row.paid_amount || 0),
                due_date: row.due_date,
                notes: row.notes || "",
            });
            dialogTitle.value = "编辑应收";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
            await loadCustomers();
        };

        const viewDetail = async (row) => {
            await editRow(row);
            dialogTitle.value = "应收详情";
            isEdit.value = false;
            isViewMode.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除该应收记录（ID=${row.id}）吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`accounts-receivable/${row.id}`);
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
                const amount = Number(form.amount || 0);
                const paid = Number(form.paid_amount || 0);
                const balance = Math.max(0, amount - paid);
                const today = new Date().toISOString().slice(0, 10);
                const status = balance <= 0 ? "paid" : form.due_date < today ? "overdue" : "unpaid";
                const payload = {
                    customer_id: form.customer_id,
                    document_type: form.document_type,
                    document_id: Number(form.document_id),
                    document_date: form.document_date,
                    amount,
                    paid_amount: paid,
                    balance,
                    due_date: form.due_date,
                    status,
                    notes: form.notes || null,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`accounts-receivable/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("accounts-receivable", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        const collectRow = async (row) => {
            const balance = Number(row?.balance || 0);
            if (!row?.id || balance <= 0) return;
            try {
                const { value } = await ElMessageBox.prompt("请输入收款金额", "收款", {
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                    inputValue: String(balance.toFixed(2)),
                    inputPattern: /^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/,
                    inputErrorMessage: "请输入正确金额（最多两位小数）",
                });
                const amount = Number(value);
                if (!Number.isFinite(amount) || amount <= 0) return;
                await window.axios.put(`accounts-receivable/${row.id}/collect`, { amount });
                ElMessage.success("收款成功");
                loadList();
            } catch (e) {
                if (e === "cancel" || e?.message === "cancel") return;
                const msg =
                    e.response?.data?.message ||
                    (e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
                    "收款失败";
                ElMessage.error(msg);
            }
        };

        return {
            loading,
            searchKeyword,
            statusFilter,
            list,
            pagination,
            formatDate,
            statusText,
            statusTagType,
            handleSearch,
            loadList,
            dialogVisible,
            dialogTitle,
            isViewMode,
            customers,
            form,
            rules,
            formRef,
            openAdd,
            editRow,
            viewDetail,
            removeRow,
            collectRow,
            submitForm,
        };
    },
});
</script>

<style scoped>
.accounts-receivable-container {
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
