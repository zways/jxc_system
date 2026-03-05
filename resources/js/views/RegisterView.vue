<template>
    <div class="register-page">
        <div class="register-card">
            <div class="brand">
                <div class="brand-title">进销存管理系统</div>
                <div class="brand-subtitle">企业注册 — 开启高效进销存管理</div>
            </div>

            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-position="top"
                @submit.prevent
            >
                <!-- 企业信息 -->
                <div class="section-title">企业信息</div>

                <el-form-item label="企业名称" prop="company_name">
                    <el-input
                        v-model="form.company_name"
                        placeholder="请输入企业/门店名称"
                        size="large"
                    />
                </el-form-item>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="所属行业" prop="industry">
                            <el-select
                                v-model="form.industry"
                                placeholder="请选择行业"
                                size="large"
                                style="width: 100%"
                                clearable
                            >
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
                            <el-input
                                v-model="form.business_license"
                                placeholder="选填"
                                size="large"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="企业地址" prop="address">
                    <el-input
                        v-model="form.address"
                        placeholder="选填"
                        size="large"
                    />
                </el-form-item>

                <!-- 管理员信息 -->
                <div class="section-title" style="margin-top: 8px">管理员账号</div>

                <el-form-item label="管理员姓名" prop="admin_name">
                    <el-input
                        v-model="form.admin_name"
                        placeholder="请输入管理员真实姓名"
                        size="large"
                    />
                </el-form-item>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="邮箱" prop="admin_email">
                            <el-input
                                v-model="form.admin_email"
                                placeholder="用于登录系统"
                                size="large"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="手机号" prop="admin_phone">
                            <el-input
                                v-model="form.admin_phone"
                                placeholder="选填"
                                size="large"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="登录密码" prop="admin_password">
                            <el-input
                                v-model="form.admin_password"
                                type="password"
                                show-password
                                placeholder="8位以上，含大小写字母和数字"
                                size="large"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="确认密码" prop="confirm_password">
                            <el-input
                                v-model="form.confirm_password"
                                type="password"
                                show-password
                                placeholder="再次输入密码"
                                size="large"
                                @keyup="(e) => e.key === 'Enter' && submit()"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-button
                    type="primary"
                    size="large"
                    style="width: 100%"
                    :loading="loading"
                    @click="submit"
                >
                    立即注册
                </el-button>

                <div class="hint">
                    已有账号？<router-link to="/login" class="link">返回登录</router-link>
                </div>
            </el-form>
        </div>
    </div>
</template>

<script>
import { defineComponent, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { ElMessage } from "element-plus";

const LS_TOKEN = "auth_token";
const LS_USER = "auth_user";
const LS_PERMS = "auth_permissions";

export default defineComponent({
    name: "RegisterView",
    setup() {
        const router = useRouter();
        const loading = ref(false);
        const formRef = ref(null);
        const form = reactive({
            company_name: "",
            industry: "",
            business_license: "",
            address: "",
            admin_name: "",
            admin_email: "",
            admin_phone: "",
            admin_password: "",
            confirm_password: "",
        });

        const validateStrongPassword = (rule, value, callback) => {
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
        };

        const validateConfirmPassword = (rule, value, callback) => {
            if (value !== form.admin_password) {
                callback(new Error("两次输入的密码不一致"));
            } else {
                callback();
            }
        };

        const rules = {
            company_name: [
                { required: true, message: "请输入企业名称", trigger: "blur" },
                { max: 100, message: "最多100个字符", trigger: "blur" },
            ],
            admin_name: [
                { required: true, message: "请输入管理员姓名", trigger: "blur" },
                { max: 50, message: "最多50个字符", trigger: "blur" },
            ],
            admin_email: [
                { required: true, message: "请输入邮箱", trigger: "blur" },
                { type: "email", message: "请输入有效的邮箱地址", trigger: "blur" },
            ],
            admin_password: [
                { required: true, message: "请输入密码", trigger: "blur" },
                { validator: validateStrongPassword, trigger: "blur" },
            ],
            confirm_password: [
                { required: true, message: "请确认密码", trigger: "blur" },
                { validator: validateConfirmPassword, trigger: "blur" },
            ],
        };

        const submit = async () => {
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            loading.value = true;
            try {
                const res = await window.axios.post("tenant/register", {
                    company_name: form.company_name,
                    industry: form.industry || undefined,
                    business_license: form.business_license || undefined,
                    address: form.address || undefined,
                    admin_name: form.admin_name,
                    admin_email: form.admin_email,
                    admin_phone: form.admin_phone || undefined,
                    admin_password: form.admin_password,
                    device_name: "web",
                });
                const payload = res.data?.data || {};

                // 注册成功，自动登录
                localStorage.setItem(LS_TOKEN, payload.token || "");
                localStorage.setItem(LS_USER, JSON.stringify(payload.user || {}));
                localStorage.setItem(LS_PERMS, JSON.stringify(payload.permissions || []));

                ElMessage.success("注册成功，欢迎使用！");
                router.replace("/dashboard");
            } catch (e) {
                const errData = e.response?.data;
                if (errData?.errors) {
                    // 取第一个验证错误
                    const firstErr = Object.values(errData.errors)[0];
                    ElMessage.error(Array.isArray(firstErr) ? firstErr[0] : firstErr);
                } else {
                    ElMessage.error(errData?.message || "注册失败，请稍后重试");
                }
            } finally {
                loading.value = false;
            }
        };

        return { loading, formRef, form, rules, submit };
    },
});
</script>

<style scoped>
.register-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(1200px 600px at 30% 20%, #e0f2fe, transparent),
        radial-gradient(900px 500px at 80% 70%, #ede9fe, transparent),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
    padding: 24px;
}
.register-card {
    width: 560px;
    max-width: 96vw;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(15, 23, 42, 0.06);
    border-radius: 14px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
    padding: 28px 28px 22px;
}
.brand {
    margin-bottom: 20px;
}
.brand-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}
.brand-subtitle {
    margin-top: 6px;
    font-size: 13px;
    color: #64748b;
}
.section-title {
    font-size: 14px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid #f1f5f9;
}
.hint {
    margin-top: 16px;
    font-size: 13px;
    color: #64748b;
    text-align: center;
}
.link {
    color: #409eff;
    text-decoration: none;
    font-weight: 500;
}
.link:hover {
    text-decoration: underline;
}
</style>
