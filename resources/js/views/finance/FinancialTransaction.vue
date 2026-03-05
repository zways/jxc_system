<template>
    <div class="financial-transaction-container">
        <div class="page-header">
            <h3>收支明细</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="流水号/描述"
                    style="width: 240px; margin-right: 12px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-select v-model="typeFilter" placeholder="类型" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="收入" value="revenue" />
                    <el-option label="支出" value="expense" />
                    <el-option label="收款" value="receipt" />
                    <el-option label="付款" value="payment" />
                </el-select>
                <el-select v-model="statusFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="草稿" value="draft" />
                    <el-option label="已过账" value="posted" />
                    <el-option label="已作废" value="voided" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增流水</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="transaction_number" label="流水号" width="150" />
                <el-table-column prop="type" label="类型" width="90">
                    <template #default="{ row }">{{ typeText(row.type) }}</template>
                </el-table-column>
                <el-table-column prop="category" label="类别" width="120" show-overflow-tooltip />
                <el-table-column prop="amount" label="金额" width="110" align="right">
                    <template #default="{ row }">
                        <span :class="isIncome(row) ? 'text-income' : 'text-expense'">
                            {{ isIncome(row) ? "+" : "-" }}¥{{ Number(row.amount || 0).toFixed(2) }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column prop="currency" label="币种" width="70" />
                <el-table-column prop="transaction_date" label="交易日期" width="120" />
                <el-table-column prop="description" label="描述" min-width="160" show-overflow-tooltip />
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
                        <el-button size="small" type="primary" :disabled="row.status === 'voided'" @click="editRow(row)">编辑</el-button>
                        <el-button
                            size="small"
                            type="warning"
                            :disabled="row.status === 'voided'"
                            @click="voidRow(row)"
                        >作废</el-button>
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

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="640px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item label="类型" prop="type">
                    <el-select v-model="form.type" :disabled="isViewMode" style="width: 100%">
                        <el-option label="收入" value="revenue" />
                        <el-option label="支出" value="expense" />
                        <el-option label="收款" value="receipt" />
                        <el-option label="付款" value="payment" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类别" prop="category">
                    <el-input v-model="form.category" :disabled="isViewMode" placeholder="如：general/工资/房租" />
                </el-form-item>
                <el-form-item label="金额" prop="amount">
                    <el-input-number v-model="form.amount" :disabled="isViewMode" :min="0.01" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item label="币种" prop="currency">
                    <el-input v-model="form.currency" :disabled="isViewMode" placeholder="CNY" maxlength="3" />
                </el-form-item>
                <el-form-item label="交易日期" prop="transaction_date">
                    <el-date-picker v-model="form.transaction_date" :disabled="isViewMode" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" :disabled="isViewMode" placeholder="选填" />
                </el-form-item>
                <el-form-item label="状态" prop="status">
                    <el-select v-model="form.status" :disabled="isViewMode" style="width: 100%">
                        <el-option label="草稿" value="draft" />
                        <el-option label="已过账" value="posted" />
                        <el-option label="已作废" value="voided" />
                    </el-select>
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
    name: "FinancialTransaction",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const typeFilter = ref("");
        const statusFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增流水");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const form = reactive({
            id: null,
            type: "revenue",
            category: "general",
            amount: 0,
            currency: "CNY",
            transaction_date: new Date().toISOString().slice(0, 10),
            description: "",
            status: "draft",
            notes: "",
        });
        const rules = {
            type: [{ required: true, message: "请选择类型", trigger: "change" }],
            category: [{ required: true, message: "请输入类别", trigger: "blur" }],
            amount: [{ required: true, message: "请输入金额", trigger: "blur" }],
            currency: [{ required: true, message: "请输入币种", trigger: "blur" }],
            transaction_date: [{ required: true, message: "请选择日期", trigger: "change" }],
            status: [{ required: true, message: "请选择状态", trigger: "change" }],
        };

        const formatDate = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const isIncome = (row) => {
            const t = row.type;
            return t === "revenue" || t === "receipt";
        };
        const typeText = (t) => ({ revenue: "收入", expense: "支出", receipt: "收款", payment: "付款" }[t] || t);
        const statusText = (s) => ({ posted: "已过账", draft: "草稿", voided: "已作废" }[s] || s);
        const statusTagType = (s) => ({ posted: "success", draft: "info", voided: "danger" }[s] || "info");

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };
        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("financial-transactions", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        type: typeFilter.value || undefined,
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

        const resetForm = () => {
            form.id = null;
            form.type = "revenue";
            form.category = "general";
            form.amount = 0;
            form.currency = "CNY";
            form.transaction_date = new Date().toISOString().slice(0, 10);
            form.description = "";
            form.status = "draft";
            form.notes = "";
        };

        const openAdd = () => {
            resetForm();
            dialogTitle.value = "新增流水";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const editRow = (row) => {
            resetForm();
            Object.assign(form, {
                id: row.id,
                type: row.type || "revenue",
                category: row.category || "general",
                amount: Number(row.amount || 0),
                currency: row.currency || "CNY",
                transaction_date: row.transaction_date || new Date().toISOString().slice(0, 10),
                description: row.description || "",
                status: row.status || "draft",
                notes: row.notes || "",
            });
            dialogTitle.value = "编辑流水";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const viewDetail = (row) => {
            editRow(row);
            dialogTitle.value = "流水详情";
            isEdit.value = false;
            isViewMode.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除流水 "${row.transaction_number}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`financial-transactions/${row.id}`);
                ElMessage.success("删除成功");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        const voidRow = async (row) => {
            if (!row?.id || row.status === "voided") return;
            try {
                await ElMessageBox.confirm(
                    `确定作废流水 "${row.transaction_number}" 吗？\n若该流水为“收款/付款”且关联应收/应付，将自动回滚已收/已付与余额状态。`,
                    "作废确认",
                    {
                        type: "warning",
                        confirmButtonText: "确定作废",
                        cancelButtonText: "取消",
                    }
                );
                await window.axios.put(`financial-transactions/${row.id}/void`);
                ElMessage.success("作废成功");
                loadList();
            } catch (e) {
                if (e === "cancel" || e?.message === "cancel") return;
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查数据" : "作废失败"));
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
                    type: form.type,
                    category: form.category,
                    amount: form.amount,
                    currency: form.currency,
                    transaction_date: form.transaction_date,
                    description: form.description || null,
                    status: form.status,
                    notes: form.notes || null,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`financial-transactions/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("financial-transactions", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        return {
            loading,
            searchKeyword,
            typeFilter,
            statusFilter,
            list,
            pagination,
            formatDate,
            isIncome,
            typeText,
            statusText,
            statusTagType,
            loadList,
            handleSearch,
            dialogVisible,
            dialogTitle,
            isViewMode,
            form,
            rules,
            formRef,
            openAdd,
            editRow,
            viewDetail,
            removeRow,
            voidRow,
            submitForm,
        };
    },
});
</script>

<style scoped>
.financial-transaction-container {
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
.text-income {
    color: #67c23a;
}
.text-expense {
    color: #f56c6c;
}
</style>
