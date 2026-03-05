<template>
    <div class="supplier-management-container">
        <div class="page-header">
            <h3>供应商管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="搜索供应商名称或编码"
                    style="width: 300px; margin-right: 12px"
                    :prefix-icon="Search"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" :icon="Plus" @click="openAddDialog"
                    >新增供应商</el-button
                >
            </div>
        </div>

        <el-card class="data-card">
            <el-table
                :data="supplierList"
                v-loading="loading"
                style="width: 100%"
                row-key="id"
                border
            >
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column
                    prop="name"
                    label="供应商名称"
                    min-width="150"
                />
                <el-table-column
                    prop="contactPerson"
                    label="联系人"
                    width="120"
                />
                <el-table-column prop="phone" label="联系电话" width="150" />
                <el-table-column prop="email" label="邮箱" width="180" />
                <el-table-column prop="address" label="地址" min-width="200" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag
                            :type="row.status === '启用' ? 'success' : 'info'"
                        >
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="createdAt"
                    label="创建时间"
                    width="180"
                />
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)"
                            >查看</el-button
                        >
                        <el-button
                            size="small"
                            type="primary"
                            @click="editSupplier(row)"
                            >编辑</el-button
                        >
                        <el-button
                            size="small"
                            type="danger"
                            @click="deleteSupplier(row)"
                            >删除</el-button
                        >
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
                    @size-change="handleSizeChange"
                    @current-change="handleCurrentChange"
                />
            </div>
        </el-card>

        <!-- 新增/编辑/查看供应商对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="dialogTitle"
            width="600px"
            :close-on-click-modal="false"
            @open="formRef?.clearValidate?.()"
        >
            <el-form
                :model="formModel"
                :rules="formRules"
                ref="formRef"
                label-width="100px"
            >
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="供应商名称" prop="name">
                            <el-input
                                v-model="formModel.name"
                                placeholder="请输入供应商名称"
                                :readonly="isViewMode"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="联系人" prop="contactPerson">
                            <el-input
                                v-model="formModel.contactPerson"
                                placeholder="请输入联系人"
                                :readonly="isViewMode"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="联系电话" prop="phone">
                            <el-input
                                v-model="formModel.phone"
                                placeholder="请输入联系电话"
                                :readonly="isViewMode"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="邮箱" prop="email">
                            <el-input
                                v-model="formModel.email"
                                placeholder="请输入邮箱"
                                :readonly="isViewMode"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="地址" prop="address">
                            <el-input
                                v-model="formModel.address"
                                type="textarea"
                                :rows="3"
                                placeholder="请输入详细地址"
                                :readonly="isViewMode"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="状态" prop="status">
                            <el-select
                                v-model="formModel.status"
                                placeholder="请选择状态"
                                style="width: 100%"
                                :disabled="isViewMode"
                            >
                                <el-option label="启用" value="启用" />
                                <el-option label="禁用" value="禁用" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="供应商编码" prop="code">
                            <el-input
                                v-model="formModel.code"
                                placeholder="请输入供应商编码"
                                :readonly="isViewMode"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <div class="dialog-footer">
                    <template v-if="isViewMode">
                        <el-button type="primary" @click="closeViewDialog"
                            >关闭</el-button
                        >
                    </template>
                    <template v-else>
                        <el-button @click="cancelDialog">取消</el-button>
                        <el-button type="primary" @click="confirmDialog"
                            >确认</el-button
                        >
                    </template>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, onMounted, defineComponent } from "vue";
import { Search, Plus } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";

