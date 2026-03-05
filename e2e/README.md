# E2E 测试说明

使用 **超管账户** 与 **企业账户** 分别模拟人类操作，对进销存系统全部页面与关键功能做端到端测试。

## 账号（与 Seeders 一致）

| 类型     | 用户名      | 密码           | 说明 |
|----------|-------------|----------------|------|
| 超管     | `admin`     | `Admin@2026`   | 可见「企业管理」、全部数据 |
| 企业账户 | `enterprise`| `Enterprise@2026` | 归属门店，可见「企业信息」、不可见「企业管理」 |

若数据库未执行过 Seeders 或账号已改，请先执行：

```bash
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=EnterpriseUserSeeder
# 或一次性：php artisan db:seed
```

**企业账号为何没有测试数据？** 企业账号只能看到其所属门店（STORE0001）的数据。完整执行 `php artisan db:seed` 时，`AssignTestDataToEnterpriseStoreSeeder` 会把未归属的测试数据挂到 STORE0001，企业账号即可看到。若之前只单独跑过 `EnterpriseUserSeeder`，可再执行一次 `php artisan db:seed --class=AssignTestDataToEnterpriseStoreSeeder` 将现有测试数据归属到 STORE0001。

## 运行前准备

1. **安装 Playwright 浏览器（首次或升级后必做）**：
   ```bash
   npx playwright install
   # 或仅安装 Chromium: npx playwright install chromium
   ```

2. 启动 Laravel 后端（默认 `http://127.0.0.1:8000`）：
   ```bash
   php artisan serve
   ```

3. 前端资源需已构建或通过 Vite 代理：
   ```bash
   npm run build
   # 或开发时: npm run dev
   ```

4. 可选：指定测试基址
   ```bash
   export PLAYWRIGHT_BASE_URL=http://127.0.0.1:8000
   ```

## 运行测试

若在 Cursor/IDE 内置终端运行时报「Executable doesn't exist」，可先指定本机浏览器路径再执行：

```bash
# macOS 使用本机已安装的 Playwright 浏览器
PLAYWRIGHT_BROWSERS_PATH="$HOME/Library/Caches/ms-playwright" npm run e2e
```

常规运行：

```bash
# 无头模式（默认）
npm run e2e

# 有头浏览器，便于调试
npm run e2e:headed

# Playwright UI 模式
npm run e2e:ui
```

## 测试内容概览

- **超管套件**：登录页、登录成功进入工作台、逐页访问（含「企业管理」）、供应商/采购/销售/盘点 查询与新增弹窗、财务与系统页、未登录重定向。
- **企业账户套件**：同上，但逐页访问不含「企业管理」、含「企业信息」；直接访问 `/system/tenant-list` 会被重定向（无权限）。

全部通过即表示在超管与企业账户两种身份下，页面与上述功能均可正常使用。
