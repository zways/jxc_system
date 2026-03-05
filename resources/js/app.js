import './bootstrap'
import { createApp } from 'vue'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import App from './App.vue'
import router from './router'

const app = createApp(App)

// 注册所有图标（多种命名，兼容 CDN 与打包后 Element Plus 的解析）
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
  app.component(`ElIcon${key}`, component)
  // Element Plus 内部可能解析为 ElementPlus.ElIconXxx
  app.component(`ElementPlus.ElIcon${key}`, component)
}

app.use(ElementPlus)
app.use(router)
app.mount('#app')