export default defineComponent({
    name: "SupplierManagement",
    setup() {
        // 搜索关键词
        const searchKeyword = ref("");

        // 加载状态
        const loading = ref(false);

        // 分页信息
        const pagination = reactive({
            currentPage: 1,
            pageSize: 20,
            total: 0,
        });

        // 供应商列表（从 API 加载）
        const supplierList = ref([]);

        // API 行转表格展示行
        const mapApiToDisplay = (row) => ({
            id: row.id,
            name: row.name,
            contactPerson: row.contact_person || "",
            phone: row.phone || "",
            email: row.email || "",
            address: row.address || "",
            status: row.is_active ? "启用" : "禁用",
            createdAt: row.created_at ? (typeof row.created_at === "string" ? row.created_at.slice(0, 10) : row.created_at) : "",
            code: row.supplier_code || "",
        });

        // 表单提交转 API 入参
        const formToApiPayload = () => ({
            supplier_code: formModel.code,
            name: formModel.name,
            contact_person: formModel.contactPerson,
            phone: formModel.phone,
            email: formModel.email,
            address: formModel.address,
            is_active: formModel.status === "启用",
        });

        // 对话框控制
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增供应商");
        const isEdit = ref(false);
        const isViewMode = ref(false);

        // 表单模型
        const formModel = reactive({
            id: null,
            name: "",
            contactPerson: "",
            phone: "",
            email: "",
            address: "",
            status: "启用",
            code: "",
        });

        // 表单规则
        const formRules = {
            code: [
                { required: true, message: "请输入供应商编码", trigger: "blur" },
            ],
            name: [
                {
                    required: true,
                    message: "请输入供应商名称",
                    trigger: "blur",
                },
                {
                    min: 2,
                    max: 50,
                    message: "长度在 2 到 50 个字符",
                    trigger: "blur",
                },
            ],
            contactPerson: [
                { required: true, message: "请输入联系人", trigger: "blur" },
            ],
            phone: [
                { required: true, message: "请输入联系电话", trigger: "blur" },
                {
                    pattern: /^1[3-9]\d{9}$/,
                    message: "请输入正确的手机号码",
                    trigger: "blur",
                },
            ],
            email: [
                { required: true, message: "请输入邮箱", trigger: "blur" },
                {
                    type: "email",
                    message: "请输入正确的邮箱地址",
                    trigger: "blur",
                },
            ],
            address: [
                { required: true, message: "请输入地址", trigger: "blur" },
            ],
        };

        // 表单引用
        const formRef = ref(null);

        // 搜索处理
        const handleSearch = () => {
            pagination.currentPage = 1;
            loadSuppliers();
        };

        // 加载供应商数据
        const loadSuppliers = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("suppliers", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                    },
                });
                const payload = res.data;
                const list = payload.data && payload.data.data ? payload.data.data : (Array.isArray(payload.data) ? payload.data : []);
                const meta = payload.data && payload.data.meta ? payload.data.meta : {};
                supplierList.value = list.map(mapApiToDisplay);
                pagination.total = meta.total ?? list.length;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (err) {
                ElMessage.error(err.response?.data?.message || "加载供应商列表失败");
                supplierList.value = [];
            } finally {
                loading.value = false;
            }
        };

        // 打开新增对话框
        const openAddDialog = () => {
            resetForm();
            dialogTitle.value = "新增供应商";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        // 编辑供应商
        const editSupplier = (row) => {
            Object.assign(formModel, row);
            dialogTitle.value = "编辑供应商";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
        };

        // 查看详情
        const viewDetail = (row) => {
            Object.assign(formModel, row);
            dialogTitle.value = "供应商详情";
            isEdit.value = false;
            isViewMode.value = true;
            dialogVisible.value = true;
        };

        // 关闭查看详情对话框
        const closeViewDialog = () => {
            dialogVisible.value = false;
            isViewMode.value = false;
            resetForm();
        };

        // 删除供应商
        const deleteSupplier = async (row) => {
            try {
                await ElMessageBox.confirm(
                    `确定要删除供应商 "${row.name}" 吗？`,
                    "删除确认",
                    {
                        confirmButtonText: "确定",
                        cancelButtonText: "取消",
                        type: "warning",
                    }
                );
                await window.axios.delete(`suppliers/${row.id}`);
                ElMessage.success("删除成功");
                loadSuppliers();
            } catch (err) {
                if (err !== "cancel" && err?.message !== "cancel") {
                    ElMessage.error(err.response?.data?.message || "删除失败");
                }
            }
        };

        // 重置表单
        const resetForm = () => {
            formModel.id = null;
            formModel.name = "";
            formModel.contactPerson = "";
            formModel.phone = "";
            formModel.email = "";
            formModel.address = "";
            formModel.status = "启用";
            formModel.code = "";
        };

        // 取消对话框
        const cancelDialog = () => {
            dialogVisible.value = false;
            isViewMode.value = false;
            resetForm();
        };

        // 确认对话框
        const confirmDialog = async () => {
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            const payload = formToApiPayload();
            try {
                if (isEdit.value) {
                    await window.axios.put(`suppliers/${formModel.id}`, payload);
                    ElMessage.success("供应商更新成功");
                } else {
                    await window.axios.post("suppliers", payload);
                    ElMessage.success("供应商新增成功");
                }
                dialogVisible.value = false;
                resetForm();
                loadSuppliers();
            } catch (err) {
                const msg = err.response?.data?.message || (err.response?.data?.errors ? "请检查表单" : "提交失败");
                ElMessage.error(msg);
            }
        };

        // 分页大小改变
        const handleSizeChange = (val) => {
            pagination.pageSize = val;
            loadSuppliers();
        };

        // 当前页改变
        const handleCurrentChange = (val) => {
            pagination.currentPage = val;
            loadSuppliers();
        };

        // 初始化加载数据
        onMounted(() => {
            loadSuppliers();
        });

        return {
            Search,
            Plus,
            searchKeyword,
            loading,
            pagination,
            supplierList,
            dialogVisible,
            dialogTitle,
            isViewMode,
            formModel,
            formRules,
            formRef,
            handleSearch,
            openAddDialog,
            editSupplier,
            viewDetail,
            closeViewDialog,
            deleteSupplier,
            cancelDialog,
            confirmDialog,
            handleSizeChange,
            handleCurrentChange,
        };
    },
});
</script>

<style scoped>
.supplier-management-container {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.data-card {
    min-height: 500px;
}

.pagination-container {
    margin-top: 20px;
    text-align: right;
}

.dialog-footer {
    text-align: right;
}
</style>
