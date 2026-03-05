<template>
    <div class="user-management-container">
        <div class="page-header">
            <h3>用户管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="搜索用户名或姓名"
                    style="width: 300px; margin-right: 15px"
                    :prefix-icon="Search"
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-button type="primary" :icon="Plus" @click="openAddDialog"
                    >新增用户</el-button
                >
            </div>
        </div>

        <el-card class="data-card">
            <el-table
                :data="userList"
                v-loading="loading"
                style="width: 100%"
                row-key="id"
                border
            >
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="username" label="用户名" width="120" />
                <el-table-column prop="realName" label="真实姓名" width="120" />
                <el-table-column prop="email" label="邮箱" width="180" />
                <el-table-column prop="phone" label="电话" width="150" />
                <el-table-column prop="role" label="角色" width="120" />
                <el-table-column prop="department" label="部门" width="120" />
                <el-table-column prop="storeName" label="所属企业" width="150" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag
                            :type="row.status === '启用' ? 'success' : 'danger'"
                        >
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="lastLoginTime"
                    label="最后登录"
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
                            @click="editUser(row)"
                            >编辑</el-button
                        >
                        <el-button
                            size="small"
                            type="danger"
                            @click="deleteUser(row)"
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

        <!-- 新增/编辑用户对话框 -->
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
                        <el-form-item label="用户名" prop="username">
                            <el-input
                                v-model="formModel.username"
                                placeholder="请输入用户名"
                                :disabled="isView"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="真实姓名" prop="realName">
                            <el-input
                                v-model="formModel.realName"
                                placeholder="请输入真实姓名"
                                :disabled="isView"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col v-if="!isView" :span="12">
                        <el-form-item
                            label="密码"
                            :prop="isEdit ? '' : 'password'"
                        >
                            <el-input
                                v-model="formModel.password"
                                type="password"
                                show-password
                                :placeholder="
                                    isEdit
                                        ? '不修改则留空'
                                        : '8位以上，含大小写字母和数字'
                                "
                            />
                        </el-form-item>
                    </el-col>
                    <el-col v-if="!isView" :span="12">
                        <el-form-item
                            label="确认密码"
                            :prop="isEdit ? '' : 'confirmPassword'"
                        >
                            <el-input
                                v-model="formModel.confirmPassword"
                                type="password"
                                show-password
                                :placeholder="
                                    isEdit ? '不修改则留空' : '请再次输入密码'
                                "
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="邮箱" prop="email">
                            <el-input
                                v-model="formModel.email"
                                placeholder="请输入邮箱"
                                :disabled="isView"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="电话" prop="phone">
                            <el-input
                                v-model="formModel.phone"
                                placeholder="请输入电话"
                                :disabled="isView"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="角色" prop="roleId">
                            <el-select
                                v-model="formModel.roleId"
                                placeholder="请选择角色"
                                style="width: 100%"
                                :disabled="isView"
                            >
                                <el-option
                                    v-for="role in roleList"
                                    :key="role.id"
                                    :label="role.name"
                                    :value="role.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="部门" prop="departmentId">
                            <el-select
                                v-model="formModel.departmentId"
                                placeholder="请选择部门"
                                style="width: 100%"
                                :disabled="isView"
                            >
                                <el-option
                                    v-for="dept in departmentList"
                                    :key="dept.id"
                                    :label="dept.name"
                                    :value="dept.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="状态" prop="status">
                            <el-select
                                v-model="formModel.status"
                                placeholder="请选择状态"
                                style="width: 100%"
                                :disabled="isView"
                            >
                                <el-option label="启用" value="启用" />
                                <el-option label="禁用" value="禁用" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="工号" prop="employeeId">
                            <el-input
                                v-model="formModel.employeeId"
                                placeholder="请输入工号"
                                :disabled="isView"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="cancelDialog">取消</el-button>
                    <el-button type="primary" @click="confirmDialog"
                        >{{ isView ? "关闭" : "确认" }}</el-button
                    >
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
    name: "UserManagement",
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

        // 用户列表
        const userList = ref([]);

        // 角色列表
        const roleList = ref([]);

        // 部门列表
        const departmentList = ref([]);

        // 对话框控制
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增用户");
        const isEdit = ref(false);
        const isView = ref(false);

        // 表单模型
        const formModel = reactive({
            id: null,
            username: "",
            realName: "",
            password: "",
            confirmPassword: "",
            email: "",
            phone: "",
            roleId: null,
            departmentId: null,
            status: "启用",
            employeeId: "",
        });

        // 基础表单验证规则（不含密码，用于编辑时）
        const baseFormRules = {
            username: [
                { required: true, message: "请输入用户名", trigger: "blur" },
                {
                    min: 3,
                    max: 20,
                    message: "长度在 3 到 20 个字符",
                    trigger: "blur",
                },
            ],
            realName: [
                { required: true, message: "请输入真实姓名", trigger: "blur" },
            ],
            email: [
                { required: true, message: "请输入邮箱", trigger: "blur" },
                {
                    type: "email",
                    message: "请输入正确的邮箱地址",
                    trigger: "blur",
                },
            ],
            phone: [
                { required: true, message: "请输入电话", trigger: "blur" },
                {
                    pattern: /^1[3-9]\d{9}$/,
                    message: "请输入正确的手机号码",
                    trigger: "blur",
                },
            ],
            roleId: [
                { required: true, message: "请选择角色", trigger: "change" },
            ],
            departmentId: [
                { required: true, message: "请选择部门", trigger: "change" },
            ],
            status: [
                { required: true, message: "请选择状态", trigger: "change" },
            ],
        };
        // 密码强度验证器（与后端 StrongPassword 规则保持一致）
        function validateStrongPassword(rule, value, callback) {
            if (!value) {
                callback(new Error("请输入密码"));
                return;
            }
            if (value.length < 8) {
                callback(new Error("密码长度不能少于 8 位"));
                return;
            }
            if (value.length > 100) {
                callback(new Error("密码长度不能超过 100 位"));
                return;
            }
            if (!/[A-Z]/.test(value)) {
                callback(new Error("密码必须包含至少一个大写字母"));
                return;
            }
            if (!/[a-z]/.test(value)) {
                callback(new Error("密码必须包含至少一个小写字母"));
                return;
            }
            if (!/[0-9]/.test(value)) {
                callback(new Error("密码必须包含至少一个数字"));
                return;
            }
            callback();
        }

        const passwordRules = {
            password: [
                { required: true, message: "请输入密码", trigger: "blur" },
                { validator: validateStrongPassword, trigger: "blur" },
            ],
            confirmPassword: [
                { required: true, message: "请再次输入密码", trigger: "blur" },
                { validator: validateConfirmPassword, trigger: "blur" },
            ],
        };
        // 当前使用的表单规则（ref 便于在新增/编辑时切换，避免编辑时误要求必填密码）
        const formRules = ref({ ...baseFormRules, ...passwordRules });

        // 表单引用
        const formRef = ref(null);

        // 验证确认密码
        function validateConfirmPassword(rule, value, callback) {
            if (value !== formModel.password) {
                callback(new Error("两次输入的密码不一致"));
            } else {
                callback();
            }
        }

        // 搜索处理
        const handleSearch = () => {
            pagination.currentPage = 1;
            loadUsers();
        };

        const formatDateTime = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const mapApiUserToRow = (u) => ({
            id: u.id,
            username: u.username || "",
            realName: u.real_name || u.name || "",
            email: u.email || "",
            phone: u.phone || "",
            role: u.role?.name || "",
            department: u.department?.name || "",
            storeName: u.store?.name || "",
            status: u.status === "disabled" ? "禁用" : "启用",
            lastLoginTime: formatDateTime(u.last_login_at) || "暂无登录记录",
            _raw: u,
        });

        const loadRoles = async () => {
            try {
                const res = await window.axios.get("roles", { params: { per_page: 500 } });
                const payload = res.data;
                const data = payload.data;
                const items = data && data.data ? data.data : Array.isArray(data) ? data : [];
                roleList.value = items || [];
            } catch (_) {
                roleList.value = [];
            }
        };

        const loadDepartments = async () => {
            try {
                const res = await window.axios.get("departments", { params: { per_page: 500 } });
                const payload = res.data;
                const data = payload.data;
                const items = data && data.data ? data.data : Array.isArray(data) ? data : [];
                departmentList.value = items || [];
            } catch (_) {
                departmentList.value = [];
            }
        };

        // 加载用户数据
        const loadUsers = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("users", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                    },
                });
                const payload = res.data;
                const data = payload.data;
                const items = data && data.data ? data.data : Array.isArray(data) ? data : [];
                const meta = data && data.meta ? data.meta : {};
                userList.value = (items || []).map(mapApiUserToRow);
                pagination.total = meta.total ?? items.length;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                userList.value = [];
                ElMessage.error(e.response?.data?.message || "加载用户列表失败");
            } finally {
                loading.value = false;
            }
        };

        // 打开新增对话框
        const openAddDialog = () => {
            resetForm();
            formRules.value = { ...baseFormRules, ...passwordRules };
            dialogTitle.value = "新增用户";
            isEdit.value = false;
            isView.value = false;
            dialogVisible.value = true;
        };

        // 编辑用户
        const editUser = (row) => {
            formRules.value = { ...baseFormRules };
            isView.value = false;
            Object.assign(formModel, {
                id: row.id,
                username: row.username,
                realName: row.realName,
                password: "",
                confirmPassword: "",
                email: row.email,
                phone: row.phone,
                roleId: row._raw?.role?.id || null,
                departmentId: row._raw?.department?.id || null,
                status: row.status,
                employeeId: row._raw?.employee_code || "",
            });

            dialogTitle.value = "编辑用户";
            isEdit.value = true;
            dialogVisible.value = true;
        };

        // 查看详情
        const viewDetail = (row) => {
            formRules.value = { ...baseFormRules };
            isView.value = true;
            Object.assign(formModel, {
                id: row.id,
                username: row.username,
                realName: row.realName,
                password: "",
                confirmPassword: "",
                email: row.email,
                phone: row.phone,
                roleId: row._raw?.role?.id || null,
                departmentId: row._raw?.department?.id || null,
                status: row.status,
                employeeId: row._raw?.employee_code || "",
            });
            dialogTitle.value = "用户详情";
            isEdit.value = false;
            dialogVisible.value = true;
        };

        // 删除用户
        const deleteUser = async (row) => {
            try {
                await ElMessageBox.confirm(
                    `确定要删除用户 "${row.username}" 吗？`,
                    "删除确认",
                    {
                        confirmButtonText: "确定",
                        cancelButtonText: "取消",
                        type: "warning",
                    }
                );

                await window.axios.delete(`users/${row.id}`);
                ElMessage.success("删除成功");
                loadUsers();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        // 重置表单
        const resetForm = () => {
            Object.keys(formModel).forEach((key) => {
                if (key === "status") {
                    formModel[key] = "启用";
                } else {
                    formModel[key] = null;
                }
            });
            formModel.password = "";
            formModel.confirmPassword = "";
        };

        // 取消对话框
        const cancelDialog = () => {
            dialogVisible.value = false;
            resetForm();
        };

        // 确认对话框
        const confirmDialog = async () => {
            if (isView.value) {
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
                if (isEdit.value) {
                    const payload = {
                        username: formModel.username,
                        real_name: formModel.realName,
                        name: formModel.realName,
                        email: formModel.email,
                        phone: formModel.phone,
                        role_id: formModel.roleId,
                        department_id: formModel.departmentId,
                        status: formModel.status === "禁用" ? "disabled" : "enabled",
                        employee_code: formModel.employeeId || null,
                    };
                    if (formModel.password) {
                        payload.password = formModel.password;
                        if (formModel.password !== formModel.confirmPassword) {
                            ElMessage.error("两次输入的密码不一致");
                            return;
                        }
                    }
                    await window.axios.put(`users/${formModel.id}`, payload);
                    ElMessage.success("用户更新成功");
                } else {
                    await window.axios.post("users", {
                        username: formModel.username,
                        real_name: formModel.realName,
                        name: formModel.realName,
                        email: formModel.email,
                        phone: formModel.phone,
                        password: formModel.password,
                        role_id: formModel.roleId,
                        department_id: formModel.departmentId,
                        status: formModel.status === "禁用" ? "disabled" : "enabled",
                        employee_code: formModel.employeeId || null,
                    });
                    ElMessage.success("用户新增成功");
                }

                dialogVisible.value = false;
                resetForm();
                loadUsers();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        // 分页大小改变
        const handleSizeChange = (val) => {
            pagination.pageSize = val;
            loadUsers();
        };

        // 当前页改变
        const handleCurrentChange = (val) => {
            pagination.currentPage = val;
            loadUsers();
        };

        // 初始化加载数据
        onMounted(() => {
            loadRoles();
            loadDepartments();
            loadUsers();
        });

        return {
            Search,
            Plus,
            searchKeyword,
            loading,
            pagination,
            userList,
            roleList,
            departmentList,
            dialogVisible,
            dialogTitle,
            formModel,
            formRules,
            formRef,
            isEdit,
            isView,
            handleSearch,
            openAddDialog,
            editUser,
            viewDetail,
            deleteUser,
            cancelDialog,
            confirmDialog,
            handleSizeChange,
            handleCurrentChange,
        };
    },
});
</script>

<style scoped>
.user-management-container {
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
