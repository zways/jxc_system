<template>
    <div class="product-category-container">
        <div class="page-header">
            <h3>商品分类管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="搜索分类名称或描述"
                    style="width: 300px; margin-right: 15px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增分类</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table
                :data="list"
                v-loading="loading"
                style="width: 100%"
                row-key="id"
                border
            >
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="分类名称" min-width="120" />
                <el-table-column prop="description" label="描述" min-width="200" />
                <el-table-column prop="sort_order" label="排序" width="80" />
                <el-table-column prop="level" label="层级" width="80" />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'">
                            {{ row.is_active ? "启用" : "禁用" }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="created_at"
                    label="创建时间"
                    width="180"
                >
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
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

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item label="分类名称" prop="name">
                    <el-input v-model="form.name" :disabled="isViewMode" placeholder="请输入分类名称" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
                </el-form-item>
                <el-form-item label="排序" prop="sort_order">
                    <el-input-number v-model="form.sort_order" :disabled="isViewMode" :min="0" style="width: 100%" />
                </el-form-item>
                <el-form-item label="状态" prop="is_active">
                    <el-select v-model="form.is_active" :disabled="isViewMode" style="width: 100%">
                        <el-option label="启用" :value="true" />
                        <el-option label="禁用" :value="false" />
                    </el-select>
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

export default defineComponent({
    name: "ProductCategory",
    setup() {
        const searchKeyword = ref("");
        const loading = ref(false);
        const list = ref([]);
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增分类");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const form = reactive({
            id: null,
            name: "",
            description: "",
            sort_order: 0,
            is_active: true,
        });
        const rules = {
            name: [{ required: true, message: "请输入分类名称", trigger: "blur" }],
        };
        const pagination = reactive({
            currentPage: 1,
            pageSize: 20,
            total: 0,
        });

        const formatDate = (v) => {
            if (!v) return "";
            const s = typeof v === "string" ? v : String(v);
            return s.slice(0, 10);
        };

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("product-categories", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                    },
                });
                const payload = res.data;
                const data = payload.data;
                const items = data && data.data ? data.data : (Array.isArray(data) ? data : []);
                const meta = data && data.meta ? data.meta : {};
                list.value = items;
                pagination.total = meta.total ?? items.length;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (err) {
                list.value = [];
                if (err.response?.status !== 404) {
                    ElMessage.error(err.response?.data?.message || "加载分类列表失败");
                }
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
            form.name = "";
            form.description = "";
            form.sort_order = 0;
            form.is_active = true;
        };

        const openAdd = () => {
            resetForm();
            dialogTitle.value = "新增分类";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const editRow = (row) => {
            Object.assign(form, row);
            dialogTitle.value = "编辑分类";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const viewDetail = (row) => {
            Object.assign(form, row);
            dialogTitle.value = "分类详情";
            isEdit.value = false;
            isViewMode.value = true;
            dialogVisible.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除分类 "${row.name}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`product-categories/${row.id}`);
                ElMessage.success("删除成功");
                loadList();
            } catch (e) {
                // 后端可能返回 400：有关联商品/子分类
                if (e?.response?.data?.message) {
                    ElMessage.error(e.response.data.message);
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
                    name: form.name,
                    description: form.description || null,
                    sort_order: form.sort_order,
                    is_active: form.is_active,
                };
                if (isEdit.value && form.id) {
                    await window.axios.put(`product-categories/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("product-categories", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                resetForm();
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        onMounted(() => {
            loadList();
        });

        return {
            searchKeyword,
            loading,
            list,
            pagination,
            formatDate,
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
            submitForm,
        };
    },
});
</script>

<style scoped>
.product-category-container {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.data-card {
    min-height: 400px;
}
.pagination-container {
    margin-top: 20px;
    text-align: right;
}
</style>
