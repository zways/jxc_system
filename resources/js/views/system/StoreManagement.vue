<template>
    <div class="store-management-container">
        <div class="page-header">
            <h3>门店管理</h3>
            <div class="header-actions">
                <el-input v-model="searchKeyword" placeholder="编码/名称/负责人/地址" style="width: 260px; margin-right: 12px" clearable @keyup="(e) => e.key === 'Enter' && handleSearch()" />
                <el-select v-model="typeFilter" placeholder="类型" clearable style="width: 150px; margin-right: 12px" @change="loadList">
                    <el-option label="零售" value="retail" />
                    <el-option label="批发" value="wholesale" />
                    <el-option label="线上" value="online" />
                    <el-option label="混合" value="hybrid" />
                </el-select>
                <el-select v-model="activeFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="loadList">
                    <el-option label="启用" :value="true" />
                    <el-option label="禁用" :value="false" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增门店</el-button>
            </div>
        </div>

        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="store_code" label="门店编码" width="140" />
                <el-table-column prop="name" label="门店名称" min-width="160" />
                <el-table-column label="上级门店" width="160" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.parent?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTag(row.type)">{{ typeText(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="manager" label="负责人" width="120" />
                <el-table-column prop="phone" label="电话" width="130" />
                <el-table-column prop="address" label="地址" min-width="180" show-overflow-tooltip />
                <el-table-column label="状态" width="90">
                    <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? "启用" : "禁用" }}</el-tag></template>
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
                        <el-form-item label="门店编码" prop="store_code">
                            <el-input v-model="form.store_code" :disabled="isViewMode" placeholder="可留空自动生成" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="门店名称" prop="name">
                            <el-input v-model="form.name" :disabled="isViewMode" placeholder="请输入门店名称" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="类型" prop="type">
                            <el-select v-model="form.type" :disabled="isViewMode" style="width: 100%">
                                <el-option label="零售" value="retail" />
                                <el-option label="批发" value="wholesale" />
                                <el-option label="线上" value="online" />
                                <el-option label="混合" value="hybrid" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="上级门店" prop="parent_store_id">
                            <el-select v-model="form.parent_store_id" :disabled="isViewMode" clearable filterable style="width: 100%" placeholder="可选">
                                <el-option v-for="s in storeOptions" :key="s.id" :label="`${s.store_code} ${s.name}`" :value="s.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="负责人" prop="manager">
                            <el-input v-model="form.manager" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="电话" prop="phone">
                            <el-input v-model="form.phone" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="地址" prop="address">
                            <el-input v-model="form.address" :disabled="isViewMode" placeholder="选填" />
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
    name: "StoreManagement",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const typeFilter = ref("");
        const activeFilter = ref(null);
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });

        const storeOptions = ref([]);

        const dialogVisible = ref(false);
        const dialogTitle = ref("新增门店");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const form = reactive({
            id: null,
            store_code: "",
            name: "",
            manager: "",
            phone: "",
            address: "",
            type: "retail",
            is_active: true,
            parent_store_id: null,
            notes: "",
        });

        const rules = {
            name: [{ required: true, message: "请输入门店名称", trigger: "blur" }],
        };

        const formatDateTime = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const typeText = (t) => ({ retail: "零售", wholesale: "批发", online: "线上", hybrid: "混合" }[t] || t);
        const typeTag = (t) => ({ retail: "success", wholesale: "primary", online: "warning", hybrid: "info" }[t] || "info");

        const loadOptions = async () => {
            try {
                const res = await window.axios.get("stores", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                storeOptions.value = data || [];
            } catch (_) {
                storeOptions.value = [];
            }
        };

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("stores", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        type: typeFilter.value || undefined,
                        is_active: activeFilter.value === null ? undefined : activeFilter.value,
                    },
                });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data || [];
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                list.value = [];
                ElMessage.error(e.response?.data?.message || "加载门店列表失败");
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
            form.store_code = "";
            form.name = "";
            form.manager = "";
            form.phone = "";
            form.address = "";
            form.type = "retail";
            form.is_active = true;
            form.parent_store_id = null;
            form.notes = "";
        };

        const openAdd = async () => {
            resetForm();
            dialogTitle.value = "新增门店";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
            await loadOptions();
        };

        const editRow = async (row) => {
            resetForm();
            Object.assign(form, {
                id: row.id,
                store_code: row.store_code,
                name: row.name,
                manager: row.manager || "",
                phone: row.phone || "",
                address: row.address || "",
                type: row.type || "retail",
                is_active: !!row.is_active,
                parent_store_id: row.parent?.id ?? row.parent_store_id ?? null,
                notes: row.notes || "",
            });
            dialogTitle.value = "编辑门店";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
            await loadOptions();
        };

        const viewDetail = async (row) => {
            await editRow(row);
            dialogTitle.value = "门店详情";
            isEdit.value = false;
            isViewMode.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除门店 "${row.name}" 吗？（存在下级门店将无法删除）`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`stores/${row.id}`);
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
                    store_code: form.store_code || null,
                    name: form.name,
                    manager: form.manager || null,
                    phone: form.phone || null,
                    address: form.address || null,
                    type: form.type || "retail",
                    is_active: form.is_active,
                    parent_store_id: form.parent_store_id || null,
                    notes: form.notes || null,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`stores/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("stores", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        onMounted(async () => {
            await loadOptions();
            loadList();
        });

        return {
            loading,
            searchKeyword,
            typeFilter,
            activeFilter,
            list,
            pagination,
            storeOptions,
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
.store-management-container {
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

