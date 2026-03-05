<template>
    <div class="role-config-container">
        <div class="page-header">
            <h3>角色配置管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="搜索角色名称"
                    style="width: 240px; margin-right: 12px"
                    clearable
                />
                <el-button type="primary" :icon="Plus" @click="openAdd">新增角色</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="filteredList" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="角色名称" min-width="140" />
                <el-table-column prop="code" label="角色编码" width="120" />
                <el-table-column prop="description" label="描述" min-width="200" />
                <el-table-column label="操作" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            size="small"
                            @click="openPerms(row)"
                            :disabled="!canManagePerms"
                        >
                            权限设置
                        </el-button>
                        <el-button size="small" type="primary" @click="editRow(row)">编辑</el-button>
                        <el-button size="small" type="danger" @click="removeRow(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑角色' : '新增角色'" width="520px" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="90px">
                <el-form-item label="角色名称" prop="name">
                    <el-input v-model="form.name" placeholder="如：管理员、销售员" />
                </el-form-item>
                <el-form-item label="角色编码" prop="code">
                    <el-input v-model="form.code" placeholder="如：admin、sales" />
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

        <!-- 角色权限设置 -->
        <el-dialog v-model="permDialogVisible" title="角色权限设置" width="720px">
            <div class="perm-toolbar">
                <div class="perm-role">
                    当前角色：<b>{{ currentRole?.name || '-' }}</b>
                    <span class="perm-role-code">（{{ currentRole?.code || '' }}）</span>
                </div>
                <el-input
                    v-model="permSearch"
                    placeholder="搜索权限（名称/标识）"
                    style="width: 260px"
                    clearable
                />
            </div>

            <el-tree
                ref="permTreeRef"
                :data="permissionTree"
                node-key="id"
                show-checkbox
                default-expand-all
                :filter-node-method="filterPermNode"
                :props="{ label: 'label', children: 'children' }"
                style="max-height: 420px; overflow: auto; border: 1px solid var(--el-border-color); border-radius: 8px; padding: 10px;"
            />

            <template #footer>
                <el-button @click="permDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="permSaving" @click="savePerms">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, computed, defineComponent, onMounted, watch } from "vue";
import { Plus } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";

const LS_PERMS = "auth_permissions";

