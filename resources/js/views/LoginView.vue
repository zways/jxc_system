<template>
    <div class="login-page">
        <div class="login-card">
            <div class="brand">
                <div class="brand-title">进销存管理系统</div>
                <div class="brand-subtitle">请登录后继续</div>
            </div>

            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-position="top"
                @submit.prevent
            >
                <el-form-item label="用户名 / 邮箱" prop="username">
                    <el-input
                        v-model="form.username"
                        autocomplete="username"
                        placeholder="请输入用户名或邮箱"
                        size="large"
                        @keyup="(e) => e.key === 'Enter' && submit()"
                    />
                </el-form-item>
                <el-form-item label="密码" prop="password">
                    <el-input
                        v-model="form.password"
                        type="password"
                        show-password
                        autocomplete="current-password"
                        placeholder="请输入密码"
                        size="large"
                        @keyup="(e) => e.key === 'Enter' && submit()"
                    />
                </el-form-item>

                <!-- 登录锁定倒计时提示 -->
                <el-alert
                    v-if="lockCountdown > 0"
                    :title="`登录失败次数过多，请 ${lockCountdownText} 后再试`"
                    type="error"
                    :closable="false"
                    show-icon
                    style="margin-bottom: 12px"
                />

                <el-button
                    type="primary"
                    size="large"
                    style="width: 100%"
                    :loading="loading"
                    :disabled="lockCountdown > 0"
                    @click="submit"
                >
                    {{ lockCountdown > 0 ? lockCountdownText : '登录' }}
                </el-button>

                <div class="extra-links">
                    <template v-if="passwordResetEnabled">
                        <router-link to="/forgot-password" class="link">忘记密码？</router-link>
                        <span class="sep">|</span>
                    </template>
                    <span>还没有企业账号？</span>
                    <router-link to="/register" class="link">立即注册</router-link>
                </div>
            </el-form>
        </div>
    </div>
</template>

<script>
import { defineComponent, reactive, ref, computed, onBeforeUnmount, onMounted } from "vue";
import { useRoute } from "vue-router";
import { ElMessage } from "element-plus";

const LS_TOKEN = "auth_token";
const LS_USER = "auth_user";
const LS_PERMS = "auth_permissions";

export default defineComponent({
    name: "LoginView",
    setup() {
        const route = useRoute();
        const loading = ref(false);
        const formRef = ref(null);
        const passwordResetEnabled = ref(false);
        const form = reactive({
            username: "",
            password: "",
        });

        onMounted(async () => {
            try {
                const res = await window.axios.get("auth/password-reset-config");
                passwordResetEnabled.value = res.data?.data?.enabled === true;
            } catch {
                passwordResetEnabled.value = false;
            }
        });
        const rules = {
            username: [{ required: true, message: "请输入用户名或邮箱", trigger: "blur" }],
            password: [{ required: true, message: "请输入密码", trigger: "blur" }],
        };

        // ---- 登录锁定倒计时 ----
        const lockCountdown = ref(0);
        let lockTimer = null;

        const lockCountdownText = computed(() => {
            const s = lockCountdown.value;
            if (s <= 0) return "";
            const m = Math.floor(s / 60);
            const sec = s % 60;
            return m > 0 ? `${m}分${sec}秒` : `${sec}秒`;
        });

        function startLockCountdown(seconds) {
            lockCountdown.value = seconds;
            if (lockTimer) clearInterval(lockTimer);
            lockTimer = setInterval(() => {
                lockCountdown.value--;
                if (lockCountdown.value <= 0) {
                    clearInterval(lockTimer);
                    lockTimer = null;
                }
            }, 1000);
        }

        onBeforeUnmount(() => {
            if (lockTimer) clearInterval(lockTimer);
        });

        const submit = async () => {
            if (lockCountdown.value > 0) return;
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            loading.value = true;
            try {
                const res = await window.axios.post("auth/login", {
                    username: form.username,
                    password: form.password,
                    device_name: "web",
                });
                const payload = res.data?.data || {};
                localStorage.setItem(LS_TOKEN, payload.token || "");
                localStorage.setItem(LS_USER, JSON.stringify(payload.user || {}));
                localStorage.setItem(LS_PERMS, JSON.stringify(payload.permissions || []));
                ElMessage.success("登录成功");

                const redirect = route.query?.redirect;
                // 安全校验：仅允许以 / 开头的内部路径，防止开放重定向攻击
                const safeRedirect = typeof redirect === "string" && redirect.startsWith("/") && !redirect.startsWith("//")
                    ? redirect
                    : "/dashboard";
                // 使用整页跳转，确保新页面加载时 token 已写入，避免路由切换与接口竞态导致 401 被踢回登录页
                window.location.replace(safeRedirect);
            } catch (e) {
                const errData = e.response?.data;
                const status = e.response?.status;

                // 429 = 被锁定，启动倒计时
                if (status === 429 && errData?.data?.retry_after_seconds) {
                    startLockCountdown(errData.data.retry_after_seconds);
                    ElMessage.error(errData?.message || "登录失败次数过多，请稍后再试");
                } else {
                    ElMessage.error(errData?.message || "登录失败");
                }
            } finally {
                loading.value = false;
            }
        };

        return {
            loading,
            formRef,
            form,
            rules,
            submit,
            lockCountdown,
            lockCountdownText,
            passwordResetEnabled,
        };
    },
});
</script>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(1200px 600px at 30% 20%, #e0f2fe, transparent),
        radial-gradient(900px 500px at 80% 70%, #ede9fe, transparent),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
    padding: 24px;
}
.login-card {
    width: 420px;
    max-width: 92vw;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(15, 23, 42, 0.06);
    border-radius: 14px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
    padding: 22px 22px 18px;
}
.brand {
    margin-bottom: 18px;
}
.brand-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}
.brand-subtitle {
    margin-top: 6px;
    font-size: 12px;
    color: #64748b;
}
.extra-links {
    margin-top: 12px;
    font-size: 13px;
    color: #475569;
    text-align: center;
}
.extra-links .sep {
    margin: 0 8px;
    color: #cbd5e1;
}
.extra-links .link {
    color: #409eff;
    text-decoration: none;
    font-weight: 500;
}
.extra-links .link:hover {
    text-decoration: underline;
}
</style>

