<template>
    <div class="department-management-container">
        <div class="page-header">
            <h3>部门管理</h3>
            <div class="header-actions">
                <el-input v-model="searchKeyword" placeholder="搜索部门名称/编码" style="width: 240px; margin-right: 12px" clearable />
                <el-button type="primary" :icon="Plus" @click="openAdd">新增部门</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="filteredList" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="部门名称" min-width="140" />
                <el-table-column prop="code" label="部门编码" width="140" />
                <el-table-column prop="description" label="描述" min-width="220" />
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="editRow(row)">编辑</el-button>
                        <el-button size="small" type="danger" @click="removeRow(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑部门' : '新增部门'" width="520px" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="90px">
                <el-form-item label="部门名称" prop="name">
                    <el-input v-model="form.name" placeholder="如：技术部、采购部" />
                </el-form-item>
                <el-form-item label="部门编码" prop="code">
                    <el-input v-model="form.code" placeholder="如：tech、purchase" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="2" placeholder="选填" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitForm">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, computed, defineComponent, onMounted } from "vue";
import { Plus } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";

export default defineComponent({
    name: "DepartmentManagement",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const list = ref([]);
        const dialogVisible = ref(false);
        const isEdit = ref(false);
        const formRef = ref(null);
        const form = reactive({ id: null, name: "", code: "", description: "" });
        const rules = {
            name: [{ required: true, message: "请输入部门名称", trigger: "blur" }],
            code: [{ required: true, message: "请输入部门编码", trigger: "blur" }],
        };

        const filteredList = computed(() => {
            const kw = (searchKeyword.value || "").trim().toLowerCase();
            if (!kw) return list.value;
            return list.value.filter(
                (r) =>
                    (r.name && r.name.toLowerCase().includes(kw)) ||
                    (r.code && r.code.toLowerCase().includes(kw))
            );
        });

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("departments", { params: { per_page: 500 } });
                const payload = res.data;
                const data = payload.data;
                const items = data && data.data ? data.data : Array.isArray(data) ? data : [];
                list.value = items || [];
            } catch (e) {
                list.value = [];
                ElMessage.error(e.response?.data?.message || "加载部门列表失败");
            } finally {
                loading.value = false;
            }
        };

        const openAdd = () => {
            isEdit.value = false;
            form.id = null;
            form.name = "";
            form.code = "";
            form.description = "";
            dialogVisible.value = true;
        };

        const editRow = (row) => {
            isEdit.value = true;
            form.id = row.id;
            form.name = row.name;
            form.code = row.code;
            form.description = row.description || "";
            dialogVisible.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除部门「${row.name}」吗？`, "删除确认", {
                    type: "warning",
                });
                await window.axios.delete(`departments/${row.id}`);
                ElMessage.success("已删除");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        const submitForm = async () => {
            try {
                await formRef.value.validate();
            } catch (_) {
                return;
            }
            if (isEdit.value) {
                await window.axios.put(`departments/${form.id}`, {
                    name: form.name,
                    code: form.code,
                    description: form.description,
                    is_active: true,
                });
                ElMessage.success("修改成功");
            } else {
                await window.axios.post("departments", {
                    name: form.name,
                    code: form.code,
                    description: form.description,
                    is_active: true,
                });
                ElMessage.success("新增成功");
            }
            dialogVisible.value = false;
            loadList();
        };

        onMounted(() => loadList());

        return {
            Plus,
            loading,
            searchKeyword,
            filteredList,
            dialogVisible,
            isEdit,
            form,
            rules,
            formRef,
            openAdd,
            editRow,
            removeRow,
            submitForm,
        };
    },
});
</script>

<style scoped>
.department-management-container {
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
</style>

