<template>
    <div class="page-container">
        <div class="page-header">
            <h2>{{ isPlatform ? '平台概览' : '企业信息' }}</h2>
            <p class="page-desc">{{ isPlatform ? '查看所有企业的汇总统计与运营概况' : '查看和管理您的企业基本信息与套餐详情' }}</p>
        </div>

        <!-- ====== 超管平台概览 ====== -->
        <template v-if="isPlatform">
            <el-row :gutter="20" class="platform-stats">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-icon" style="background: #e0f2fe; color: #0284c7">
                            <el-icon :size="28"><OfficeBuilding /></el-icon>
                        </div>
                        <div class="stat-body">
                            <div class="stat-number">{{ tenant.stats?.total_tenants || 0 }}</div>
                            <div class="stat-title">企业总数</div>
                        </div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-icon" style="background: #dcfce7; color: #16a34a">
                            <el-icon :size="28"><CircleCheck /></el-icon>
                        </div>
                        <div class="stat-body">
                            <div class="stat-number">{{ tenant.stats?.active_tenants || 0 }}</div>
                            <div class="stat-title">活跃企业</div>
                        </div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-icon" style="background: #fef3c7; color: #d97706">
                            <el-icon :size="28"><Warning /></el-icon>
                        </div>
                        <div class="stat-body">
                            <div class="stat-number">{{ tenant.stats?.expired_tenants || 0 }}</div>
                            <div class="stat-title">已过期</div>
                        </div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-icon" style="background: #ede9fe; color: #7c3aed">
                            <el-icon :size="28"><User /></el-icon>
                        </div>
                        <div class="stat-body">
                            <div class="stat-number">{{ tenant.stats?.total_users || 0 }}</div>
                            <div class="stat-title">总用户数</div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <el-row :gutter="20" style="margin-top: 20px">
                <el-col :span="10">
                    <el-card shadow="never">
                        <template #header><span style="font-weight: 600">套餐分布</span></template>
                        <div class="plan-distribution">
                            <div v-for="(count, plan) in (tenant.plan_distribution || {})" :key="plan" class="plan-dist-item">
                                <span class="plan-dist-label">
                                    <el-tag :type="planTagType(plan)" size="small">{{ planNameMap[plan] || plan }}</el-tag>
                                </span>
                                <el-progress
                                    :percentage="Math.round(count / (tenant.stats?.total_tenants || 1) * 100)"
                                    :stroke-width="16"
                                    :color="planColor(plan)"
                                    style="flex: 1; margin: 0 12px"
                                />
                                <span class="plan-dist-count">{{ count }} 家</span>
                            </div>
                        </div>
                    </el-card>
                </el-col>
                <el-col :span="14">
                    <el-card shadow="never">
                        <template #header><span style="font-weight: 600">最近注册的企业</span></template>
                        <el-table :data="tenant.recent_tenants || []" size="small" stripe>
                            <el-table-column prop="store_code" label="编码" width="140" />
                            <el-table-column prop="name" label="企业名称" min-width="140" />
                            <el-table-column label="套餐" width="90" align="center">
                                <template #default="{ row }">
                                    <el-tag :type="planTagType(row.plan)" size="small">{{ planNameMap[row.plan] || row.plan }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="状态" width="80" align="center">
                                <template #default="{ row }">
                                    <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '激活' : '停用' }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="注册时间" width="110">
                                <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>
            </el-row>
        </template>

        <!-- ====== 普通用户企业信息 ====== -->
        <template v-else>
        <el-row :gutter="20">
            <!-- 左侧：企业信息卡片 -->
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>基本信息</span>
                            <el-button
                                v-if="canEdit"
                                type="primary"
                                size="small"
                                :icon="isEditing ? '' : Edit"
                                @click="isEditing ? saveInfo() : (isEditing = true)"
                                :loading="saving"
                            >
                                {{ isEditing ? '保存' : '编辑' }}
                            </el-button>
                        </div>
                    </template>

                    <div v-loading="loading">
                        <template v-if="!isEditing">
                            <el-descriptions :column="2" border>
                                <el-descriptions-item label="企业名称">{{ tenant.name || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="企业编码">{{ tenant.store_code || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="负责人">{{ tenant.manager || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="联系电话">{{ tenant.phone || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="联系邮箱">{{ tenant.contact_email || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="所属行业">{{ tenant.industry || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="营业执照号">{{ tenant.business_license || '-' }}</el-descriptions-item>
                                <el-descriptions-item label="注册时间">{{ formatDate(tenant.created_at) }}</el-descriptions-item>
                                <el-descriptions-item label="企业地址" :span="2">{{ tenant.address || '-' }}</el-descriptions-item>
                            </el-descriptions>
                        </template>

                        <template v-else>
                            <el-form
                                ref="formRef"
                                :model="editForm"
                                :rules="editRules"
                                label-width="100px"
                                label-position="right"
                            >
                                <el-row :gutter="16">
                                    <el-col :span="12">
                                        <el-form-item label="企业名称" prop="name">
                                            <el-input v-model="editForm.name" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item label="负责人" prop="manager">
                                            <el-input v-model="editForm.manager" />
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-row :gutter="16">
                                    <el-col :span="12">
                                        <el-form-item label="联系电话" prop="phone">
                                            <el-input v-model="editForm.phone" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item label="联系邮箱" prop="contact_email">
                                            <el-input v-model="editForm.contact_email" />
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-row :gutter="16">
                                    <el-col :span="12">
                                        <el-form-item label="所属行业" prop="industry">
                                            <el-select v-model="editForm.industry" placeholder="请选择" clearable style="width: 100%">
                                                <el-option label="零售" value="零售" />
                                                <el-option label="批发" value="批发" />
                                                <el-option label="制造" value="制造" />
                                                <el-option label="食品饮料" value="食品饮料" />
                                                <el-option label="电子科技" value="电子科技" />
                                                <el-option label="服装纺织" value="服装纺织" />
                                                <el-option label="建材家居" value="建材家居" />
                                                <el-option label="医药保健" value="医药保健" />
                                                <el-option label="汽配" value="汽配" />
                                                <el-option label="其他" value="其他" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item label="营业执照号" prop="business_license">
                                            <el-input v-model="editForm.business_license" />
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-form-item label="企业地址" prop="address">
                                    <el-input v-model="editForm.address" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button @click="cancelEdit">取消</el-button>
                                </el-form-item>
                            </el-form>
                        </template>
                    </div>
                </el-card>
            </el-col>

            <!-- 右侧：套餐信息 -->
            <el-col :span="8">
                <el-card shadow="never" class="plan-card">
                    <template #header>
                        <span>套餐信息</span>
                    </template>
                    <div v-loading="loading" class="plan-content">
                        <div class="plan-badge" :class="'plan-' + (tenant.plan || 'free')">
                            {{ planLabel }}
                        </div>
                        <div class="plan-stats">
                            <div class="stat-item">
                                <div class="stat-label">用户数</div>
                                <div class="stat-value">
                                    <span class="stat-current">{{ tenant.user_count || 0 }}</span>
                                    <span class="stat-sep">/</span>
                                    <span class="stat-max">{{ tenant.max_users || '-' }}</span>
                                </div>
                            </div>
                            <el-progress
                                :percentage="userPercentage"
                                :color="userPercentage > 80 ? '#f56c6c' : '#409eff'"
                                :stroke-width="8"
                                style="margin-top: 8px"
                            />
                        </div>
                        <div class="plan-info-list">
                            <div class="plan-info-item">
                                <span class="info-label">到期时间</span>
                                <span class="info-value" :class="{ 'text-danger': tenant.is_expired }">
                                    {{ tenant.expires_at ? formatDate(tenant.expires_at) : '永久有效' }}
                                    <el-tag v-if="tenant.is_expired" type="danger" size="small" style="margin-left: 4px">已过期</el-tag>
                                </span>
                            </div>
                            <div class="plan-info-item">
                                <span class="info-label">企业编码</span>
                                <span class="info-value">{{ tenant.store_code || '-' }}</span>
                            </div>
                        </div>
                        <!-- 在线续费/升级入口（仅当平台开启支付时显示） -->
                        <div v-if="paymentConfig.enabled" class="plan-upgrade-block">
                            <el-button type="primary" plain style="width: 100%; margin-top: 12px" @click="openPayDialog">
                                续费 / 升级套餐
                            </el-button>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 续费/升级套餐弹窗 -->
        <el-dialog
            v-model="payDialogVisible"
            title="续费 / 升级套餐"
            width="440px"
            :close-on-click-modal="!paymentSubmitting"
            destroy-on-close
            @closed="resetPayDialog"
        >
            <template v-if="!showQrCode">
                <el-form label-width="90px" :model="payForm">
                    <el-form-item label="选择套餐">
                        <el-select v-model="payForm.plan" placeholder="请选择" style="width: 100%">
                            <el-option
                                v-for="(cfg, key) in payPlanOptions"
                                :key="key"
                                :label="cfg.label"
                                :value="key"
                            />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="订阅周期">
                        <el-select v-model="payForm.period" placeholder="请选择" style="width: 100%">
                            <el-option
                                v-for="(cfg, key) in payPeriodOptions"
                                :key="key"
                                :label="cfg.label"
                                :value="key"
                            />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="应付金额">
                        <span class="pay-amount">¥{{ payAmount }}</span>
                    </el-form-item>
                </el-form>
            </template>
            <div v-else class="qr-pay-block">
                <p class="qr-tip">请使用微信扫码完成支付</p>
                <div class="qr-wrap">
                    <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="支付二维码" class="qr-img" />
                </div>
                <p class="qr-tip-sub">支付完成后将自动更新套餐，请稍后刷新页面查看</p>
            </div>
            <template #footer>
                <template v-if="!showQrCode">
                    <el-button @click="payDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="paymentSubmitting" @click="submitPayOrder">
                        去支付
                    </el-button>
                </template>
                <template v-else>
                    <el-button type="primary" @click="closeQrAndRefresh">已完成支付，刷新</el-button>
                </template>
            </template>
        </el-dialog>

        <!-- 支付宝：隐藏表单容器，提交后由后端返回的 form 跳转 -->
        <div v-if="alipayFormHtml" v-html="alipayFormHtml" ref="alipayFormRef" class="alipay-form-container"></div>
        </template>
    </div>
</template>

<script>
import { defineComponent, ref, reactive, computed, onMounted, nextTick } from "vue";
import { ElMessage } from "element-plus";
import { Edit, OfficeBuilding, CircleCheck, Warning, User } from "@element-plus/icons-vue";
import QRCode from "qrcode";

const LS_USER = "auth_user";

const PLAN_LABELS = {
    free: "免费版",
    basic: "基础版",
    pro: "专业版",
    enterprise: "企业版",
};

const PLAN_TAG_TYPES = { free: "info", basic: "success", pro: "warning", enterprise: "" };
const PLAN_COLORS = { free: "#94a3b8", basic: "#16a34a", pro: "#d97706", enterprise: "#7c3aed" };

const PERIOD_LABELS = {
    "1_month": "1 个月",
    "3_months": "3 个月",
    "1_year": "1 年",
};

export default defineComponent({
    name: "TenantProfile",
    components: { OfficeBuilding, CircleCheck, Warning, User },
    setup() {
        const loading = ref(false);
        const saving = ref(false);
        const isEditing = ref(false);
        const formRef = ref(null);
        const tenant = ref({});
        const alipayFormRef = ref(null);

        // 支付配置（是否开启、套餐与周期）
        const paymentConfig = ref({
            enabled: false,
            provider: null,
            plans: {},
            periods: {},
        });
        const payDialogVisible = ref(false);
        const paymentSubmitting = ref(false);
        const payForm = reactive({ plan: "basic", period: "1_month" });
        const showQrCode = ref(false);
        const qrCodeDataUrl = ref("");
        const alipayFormHtml = ref("");

        // 是否为平台概览模式（超管）
        const isPlatform = computed(() => Boolean(tenant.value.is_platform));

        const planNameMap = PLAN_LABELS;
        const planTagType = (plan) => PLAN_TAG_TYPES[plan] || "info";
        const planColor = (plan) => PLAN_COLORS[plan] || "#409eff";

        const editForm = reactive({
            name: "",
            manager: "",
            phone: "",
            contact_email: "",
            industry: "",
            business_license: "",
            address: "",
        });

        const editRules = {
            name: [{ required: true, message: "请输入企业名称", trigger: "blur" }],
        };

        const canEdit = computed(() => {
            if (isPlatform.value) return false; // 平台概览不可编辑
            try {
                const raw = localStorage.getItem(LS_USER);
                const u = raw ? JSON.parse(raw) : null;
                const roleCode = u?.role?.code || u?.role_code || "";
                return ["super_admin", "tenant_admin"].includes(roleCode);
            } catch (_) {
                return false;
            }
        });

        const planLabel = computed(() => PLAN_LABELS[tenant.value.plan] || "免费版");
        const userPercentage = computed(() => {
            const max = tenant.value.max_users || 1;
            const current = tenant.value.user_count || 0;
            return Math.min(Math.round((current / max) * 100), 100);
        });

        // 支付：可选的套餐（排除免费版）
        const payPlanOptions = computed(() => {
            const plans = paymentConfig.value.plans || {};
            return Object.fromEntries(
                Object.entries(plans).filter(([k]) => k !== "free").map(([k, v]) => [k, { label: `${v.name} ¥${v.price}/月` }])
            );
        });
        const payPeriodOptions = computed(() => {
            const periods = paymentConfig.value.periods || {};
            return Object.fromEntries(
                Object.entries(periods).map(([k, v]) => [k, { label: PERIOD_LABELS[k] || k, months: v.months || 1, discount: v.discount ?? 1 }])
            );
        });
        const payAmount = computed(() => {
            const plans = paymentConfig.value.plans || {};
            const periods = paymentConfig.value.periods || {};
            const plan = plans[payForm.plan];
            const period = periods[payForm.period];
            if (!plan || !period) return "0.00";
            const price = Number(plan.price) || 0;
            const months = Number(period.months) || 1;
            const discount = Number(period.discount) ?? 1;
            return (price * months * discount).toFixed(2);
        });

        const loadPaymentConfig = async () => {
            try {
                const res = await window.axios.get("payment/config");
                const data = res.data?.data || {};
                paymentConfig.value = {
                    enabled: Boolean(data.enabled),
                    provider: data.provider || null,
                    plans: data.plans || {},
                    periods: data.periods || {},
                };
            } catch (_) {
                paymentConfig.value = { enabled: false, provider: null, plans: {}, periods: {} };
            }
        };

        const openPayDialog = () => {
            payForm.plan = "basic";
            payForm.period = "1_month";
            showQrCode.value = false;
            qrCodeDataUrl.value = "";
            payDialogVisible.value = true;
        };

        const resetPayDialog = () => {
            showQrCode.value = false;
            qrCodeDataUrl.value = "";
            paymentSubmitting.value = false;
        };

        const submitPayOrder = async () => {
            const storeId = tenant.value.id;
            if (!storeId) {
                ElMessage.warning("无法获取当前企业信息");
                return;
            }
            paymentSubmitting.value = true;
            try {
                const returnUrl = window.location.origin + window.location.pathname + "#/system/tenant-profile";
                const res = await window.axios.post("payment/create-order", {
                    store_id: storeId,
                    plan: payForm.plan,
                    period: payForm.period,
                    return_url: returnUrl,
                });
                const data = res.data?.data || {};
                if (res.data?.success && !data.need_offline) {
                    if (data.pay_form_html) {
                        payDialogVisible.value = false;
                        alipayFormHtml.value = data.pay_form_html;
                        await nextTick();
                        const form = alipayFormRef.value?.querySelector("form");
                        if (form) form.submit();
                        setTimeout(() => { alipayFormHtml.value = ""; }, 500);
                        ElMessage.success("已跳转支付宝，支付完成后将自动更新套餐");
                    } else if (data.pay_url) {
                        qrCodeDataUrl.value = await QRCode.toDataURL(data.pay_url, { width: 220, margin: 1 });
                        showQrCode.value = true;
                    } else {
                        ElMessage.warning(data.message || "未返回支付信息");
                    }
                } else {
                    ElMessage.warning(data.message || res.data?.message || "创建订单失败");
                }
            } catch (e) {
                const msg = e.response?.data?.message || e.response?.data?.data?.message || "创建订单失败";
                ElMessage.error(msg);
            } finally {
                paymentSubmitting.value = false;
            }
        };

        const closeQrAndRefresh = () => {
            payDialogVisible.value = false;
            loadTenant();
            ElMessage.success("已刷新，请查看套餐信息");
        };

        const formatDate = (val) => {
            if (!val) return "-";
            return String(val).substring(0, 10);
        };

        const loadTenant = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("tenant/current");
                tenant.value = res.data?.data || {};
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "获取企业信息失败");
            } finally {
                loading.value = false;
            }
        };

        const fillEditForm = () => {
            const t = tenant.value;
            editForm.name = t.name || "";
            editForm.manager = t.manager || "";
            editForm.phone = t.phone || "";
            editForm.contact_email = t.contact_email || "";
            editForm.industry = t.industry || "";
            editForm.business_license = t.business_license || "";
            editForm.address = t.address || "";
        };

        const cancelEdit = () => {
            isEditing.value = false;
            fillEditForm();
        };

        const saveInfo = async () => {
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            saving.value = true;
            try {
                await window.axios.put("tenant/current", { ...editForm });
                ElMessage.success("企业信息已更新");
                isEditing.value = false;
                await loadTenant();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "更新失败");
            } finally {
                saving.value = false;
            }
        };

        onMounted(async () => {
            await loadTenant();
            if (!isPlatform.value) {
                fillEditForm();
                await loadPaymentConfig();
            }
        });

        return {
            Edit,
            OfficeBuilding,
            CircleCheck,
            Warning,
            User,
            loading,
            saving,
            isEditing,
            formRef,
            tenant,
            editForm,
            editRules,
            canEdit,
            isPlatform,
            planLabel,
            planNameMap,
            planTagType,
            planColor,
            userPercentage,
            formatDate,
            cancelEdit,
            saveInfo,
            paymentConfig,
            payDialogVisible,
            paymentSubmitting,
            payForm,
            payPlanOptions,
            payPeriodOptions,
            payAmount,
            showQrCode,
            qrCodeDataUrl,
            alipayFormHtml,
            alipayFormRef,
            openPayDialog,
            submitPayOrder,
            closeQrAndRefresh,
        };
    },
});
</script>

<style scoped>
.page-container {
    max-width: 1200px;
}
.page-header {
    margin-bottom: 20px;
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
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
}

/* 平台概览统计卡片 */
.stat-card {
    display: flex;
    align-items: center;
    padding: 0;
}
.stat-card :deep(.el-card__body) {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    width: 100%;
}
.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-body {
    flex: 1;
}
.stat-number {
    font-size: 26px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.2;
}
.stat-title {
    font-size: 13px;
    color: #6b7280;
    margin-top: 2px;
}

/* 套餐分布 */
.plan-distribution {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.plan-dist-item {
    display: flex;
    align-items: center;
}
.plan-dist-label {
    width: 70px;
    flex-shrink: 0;
}
.plan-dist-count {
    width: 50px;
    text-align: right;
    font-size: 13px;
    color: #374151;
    font-weight: 500;
    flex-shrink: 0;
}

/* Plan card */
.plan-content {
    text-align: center;
}
.plan-badge {
    display: inline-block;
    padding: 8px 28px;
    border-radius: 20px;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 20px;
}
.plan-free {
    background: #f0f9ff;
    color: #0284c7;
}
.plan-basic {
    background: #f0fdf4;
    color: #16a34a;
}
.plan-pro {
    background: #fefce8;
    color: #ca8a04;
}
.plan-enterprise {
    background: #faf5ff;
    color: #9333ea;
}
.plan-stats {
    margin-bottom: 20px;
    text-align: left;
}
.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}
.stat-label {
    font-size: 13px;
    color: #6b7280;
}
.stat-current {
    font-size: 22px;
    font-weight: 700;
    color: #1f2937;
}
.stat-sep {
    margin: 0 2px;
    color: #d1d5db;
}
.stat-max {
    font-size: 14px;
    color: #9ca3af;
}
.plan-info-list {
    text-align: left;
    border-top: 1px solid #f3f4f6;
    padding-top: 16px;
}
.plan-info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 13px;
}
.info-label {
    color: #6b7280;
}
.info-value {
    color: #374151;
    font-weight: 500;
}
.text-danger {
    color: #ef4444;
}

.plan-upgrade-block {
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid #f3f4f6;
}

.pay-amount {
    font-size: 20px;
    font-weight: 700;
    color: #16a34a;
}

.qr-pay-block {
    text-align: center;
    padding: 16px 0;
}
.qr-tip {
    margin: 0 0 16px 0;
    font-size: 15px;
    color: #374151;
}
.qr-wrap {
    display: inline-block;
    padding: 12px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
.qr-img {
    display: block;
    width: 220px;
    height: 220px;
}
.qr-tip-sub {
    margin: 16px 0 0 0;
    font-size: 13px;
    color: #6b7280;
}

.alipay-form-container {
    position: absolute;
    left: -9999px;
    top: 0;
    opacity: 0;
    pointer-events: none;
}
</style>
