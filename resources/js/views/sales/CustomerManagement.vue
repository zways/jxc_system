<template>
    <div class="customer-management-container">
        <div class="page-header">
            <h3>客户管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="搜索客户名称或编码"
                    style="width: 300px; margin-right: 15px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增客户</el-button>
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
                <el-table-column prop="customer_code" label="客户编码" width="120" />
                <el-table-column prop="name" label="客户名称" min-width="150" />
                <el-table-column prop="contact_person" label="联系人" width="120" />
                <el-table-column prop="phone" label="联系电话" width="140" />
                <el-table-column prop="email" label="邮箱" width="180" />
                <el-table-column prop="address" label="地址" min-width="200" show-overflow-tooltip />
                <el-table-column prop="customer_level" label="客户等级" width="120" />
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

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="640px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="客户编码" prop="customer_code">
                            <el-input v-model="form.customer_code" :disabled="isViewMode" placeholder="如：CUST001" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="客户名称" prop="name">
                            <el-input v-model="form.name" :disabled="isViewMode" placeholder="请输入客户名称" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="联系人" prop="contact_person">
                            <el-input v-model="form.contact_person" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="联系电话" prop="phone">
                            <el-input v-model="form.phone" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="邮箱" prop="email">
                            <el-input v-model="form.email" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="客户等级" prop="customer_level">
                            <el-input v-model="form.customer_level" :disabled="isViewMode" placeholder="如：VIP客户" />
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

export default defineComponent({
    name: "CustomerManagement",
    setup() {
        const searchKeyword = ref("");
        const loading = ref(false);
        const list = ref([]);
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增客户");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const pagination = reactive({
            currentPage: 1,
            pageSize: 20,
            total: 0,
        });
        const form = reactive({
            id: null,
            customer_code: "",
            name: "",
            contact_person: "",
            phone: "",
            email: "",
            address: "",
            customer_level: "普通客户",
            notes: "",
            is_active: true,
        });

        const rules = {
            customer_code: [{ required: true, message: "请输入客户编码", trigger: "blur" }],
            name: [{ required: true, message: "请输入客户名称", trigger: "blur" }],
        };

        const formatDate = (v) => {
            if (!v) return "";
            const s = typeof v === "string" ? v : String(v);
            return s.slice(0, 10);
        };

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("customers", {
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
                    ElMessage.error(err.response?.data?.message || "加载客户列表失败");
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
            form.customer_code = "";
            form.name = "";
            form.contact_person = "";
            form.phone = "";
            form.email = "";
            form.address = "";
            form.customer_level = "普通客户";
            form.notes = "";
            form.is_active = true;
        };

        const openAdd = () => {
            resetForm();
            dialogTitle.value = "新增客户";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const editRow = (row) => {
            Object.assign(form, row);
            dialogTitle.value = "编辑客户";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        const viewDetail = (row) => {
            Object.assign(form, row);
            dialogTitle.value = "客户详情";
            isEdit.value = false;
            isViewMode.value = true;
            dialogVisible.value = true;
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定要删除客户 "${row.name}" 吗？`, "删除确认", {
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                    type: "warning",
                });
                await window.axios.delete(`customers/${row.id}`);
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
            } catch {
                return;
            }
            try {
                const payload = {
                    customer_code: form.customer_code,
                    name: form.name,
                    contact_person: form.contact_person || null,
                    phone: form.phone || null,
                    email: form.email || null,
                    address: form.address || null,
                    customer_level: form.customer_level || "普通客户",
                    notes: form.notes || null,
                    is_active: form.is_active,
                };
                if (isEdit.value) {
                    await window.axios.put(`customers/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("customers", payload);
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
.customer-management-container {
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
