<template>
    <div class="warehouse-management-container">
        <div class="page-header">
            <h3>仓库管理</h3>
            <div class="header-actions">
                <el-input v-model="searchKeyword" placeholder="仓库名称/编码/位置" style="width: 260px; margin-right: 12px" clearable @keyup="(e) => e.key === 'Enter' && handleSearch()" />
                <el-select v-model="typeFilter" placeholder="类型" clearable style="width: 150px; margin-right: 12px" @change="handleSearch">
                    <el-option label="常规仓库" value="normal" />
                    <el-option label="冷链仓库" value="frozen" />
                    <el-option label="液体仓库" value="liquid" />
                </el-select>
                <el-select v-model="activeFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="启用" :value="true" />
                    <el-option label="禁用" :value="false" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增仓库</el-button>
            </div>
        </div>

        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="code" label="仓库编码" width="120" />
                <el-table-column prop="name" label="仓库名称" min-width="150" />
                <el-table-column prop="type" label="类型" width="110">
                    <template #default="{ row }">
                        <el-tag :type="typeTag(row.type)">{{ typeText(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="location" label="位置" min-width="160" show-overflow-tooltip />
                <el-table-column prop="manager" label="管理员" width="120" />
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? "启用" : "禁用" }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="210" fixed="right">
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

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="700px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="仓库编码" prop="code">
                            <el-input v-model="form.code" :disabled="isViewMode" placeholder="如：WH001" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="仓库名称" prop="name">
                            <el-input v-model="form.name" :disabled="isViewMode" placeholder="请输入仓库名称" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="类型" prop="type">
                            <el-select v-model="form.type" :disabled="isViewMode" style="width: 100%">
                                <el-option label="常规仓库" value="normal" />
                                <el-option label="冷链仓库" value="frozen" />
                                <el-option label="液体仓库" value="liquid" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="管理员" prop="manager">
                            <el-input v-model="form.manager" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="位置" prop="location">
                            <el-input v-model="form.location" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="描述" prop="description">
                            <el-input v-model="form.description" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="备注" prop="notes">
                            <el-input v-model="form.notes" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="状态" prop="is_active">
                            <el-select v-model="form.is_active" :disabled="isViewMode" style="width: 100%">
                                <el-option label="启用" :value="true" />
                                <el-option label="禁用" :value="false" />
                            </el-select>
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
    name: "WarehouseManagement",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const typeFilter = ref("");
        const activeFilter = ref(null);
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });

        const dialogVisible = ref(false);
        const dialogTitle = ref("新增仓库");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const form = reactive({
            id: null,
            code: "",
            name: "",
            location: "",
            manager: "",
            description: "",
            type: "normal",
            is_active: true,
            notes: "",
        });

        const rules = {
            code: [{ required: true, message: "请输入仓库编码", trigger: "blur" }],
            name: [{ required: true, message: "请输入仓库名称", trigger: "blur" }],
            type: [{ required: true, message: "请选择类型", trigger: "change" }],
        };

        const formatDateTime = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const typeText = (t) => ({ normal: "常规", frozen: "冷链", liquid: "液体" }[t] || t);
        const typeTag = (t) => ({ normal: "info", frozen: "primary", liquid: "warning" }[t] || "info");

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("warehouses", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        is_active: activeFilter.value === null ? undefined : activeFilter.value,
                        type: typeFilter.value || undefined,
                    },
                });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data || [];
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                list.value = [];
                ElMessage.error(e.response?.data?.message || "加载仓库列表失败");
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
            form.code = "";
            form.name = "";
            form.location = "";
            form.manager = "";
            form.description = "";
            form.type = "normal";
            form.is_active = true;
            form.notes = "";
        };

        const openAdd = () => {
            resetForm();
            isEdit.value = false;
            isViewMode.value = false;
            dialogTitle.value = "新增仓库";
            dialogVisible.value = true;
        };

        const editRow = (row) => {
            isEdit.value = true;
            isViewMode.value = false;
            dialogTitle.value = "编辑仓库";
            dialogVisible.value = true;
            Object.assign(form, row);
        };

        const viewDetail = (row) => {
            isEdit.value = false;
            isViewMode.value = true;
            dialogTitle.value = "仓库详情";
            dialogVisible.value = true;
            Object.assign(form, row);
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除仓库 "${row.name}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`warehouses/${row.id}`);
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
                resetForm();
                return;
            }
            try {
                await formRef.value.validate();
            } catch (_) {
                return;
            }
            try {
                const payload = {
                    code: form.code,
                    name: form.name,
                    location: form.location || null,
                    manager: form.manager || null,
                    description: form.description || null,
                    type: form.type,
                    is_active: form.is_active,
                    notes: form.notes || null,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`warehouses/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("warehouses", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                resetForm();
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        onMounted(() => loadList());

        return {
            loading,
            searchKeyword,
            typeFilter,
            activeFilter,
            list,
            pagination,
            dialogVisible,
            dialogTitle,
            isViewMode,
            form,
            rules,
            formRef,
            formatDateTime,
            typeText,
            typeTag,
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
.warehouse-management-container {
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

