// @ts-check
const { defineConfig, devices } = require('playwright/test');

/**
 * 进销存系统 E2E 测试配置
 * 使用超管账户模拟人类操作，测试全部页面与功能
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    video: 'on-first-retry',
    actionTimeout: 15000,
    navigationTimeout: 20000,
    locale: 'zh-CN',
    viewport: { width: 1280, height: 720 },
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
