<template>
    <div class="reset-page">
        <div class="reset-card">
            <div class="brand">
                <div class="brand-title">设置新密码</div>
                <div class="brand-subtitle">请设置您的新登录密码</div>
            </div>

            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-position="top"
                @submit.prevent
            >
                <el-form-item label="邮箱" prop="email">
                    <el-input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="注册邮箱"
                        size="large"
                        :disabled="!!emailFromQuery"
                    />
                </el-form-item>
                <el-form-item label="新密码" prop="password">
                    <el-input
                        v-model="form.password"
                        type="password"
                        show-password
                        autocomplete="new-password"
                        placeholder="请输入新密码（至少 6 位）"
                        size="large"
                        @keyup="(e) => e.key === 'Enter' && submit()"
                    />
                </el-form-item>
                <el-form-item label="确认密码" prop="password_confirmation">
                    <el-input
                        v-model="form.password_confirmation"
                        type="password"
                        show-password
                        autocomplete="new-password"
                        placeholder="请再次输入新密码"
                        size="large"
                        @keyup="(e) => e.key === 'Enter' && submit()"
                    />
                </el-form-item>

                <el-button
                    type="primary"
                    size="large"
                    style="width: 100%"
                    :loading="loading"
                    :disabled="!form.token"
                    @click="submit"
                >
                    重置密码
                </el-button>

                <div class="back-hint">
                    <router-link to="/login" class="back-link">返回登录</router-link>
                </div>
            </el-form>
        </div>
    </div>
</template>

<script>
import { defineComponent, reactive, ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { ElMessage } from "element-plus";

export default defineComponent({
    name: "ResetPasswordView",
    setup() {
        const route = useRoute();
        const loading = ref(false);
        const formRef = ref(null);

        const emailFromQuery = computed(() => route.query.email || "");
        const tokenFromQuery = computed(() => route.query.token || "");

        const form = reactive({
            email: "",
            token: "",
            password: "",
            password_confirmation: "",
        });

        const validateConfirm = (_rule, value, callback) => {
            if (value !== form.password) {
                callback(new Error("两次输入的密码不一致"));
            } else {
                callback();
            }
        };

        const rules = {
            email: [
                { required: true, message: "请输入邮箱", trigger: "blur" },
                { type: "email", message: "请输入有效的邮箱地址", trigger: "blur" },
            ],
            password: [
                { required: true, message: "请输入新密码", trigger: "blur" },
                { min: 6, message: "密码至少 6 位", trigger: "blur" },
            ],
            password_confirmation: [
                { required: true, message: "请再次输入新密码", trigger: "blur" },
                { validator: validateConfirm, trigger: "blur" },
            ],
        };

        onMounted(() => {
            form.email = typeof emailFromQuery.value === "string" ? emailFromQuery.value : "";
            form.token = typeof tokenFromQuery.value === "string" ? tokenFromQuery.value : "";
            if (!form.token) {
                ElMessage.warning("重置链接无效，请从邮件中的链接进入或重新申请找回密码");
            }
        });

        const submit = async () => {
            if (!form.token) return;
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            loading.value = true;
            try {
                await window.axios.post("auth/reset-password", {
                    email: form.email,
                    token: form.token,
                    password: form.password,
                    password_confirmation: form.password_confirmation,
                });
                ElMessage.success("密码已重置，请使用新密码登录");
                window.location.replace("/login");
            } catch (e) {
                const errData = e.response?.data;
                ElMessage.error(errData?.message || "重置失败，请检查链接是否有效");
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
            emailFromQuery,
        };
    },
});
</script>

<style scoped>
.reset-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(1200px 600px at 30% 20%, #e0f2fe, transparent),
        radial-gradient(900px 500px at 80% 70%, #ede9fe, transparent),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
    padding: 24px;
}
.reset-card {
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
.back-hint {
    margin-top: 12px;
    font-size: 13px;
    color: #475569;
    text-align: center;
}
.back-link {
    color: #409eff;
    text-decoration: none;
    font-weight: 500;
}
.back-link:hover {
    text-decoration: underline;
}
</style>
