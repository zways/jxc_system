<template>
    <el-config-provider :locale="zhCn">
        <div class="app-container" :class="{ 'login-layout': isLogin }">
            <!-- 顶部工具栏 -->
            <TopHeader v-if="!isLogin" />

            <div v-if="!isLogin" class="main-layout">
                <!-- 左侧导航栏 -->
                <LeftSidebar />

                <!-- 主内容区域 -->
                <div class="main-content">
                    <router-view v-slot="{ Component }">
                        <keep-alive :max="5">
                            <component :is="Component" />
                        </keep-alive>
                    </router-view>
                </div>
            </div>

            <!-- 登录页：不渲染顶部与侧边栏 -->
            <router-view v-else />
        </div>
    </el-config-provider>
</template>

<script>
import { computed, defineComponent } from "vue";
import { useRoute } from "vue-router";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import TopHeader from "./components/TopHeader.vue";
import LeftSidebar from "./components/LeftSidebar.vue";

export default defineComponent({
    name: "App",
    components: {
        TopHeader,
        LeftSidebar,
    },
    setup() {
        const route = useRoute();
        const isLogin = computed(() => route.path === "/login" || route.path === "/register");
        return {
            zhCn,
            isLogin,
        };
    },
});
</script>

<style>
.app-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background-color: #f0f2f5;
}
.app-container.login-layout {
    background: transparent;
}

.main-layout {
    display: flex;
    flex: 1;
    overflow: hidden;
}

.main-content {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    background-color: #f5f7fa;
}
</style>
