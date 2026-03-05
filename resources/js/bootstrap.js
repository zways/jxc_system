import axios from 'axios';
import { ElMessage } from 'element-plus';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
// API 基础路径（与 Laravel routes/api.php 一致）
window.axios.defaults.baseURL = '/api/v1/';

const LS_TOKEN = 'auth_token';
const LS_USER = 'auth_user';
const LS_PERMS = 'auth_permissions';

function redirectToLoginNow() {
    // 防止同一时刻多次 401 触发重复跳转
    if (window.__auth_redirecting) return;
    window.__auth_redirecting = true;

    const current = window.location.pathname + window.location.search + window.location.hash;
    const target = `/login?redirect=${encodeURIComponent(current)}`;

    // 立即引导到登录页（无需用户点击确认）
    if (window.location.pathname !== '/login') {
        window.location.replace(target);
    } else {
        window.__auth_redirecting = false;
    }
}

// 请求拦截器
window.axios.interceptors.request.use(
    (config) => {
        // 可以在这里添加 loading 状态或 token
        const token = localStorage.getItem(LS_TOKEN);
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// 响应拦截器 - 统一错误处理
window.axios.interceptors.response.use(
    (response) => {
        // 正常响应直接返回
        return response;
    },
    (error) => {
        const { response } = error;
        
        if (!response) {
            // 网络错误或请求被取消
            ElMessage.error('网络连接失败，请检查您的网络');
            return Promise.reject(error);
        }

        const { status } = response;

        switch (status) {
            case 401:
                // 未认证，清除本地存储并跳转登录
                localStorage.removeItem(LS_TOKEN);
                localStorage.removeItem(LS_USER);
                localStorage.removeItem(LS_PERMS);
                // 登录接口本身返回 401 时不做跳转（避免循环）
                if (!String(response?.config?.url || '').includes('auth/login')) {
                    ElMessage.warning('请先登录');
                    redirectToLoginNow();
                }
                break;
            case 403:
                ElMessage.error('没有权限执行此操作');
                break;
            // 其他错误（400/404/422/429/500等）由各组件的 catch 块自行处理，
            // 避免双重弹出错误提示
        }

        return Promise.reject(error);
    }
);
