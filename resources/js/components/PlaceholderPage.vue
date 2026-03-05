<template>
    <div class="placeholder-page">
        <el-card class="placeholder-card">
            <template #header>
                <span>{{ title }}</span>
            </template>
            <div class="placeholder-body">
                <el-icon class="placeholder-icon" :size="48"><InfoFilled /></el-icon>
                <p class="placeholder-desc">该功能正在完善中，敬请期待。</p>
                <p class="placeholder-hint">您可先使用左侧菜单中的「供应商管理」「客户管理」「商品分类」等已开放功能。</p>
            </div>
        </el-card>
    </div>
</template>

<script>
import { defineComponent } from "vue";
import { InfoFilled } from "@element-plus/icons-vue";

export default defineComponent({
    name: "PlaceholderPage",
    components: { InfoFilled },
    props: {
        title: { type: String, default: "功能开发中" },
    },
});
</script>

<style scoped>
.placeholder-page {
    padding: 20px;
}
.placeholder-card {
    max-width: 560px;
    margin: 40px auto;
}
.placeholder-body {
    text-align: center;
    padding: 24px 0;
}
.placeholder-icon {
    color: var(--el-color-info-light-5);
    margin-bottom: 16px;
}
.placeholder-desc {
    font-size: 16px;
    color: var(--el-text-color-primary);
    margin: 0 0 8px;
}
.placeholder-hint {
    font-size: 14px;
    color: var(--el-text-color-secondary);
    margin: 0;
}
</style>