export default defineComponent({
    name: "RoleConfiguration",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const list = ref([]);
        const dialogVisible = ref(false);
        const isEdit = ref(false);
        const formRef = ref(null);
        const form = reactive({ id: null, name: "", code: "", description: "" });
        const rules = {
            name: [{ required: true, message: "请输入角色名称", trigger: "blur" }],
            code: [{ required: true, message: "请输入角色编码", trigger: "blur" }],
        };

        const canManagePerms = computed(() => {
            try {
                const raw = localStorage.getItem(LS_PERMS);
                const perms = raw ? JSON.parse(raw) : [];
                return Array.isArray(perms) && perms.includes("roles.update");
            } catch (_) {
                return false;
            }
        });

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
                const res = await window.axios.get("roles", { params: { per_page: 500 } });
                const payload = res.data;
                const data = payload.data;
                const items = data && data.data ? data.data : Array.isArray(data) ? data : [];
                list.value = items || [];
            } catch (e) {
                list.value = [];
                ElMessage.error(e.response?.data?.message || "加载角色列表失败");
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
                await ElMessageBox.confirm(`确定删除角色「${row.name}」吗？`, "删除确认", {
                    type: "warning",
                });
                await window.axios.delete(`roles/${row.id}`);
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
            try {
                if (isEdit.value) {
                    await window.axios.put(`roles/${form.id}`, {
                        name: form.name,
                        code: form.code,
                        description: form.description,
                        is_active: true,
                    });
                    ElMessage.success("修改成功");
                } else {
                    await window.axios.post("roles", {
                        name: form.name,
                        code: form.code,
                        description: form.description,
                        is_active: true,
                    });
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (isEdit.value ? "修改失败" : "新增失败"));
            }
        };

        // ---------- 权限设置 ----------
        const permDialogVisible = ref(false);
        const permSaving = ref(false);
        const currentRole = ref(null);
        const permissions = ref([]); // flat list
        const permissionTree = ref([]); // tree nodes
        const permTreeRef = ref(null);
        const permSearch = ref("");

        const loadPermissions = async () => {
            const res = await window.axios.get("permissions", { params: { per_page: 1000 } });
            const payload = res.data;
            const data = payload.data;
            const items = data && data.data ? data.data : Array.isArray(data) ? data : [];
            permissions.value = items || [];
            permissionTree.value = buildPermissionTree(permissions.value);
        };

        const buildPermissionTree = (items) => {
            const groups = new Map();
            for (const p of items || []) {
                const g = p.group || "other";
                if (!groups.has(g)) groups.set(g, []);
                groups.get(g).push(p);
            }
            const groupOrder = ["system", "purchase", "sales", "inventory", "finance", "reports", "other"];
            const sortedGroups = Array.from(groups.keys()).sort((a, b) => {
                const ia = groupOrder.indexOf(a);
                const ib = groupOrder.indexOf(b);
                return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib) || String(a).localeCompare(String(b));
            });

            return sortedGroups.map((g) => ({
                id: `group:${g}`,
                label: groupTitle(g),
                children: (groups.get(g) || []).map((p) => ({
                    id: p.id,
                    label: `${p.title}（${p.name}）`,
                    _perm: p,
                })),
            }));
        };

        const groupTitle = (g) => {
            const map = {
                system: "系统",
                purchase: "采购",
                sales: "销售",
                inventory: "库存",
                finance: "财务",
                reports: "报表",
                other: "其他",
            };
            return map[g] || g;
        };

        const filterPermNode = (value, data) => {
            if (!value) return true;
            const v = String(value).toLowerCase();
            const label = String(data.label || "").toLowerCase();
            return label.includes(v);
        };

        watch(permSearch, (v) => {
            if (permTreeRef.value) permTreeRef.value.filter(v);
        });

        const openPerms = async (role) => {
            if (!canManagePerms.value) {
                ElMessage.warning("你没有权限修改角色权限（需要 roles.update）");
                return;
            }
            currentRole.value = role;
            permSearch.value = "";
            permDialogVisible.value = true;
            try {
                // 先加载权限列表（只需一次）
                if (!permissions.value || permissions.value.length === 0) {
                    await loadPermissions();
                }
                const res = await window.axios.get(`roles/${role.id}/permissions`);
                const ids = res.data?.data?.permission_ids || [];
                const checked = Array.isArray(ids) ? ids : [];
                // 设置勾选
                if (permTreeRef.value) {
                    permTreeRef.value.setCheckedKeys(checked);
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "加载角色权限失败");
            }
        };

        const savePerms = async () => {
            const role = currentRole.value;
            if (!role) return;
            permSaving.value = true;
            try {
                const keys = permTreeRef.value ? permTreeRef.value.getCheckedKeys(false) : [];
                // 去掉 group:* 这种 key，只保留数字 ID
                const ids = (keys || [])
                    .filter((k) => typeof k === "number" || /^\d+$/.test(String(k)))
                    .map((k) => Number(k))
                    .filter((n) => Number.isFinite(n));

                await window.axios.put(`roles/${role.id}/permissions`, {
                    permission_ids: ids,
                });
                ElMessage.success("权限已保存");
                permDialogVisible.value = false;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "保存失败");
            } finally {
                permSaving.value = false;
            }
        };

        onMounted(() => {
            loadList();
        });

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
            loadList,
            openAdd,
            editRow,
            removeRow,
            submitForm,
            // perms
            canManagePerms,
            permDialogVisible,
            permSaving,
            currentRole,
            permissionTree,
            permTreeRef,
            permSearch,
            filterPermNode,
            openPerms,
            savePerms,
        };
    },
});
</script>

<style scoped>
.role-config-container {
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
.perm-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    gap: 12px;
}
.perm-role {
    font-size: 13px;
    color: var(--el-text-color-regular);
}
.perm-role-code {
    color: var(--el-text-color-secondary);
    margin-left: 6px;
}
</style>
