<template>
    <div class="business-agent-management-container">
        <div class="page-header">
            <h3>业务员管理</h3>
            <div class="header-actions">
                <el-input v-model="searchKeyword" placeholder="编号/姓名/电话/邮箱" style="width: 260px; margin-right: 12px" clearable @keyup="(e) => e.key === 'Enter' && handleSearch()" />
                <el-select v-model="statusFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="loadList">
                    <el-option label="在职" value="active" />
                    <el-option label="停用" value="inactive" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增业务员</el-button>
            </div>
        </div>

        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="agent_code" label="编号" width="120" />
                <el-table-column prop="name" label="姓名" min-width="120" />
                <el-table-column prop="phone" label="电话" width="130" />
                <el-table-column prop="email" label="邮箱" min-width="180" show-overflow-tooltip />
                <el-table-column prop="commission_rate" label="提成(%)" width="100" align="right">
                    <template #default="{ row }">{{ Number(row.commission_rate || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="territory" label="负责区域" min-width="140" show-overflow-tooltip />
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status === "active" ? "在职" : "停用" }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">查看</el-button>
                        <el-button size="small" type="primary" @click="editRow(row)">编辑</el-button>
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

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="680px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="编号" prop="agent_code">
                            <el-input v-model="form.agent_code" :disabled="isViewMode" placeholder="可留空自动生成" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="姓名" prop="name">
                            <el-input v-model="form.name" :disabled="isViewMode" placeholder="请输入姓名" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="电话" prop="phone">
                            <el-input v-model="form.phone" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="邮箱" prop="email">
                            <el-input v-model="form.email" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="提成(%)" prop="commission_rate">
                            <el-input-number v-model="form.commission_rate" :disabled="isViewMode" :min="0" :max="100" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="状态" prop="status">
                            <el-select v-model="form.status" :disabled="isViewMode" style="width: 100%">
                                <el-option label="在职" value="active" />
                                <el-option label="停用" value="inactive" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="负责区域" prop="territory">
                            <el-input v-model="form.territory" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="备注" prop="notes">
                            <el-input v-model="form.notes" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                </el-row>
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
    name: "BusinessAgentManagement",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const statusFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });

        const dialogVisible = ref(false);
        const dialogTitle = ref("新增业务员");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const form = reactive({
            id: null,
            agent_code: "",
            name: "",
            phone: "",
            email: "",
            commission_rate: 0,
            territory: "",
            status: "active",
            notes: "",
        });

        const rules = {
            name: [{ required: true, message: "请输入姓名", trigger: "blur" }],
        };

        const formatDateTime = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("business-agents", {
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
                ElMessage.error(e.response?.data?.message || "加载业务员列表失败");
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
            form.agent_code = "";
            form.name = "";
            form.phone = "";
            form.email = "";
            form.commission_rate = 0;
            form.territory = "";
            form.status = "active";
            form.notes = "";
        };

        const openAdd = () => {
            resetForm();
            dialogTitle.value = "新增业务员";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const editRow = (row) => {
            resetForm();
            Object.assign(form, {
                id: row.id,
                agent_code: row.agent_code,
                name: row.name,
                phone: row.phone || "",
                email: row.email || "",
                commission_rate: Number(row.commission_rate || 0),
                territory: row.territory || "",
                status: row.status || "active",
                notes: row.notes || "",
            });
            dialogTitle.value = "编辑业务员";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const viewDetail = (row) => {
            editRow(row);
            dialogTitle.value = "业务员详情";
            isEdit.value = false;
            isViewMode.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除业务员 "${row.name}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`business-agents/${row.id}`);
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
            } catch (_) {
                return;
            }
            try {
                const payload = {
                    agent_code: form.agent_code || null,
                    name: form.name,
                    phone: form.phone || null,
                    email: form.email || null,
                    commission_rate: form.commission_rate,
                    territory: form.territory || null,
                    status: form.status || "active",
                    notes: form.notes || null,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`business-agents/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("business-agents", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        onMounted(() => loadList());

        return {
            loading,
            searchKeyword,
            statusFilter,
            list,
            pagination,
            dialogVisible,
            dialogTitle,
            isViewMode,
            form,
            rules,
            formRef,
            formatDateTime,
            loadList,
            handleSearch,
            openAdd,
            editRow,
            viewDetail,
            removeRow,
            submitForm,
        };
    },
});
</script>

<style scoped>
.business-agent-management-container {
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

