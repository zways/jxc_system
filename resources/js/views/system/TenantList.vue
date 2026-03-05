<template>
    <div class="page-container">
        <div class="page-header">
            <h2>企业管理</h2>
            <p class="page-desc">查看和管理所有已注册的企业（仅超级管理员可见）</p>
        </div>

        <!-- 搜索栏 -->
        <el-card shadow="never" class="filter-card">
            <el-row :gutter="16" align="middle">
                <el-col :span="8">
                    <el-input
                        v-model="search"
                        placeholder="搜索企业名称/编码/邮箱"
                        clearable
                        :prefix-icon="Search"
                        @keyup="(e) => e.key === 'Enter' && loadData()"
                        @clear="loadData"
                    />
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filterPlan" placeholder="套餐筛选" clearable style="width: 100%" @change="loadData">
                        <el-option label="免费版" value="free" />
                        <el-option label="基础版" value="basic" />
                        <el-option label="专业版" value="pro" />
                        <el-option label="企业版" value="enterprise" />
                    </el-select>
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filterActive" placeholder="状态筛选" clearable style="width: 100%" @change="loadData">
                        <el-option label="已激活" value="1" />
                        <el-option label="已停用" value="0" />
                    </el-select>
                </el-col>
                <el-col :span="4">
                    <el-button type="primary" :icon="Search" @click="loadData">查询</el-button>
                    <el-button @click="resetFilter">重置</el-button>
                </el-col>
            </el-row>
        </el-card>

        <!-- 数据表格 -->
        <el-card shadow="never" style="margin-top: 16px">
            <el-table :data="tableData" v-loading="loading" stripe border style="width: 100%">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="store_code" label="企业编码" width="150" />
                <el-table-column prop="name" label="企业名称" min-width="160" />
                <el-table-column prop="manager" label="负责人" width="100" />
                <el-table-column prop="contact_email" label="联系邮箱" min-width="180" />
                <el-table-column prop="phone" label="电话" width="130" />
                <el-table-column label="套餐" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag :type="planTagType(row.plan)" size="small">{{ planLabel(row.plan) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="用户数" width="80" align="center">
                    <template #default="{ row }">
                        {{ row.user_count || 0 }} / {{ row.max_users || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                            {{ row.is_active ? '激活' : '停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="到期时间" width="120">
                    <template #default="{ row }">
                        <span :class="{ 'text-danger': isExpired(row) }">
                            {{ row.expires_at ? String(row.expires_at).substring(0, 10) : '永久' }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column label="注册时间" width="120">
                    <template #default="{ row }">
                        {{ row.created_at ? String(row.created_at).substring(0, 10) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="showDetail(row)">详情</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div class="pagination-bar">
                <el-pagination
                    v-model:current-page="pagination.page"
                    v-model:page-size="pagination.perPage"
                    :total="pagination.total"
                    :page-sizes="[15, 30, 50]"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadData"
                    @current-change="loadData"
                />
            </div>
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="企业详情" width="560px" destroy-on-close>
            <el-descriptions :column="2" border v-if="detailRow">
                <el-descriptions-item label="企业编码">{{ detailRow.store_code }}</el-descriptions-item>
                <el-descriptions-item label="企业名称">{{ detailRow.name }}</el-descriptions-item>
                <el-descriptions-item label="负责人">{{ detailRow.manager || '-' }}</el-descriptions-item>
                <el-descriptions-item label="电话">{{ detailRow.phone || '-' }}</el-descriptions-item>
                <el-descriptions-item label="联系邮箱">{{ detailRow.contact_email || '-' }}</el-descriptions-item>
                <el-descriptions-item label="所属行业">{{ detailRow.industry || '-' }}</el-descriptions-item>
                <el-descriptions-item label="营业执照号">{{ detailRow.business_license || '-' }}</el-descriptions-item>
                <el-descriptions-item label="套餐">{{ planLabel(detailRow.plan) }}</el-descriptions-item>
                <el-descriptions-item label="最大用户数">{{ detailRow.max_users || '-' }}</el-descriptions-item>
                <el-descriptions-item label="当前用户数">{{ detailRow.user_count || 0 }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="detailRow.is_active ? 'success' : 'danger'" size="small">
                        {{ detailRow.is_active ? '激活' : '停用' }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="到期时间">{{ detailRow.expires_at ? String(detailRow.expires_at).substring(0, 10) : '永久' }}</el-descriptions-item>
                <el-descriptions-item label="企业地址" :span="2">{{ detailRow.address || '-' }}</el-descriptions-item>
                <el-descriptions-item label="注册时间" :span="2">{{ detailRow.created_at || '-' }}</el-descriptions-item>
            </el-descriptions>
            <template #footer>
                <el-button @click="detailVisible = false">关闭</el-button>
                <el-button type="primary" @click="openSubscriptionForm(detailRow)">编辑订阅（线下续费）</el-button>
            </template>
        </el-dialog>

        <!-- 编辑订阅弹窗：管理员手动为企业续费/改套餐 -->
        <el-dialog v-model="subscriptionFormVisible" title="编辑订阅（线下续费）" width="420px" destroy-on-close @close="subscriptionFormRef?.resetFields()">
            <el-form ref="subscriptionFormRef" :model="subscriptionForm" :rules="subscriptionFormRules" label-width="100px">
                <el-form-item label="套餐" prop="plan">
                    <el-select v-model="subscriptionForm.plan" placeholder="选择套餐" style="width: 100%">
                        <el-option label="免费版" value="free" />
                        <el-option label="基础版" value="basic" />
                        <el-option label="专业版" value="pro" />
                        <el-option label="企业版" value="enterprise" />
                    </el-select>
                </el-form-item>
                <el-form-item label="到期时间" prop="expires_at">
                    <el-date-picker
                        v-model="subscriptionForm.expires_at"
                        type="date"
                        value-format="YYYY-MM-DD"
                        placeholder="不填表示永久"
                        style="width: 100%"
                        clearable
                    />
                    <div class="form-tip">不填表示永久有效；线下收款后在此延长到期时间即可。</div>
                </el-form-item>
                <el-form-item label="最大用户数" prop="max_users">
                    <el-input-number v-model="subscriptionForm.max_users" :min="1" :max="9999" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="subscriptionFormVisible = false">取消</el-button>
                <el-button type="primary" :loading="subscriptionSubmitting" @click="submitSubscriptionForm">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { defineComponent, ref, reactive, onMounted } from "vue";
import { ElMessage } from "element-plus";
import { Search } from "@element-plus/icons-vue";

const PLAN_LABELS = { free: "免费版", basic: "基础版", pro: "专业版", enterprise: "企业版" };
const PLAN_TAG_TYPES = { free: "info", basic: "success", pro: "warning", enterprise: "" };

export default defineComponent({
    name: "TenantList",
    setup() {
        const loading = ref(false);
        const search = ref("");
        const filterPlan = ref("");
        const filterActive = ref("");
        const tableData = ref([]);
        const pagination = reactive({ page: 1, perPage: 15, total: 0 });
        const detailVisible = ref(false);
        const detailRow = ref(null);

        const subscriptionFormVisible = ref(false);
        const subscriptionFormRef = ref(null);
        const subscriptionSubmitting = ref(false);
        const subscriptionTargetId = ref(null);
        const subscriptionForm = reactive({
            plan: "free",
            expires_at: null,
            max_users: 5,
        });
        const subscriptionFormRules = {
            plan: [{ required: true, message: "请选择套餐", trigger: "change" }],
            max_users: [{ required: true, message: "请填写最大用户数", trigger: "blur" }],
        };

        const planLabel = (plan) => PLAN_LABELS[plan] || plan || "免费版";
        const planTagType = (plan) => PLAN_TAG_TYPES[plan] || "";
        const isExpired = (row) => {
            if (!row.expires_at) return false;
            return new Date(row.expires_at) < new Date();
        };

        const loadData = async () => {
            loading.value = true;
            try {
                const params = {
                    page: pagination.page,
                    per_page: pagination.perPage,
                };
                if (search.value) params.search = search.value;
                if (filterPlan.value) params.plan = filterPlan.value;
                if (filterActive.value !== "") params.is_active = filterActive.value;

                const res = await window.axios.get("tenant/list", { params });
                const data = res.data?.data || {};
                tableData.value = data.data || [];
                pagination.total = data.total || 0;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "获取企业列表失败");
            } finally {
                loading.value = false;
            }
        };

        const resetFilter = () => {
            search.value = "";
            filterPlan.value = "";
            filterActive.value = "";
            pagination.page = 1;
            loadData();
        };

        const showDetail = (row) => {
            detailRow.value = row;
            detailVisible.value = true;
        };

        const openSubscriptionForm = (row) => {
            if (!row || !row.id) return;
            subscriptionTargetId.value = row.id;
            subscriptionForm.plan = row.plan || "free";
            subscriptionForm.expires_at = row.expires_at ? String(row.expires_at).substring(0, 10) : null;
            subscriptionForm.max_users = row.max_users ?? 5;
            subscriptionFormVisible.value = true;
        };

        const submitSubscriptionForm = async () => {
            try {
                await subscriptionFormRef.value?.validate();
            } catch {
                return;
            }
            subscriptionSubmitting.value = true;
            try {
                await window.axios.put(`tenant/${subscriptionTargetId.value}/subscription`, {
                    plan: subscriptionForm.plan,
                    expires_at: subscriptionForm.expires_at || null,
                    max_users: subscriptionForm.max_users,
                });
                ElMessage.success("订阅已更新");
                subscriptionFormVisible.value = false;
                loadData();
                if (detailRow.value?.id === subscriptionTargetId.value) {
                    detailRow.value = { ...detailRow.value, ...subscriptionForm, expires_at: subscriptionForm.expires_at };
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "更新失败");
            } finally {
                subscriptionSubmitting.value = false;
            }
        };

        onMounted(() => loadData());

        return {
            Search,
            loading,
            search,
            filterPlan,
            filterActive,
            tableData,
            pagination,
            detailVisible,
            detailRow,
            planLabel,
            planTagType,
            isExpired,
            loadData,
            resetFilter,
            showDetail,
            subscriptionFormVisible,
            subscriptionFormRef,
            subscriptionForm,
            subscriptionFormRules,
            subscriptionSubmitting,
            openSubscriptionForm,
            submitSubscriptionForm,
        };
    },
});
</script>

<style scoped>
.page-container {
    max-width: 1400px;
}
.page-header {
    margin-bottom: 16px;
}
.page-header h2 {
    margin: 0 0 4px 0;
    font-size: 20px;
    font-weight: 600;
    color: #1f2937;
}
.page-desc {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
}
.filter-card {
    margin-bottom: 0;
}
.pagination-bar {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
.text-danger {
    color: #ef4444;
}
.form-tip {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}
</style>
