<template>
    <div class="forgot-page">
        <div class="forgot-card">
            <div class="brand">
                <div class="brand-title">找回密码</div>
                <div class="brand-subtitle">请输入注册时使用的邮箱，我们将发送重置链接</div>
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
                        placeholder="请输入注册邮箱"
                        size="large"
                        @keyup="(e) => e.key === 'Enter' && submit()"
                    />
                </el-form-item>

                <el-button
                    type="primary"
                    size="large"
                    style="width: 100%"
                    :loading="loading"
                    @click="submit"
                >
                    发送重置链接
                </el-button>

                <div class="back-hint">
                    <router-link to="/login" class="back-link">返回登录</router-link>
                </div>
            </el-form>
        </div>
    </div>
</template>

<script>
import { defineComponent, reactive, ref } from "vue";
import { ElMessage } from "element-plus";

export default defineComponent({
    name: "ForgotPasswordView",
    setup() {
        const loading = ref(false);
        const formRef = ref(null);
        const form = reactive({ email: "" });
        const rules = {
            email: [
                { required: true, message: "请输入邮箱", trigger: "blur" },
                { type: "email", message: "请输入有效的邮箱地址", trigger: "blur" },
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
                await window.axios.post("auth/forgot-password", { email: form.email });
                ElMessage.success("若该邮箱已注册，您将收到重置链接，请查收邮件");
            } catch (e) {
                const errData = e.response?.data;
                ElMessage.error(errData?.message || "发送失败，请稍后再试");
            } finally {
                loading.value = false;
            }
        };

        return { loading, formRef, form, rules, submit };
    },
});
</script>

<style scoped>
.forgot-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(1200px 600px at 30% 20%, #e0f2fe, transparent),
        radial-gradient(900px 500px at 80% 70%, #ede9fe, transparent),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
    padding: 24px;
}
.forgot-card {
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
