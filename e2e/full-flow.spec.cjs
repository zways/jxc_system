// @ts-check
const { test, expect } = require('playwright/test');

/** 超管账号（与 UserSeeder 一致） */
const SUPER_ADMIN = {
  username: 'admin',
  password: 'Admin@2026',
};

/** 企业账户（与 EnterpriseUserSeeder 一致，归属门店，无「企业管理」有「企业信息」） */
const ENTERPRISE = {
  username: 'enterprise',
  password: 'Enterprise@2026',
};

/** 模拟人类操作：随机短延迟 300~700ms */
function humanDelay(page) {
  return page.waitForTimeout(300 + Math.floor(Math.random() * 400));
}

test.describe('超管全功能 E2E：模拟人类操作', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('1. 登录页展示并可用', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByText('进销存管理系统')).toBeVisible();
    await expect(page.getByPlaceholder('请输入用户名或邮箱')).toBeVisible();
    await expect(page.getByPlaceholder('请输入密码')).toBeVisible();
    await expect(page.getByRole('button', { name: '登录' })).toBeVisible();
    await humanDelay(page);
  });

  test('2. 超管登录成功并进入工作台', async ({ page }) => {
    await page.goto('/login');
    await humanDelay(page);
    await page.getByPlaceholder('请输入用户名或邮箱').fill(SUPER_ADMIN.username);
    await humanDelay(page);
    await page.getByPlaceholder('请输入密码').fill(SUPER_ADMIN.password);
    await humanDelay(page);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
    await humanDelay(page);
  });

  test('3. 登录后逐页访问并校验（采购、销售、库存、财务、报表、系统）', async ({ page }) => {
    // 登录
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(SUPER_ADMIN.username);
    await page.getByPlaceholder('请输入密码').fill(SUPER_ADMIN.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
    await humanDelay(page);

    const routes = [
      { path: '/dashboard', expectInMain: '首页仪表盘' },
      { path: '/purchase/supplier', expectInMain: '供应商' },
      { path: '/purchase/order', expectInMain: '采购' },
      { path: '/purchase/inbound', expectInMain: '入库' },
      { path: '/sales/customer', expectInMain: '客户' },
      { path: '/sales/order', expectInMain: '销售' },
      { path: '/sales/outbound', expectInMain: '出库' },
      { path: '/sales/return', expectInMain: '退货' },
      { path: '/sales/exchange', expectInMain: '换货' },
      { path: '/inventory/current', expectInMain: '库存' },
      { path: '/inventory/transfer', expectInMain: '调拨' },
      { path: '/inventory/count', expectInMain: '盘点' },
      { path: '/inventory/adjustment', expectInMain: '调整' },
      { path: '/finance/receivable', expectInMain: '应收' },
      { path: '/finance/payable', expectInMain: '应付' },
      { path: '/finance/transaction', expectInMain: '收支' },
      { path: '/finance/reconciliation', expectInMain: '对账' },
      { path: '/reports', expectInMain: '报表' },
      { path: '/system/user', expectInMain: '用户' },
      { path: '/system/department', expectInMain: '部门' },
      { path: '/system/role', expectInMain: '角色' },
      { path: '/system/product', expectInMain: '商品' },
      { path: '/system/warehouse', expectInMain: '仓库' },
      { path: '/system/store', expectInMain: '门店' },
      { path: '/system/agent', expectInMain: '业务员' },
      { path: '/system/category', expectInMain: '分类' },
      { path: '/system/unit', expectInMain: '单位' },
      { path: '/system/audit-log', expectInMain: '操作日志' },
      { path: '/system/tenant-profile', expectInMain: '企业' },
      { path: '/system/tenant-list', expectInMain: '企业管理' },
    ];

    const main = page.locator('.main-content').or(page.locator('.dashboard-container'));
    for (const r of routes) {
      await page.goto(r.path);
      await humanDelay(page);
      await expect(page).not.toHaveURL(/\/login/);
      await expect(main.first()).toBeVisible({ timeout: 8000 });
      await expect(main.filter({ hasText: r.expectInMain }).first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('4. 关键操作：列表查询与新增弹窗（供应商/采购/销售/库存）', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(SUPER_ADMIN.username);
    await page.getByPlaceholder('请输入密码').fill(SUPER_ADMIN.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
    await humanDelay(page);

    // 供应商：点击查询、点击新增后关闭
    await page.goto('/purchase/supplier');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    await page.getByRole('button', { name: '新增供应商' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '供应商' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);
    await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
    await humanDelay(page);

    // 采购订单：查询、新增后关闭
    await page.goto('/purchase/order');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    const addPo = page.getByRole('button', { name: '新增采购单' });
    if (await addPo.isVisible()) {
      await addPo.click();
      await expect(page.getByRole('dialog').filter({ hasText: '采购' })).toBeVisible({ timeout: 5000 });
      await humanDelay(page);
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
    }
    await humanDelay(page);

    // 销售订单：查询、新增后关闭
    await page.goto('/sales/order');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    const addSo = page.getByRole('button', { name: '新增销售单' });
    if (await addSo.isVisible()) {
      await addSo.click();
      await expect(page.getByRole('dialog').filter({ hasText: '销售' })).toBeVisible({ timeout: 5000 });
      await humanDelay(page);
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
    }
    await humanDelay(page);

    // 库存盘点：查询、新增盘点单后关闭
    await page.goto('/inventory/count');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    await page.getByRole('button', { name: '新增盘点单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '盘点' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);
    await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
  });

  test('5. 财务与系统：应收/应付列表、用户管理、角色配置', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(SUPER_ADMIN.username);
    await page.getByPlaceholder('请输入密码').fill(SUPER_ADMIN.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await humanDelay(page);

    await page.goto('/finance/receivable');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    await page.getByRole('button', { name: '查询' }).first().click().catch(() => {});
    await humanDelay(page);

    await page.goto('/finance/payable');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    await page.getByRole('button', { name: '查询' }).first().click().catch(() => {});
    await humanDelay(page);

    await page.goto('/system/user');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    const addUser = page.getByRole('button', { name: '新增用户' });
    if (await addUser.isVisible()) {
      await addUser.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
    }
    await humanDelay(page);

    await page.goto('/system/role');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    await humanDelay(page);
  });

  test('6. 未登录访问受保护页会跳转登录', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
    await page.goto('/purchase/supplier');
    await expect(page).toHaveURL(/\/login/);
  });
});

// ——————— 企业账户：严格模拟人类操作，覆盖企业可见的全部页面与关键功能 ———————
test.describe('企业账户全功能 E2E：模拟人类操作', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('1. 企业账户登录页展示并可用', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByText('进销存管理系统')).toBeVisible();
    await expect(page.getByPlaceholder('请输入用户名或邮箱')).toBeVisible();
    await expect(page.getByPlaceholder('请输入密码')).toBeVisible();
    await expect(page.getByRole('button', { name: '登录' })).toBeVisible();
    await humanDelay(page);
  });

  test('2. 企业账户登录成功并进入工作台', async ({ page }) => {
    await page.goto('/login');
    await humanDelay(page);
    await page.getByPlaceholder('请输入用户名或邮箱').fill(ENTERPRISE.username);
    await humanDelay(page);
    await page.getByPlaceholder('请输入密码').fill(ENTERPRISE.password);
    await humanDelay(page);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
    await humanDelay(page);
  });

  test('3. 企业账户逐页访问（无企业管理，有企业信息）', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(ENTERPRISE.username);
    await page.getByPlaceholder('请输入密码').fill(ENTERPRISE.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
    await humanDelay(page);

    // 企业账户可见路由：不含 /system/tenant-list（超管专属），含 /system/tenant-profile（企业信息）
    const routes = [
      { path: '/dashboard', expectInMain: '首页仪表盘' },
      { path: '/purchase/supplier', expectInMain: '供应商' },
      { path: '/purchase/order', expectInMain: '采购' },
      { path: '/purchase/inbound', expectInMain: '入库' },
      { path: '/sales/customer', expectInMain: '客户' },
      { path: '/sales/order', expectInMain: '销售' },
      { path: '/sales/outbound', expectInMain: '出库' },
      { path: '/sales/return', expectInMain: '退货' },
      { path: '/sales/exchange', expectInMain: '换货' },
      { path: '/inventory/current', expectInMain: '库存' },
      { path: '/inventory/transfer', expectInMain: '调拨' },
      { path: '/inventory/count', expectInMain: '盘点' },
      { path: '/inventory/adjustment', expectInMain: '调整' },
      { path: '/finance/receivable', expectInMain: '应收' },
      { path: '/finance/payable', expectInMain: '应付' },
      { path: '/finance/transaction', expectInMain: '收支' },
      { path: '/finance/reconciliation', expectInMain: '对账' },
      { path: '/reports', expectInMain: '报表' },
      { path: '/system/user', expectInMain: '用户' },
      { path: '/system/department', expectInMain: '部门' },
      { path: '/system/role', expectInMain: '角色' },
      { path: '/system/product', expectInMain: '商品' },
      { path: '/system/warehouse', expectInMain: '仓库' },
      { path: '/system/store', expectInMain: '门店' },
      { path: '/system/agent', expectInMain: '业务员' },
      { path: '/system/category', expectInMain: '分类' },
      { path: '/system/unit', expectInMain: '单位' },
      { path: '/system/audit-log', expectInMain: '操作日志' },
      { path: '/system/tenant-profile', expectInMain: '企业' },
    ];

    const main = page.locator('.main-content').or(page.locator('.dashboard-container'));
    for (const r of routes) {
      await page.goto(r.path);
      await humanDelay(page);
      await expect(page).not.toHaveURL(/\/login/);
      await expect(main.first()).toBeVisible({ timeout: 8000 });
      await expect(main.filter({ hasText: r.expectInMain }).first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('4. 企业账户访问企业管理应被重定向（入口已隐藏，直接访问 URL 亦重定向）', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(ENTERPRISE.username);
    await page.getByPlaceholder('请输入密码').fill(ENTERPRISE.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await humanDelay(page);
    await page.goto('/system/tenant-list');
    await humanDelay(page);
    // 仅超管可访问：路由守卫将非超管重定向到 dashboard
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('5. 企业账户关键操作：查询与新增弹窗（供应商/采购/销售/盘点）', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(ENTERPRISE.username);
    await page.getByPlaceholder('请输入密码').fill(ENTERPRISE.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
    await humanDelay(page);

    await page.goto('/purchase/supplier');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    await page.getByRole('button', { name: '新增供应商' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '供应商' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);
    await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
    await humanDelay(page);

    await page.goto('/purchase/order');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    const addPo = page.getByRole('button', { name: '新增采购单' });
    if (await addPo.isVisible()) {
      await addPo.click();
      await expect(page.getByRole('dialog').filter({ hasText: '采购' })).toBeVisible({ timeout: 5000 });
      await humanDelay(page);
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
    }
    await humanDelay(page);

    await page.goto('/sales/order');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    const addSo = page.getByRole('button', { name: '新增销售单' });
    if (await addSo.isVisible()) {
      await addSo.click();
      await expect(page.getByRole('dialog').filter({ hasText: '销售' })).toBeVisible({ timeout: 5000 });
      await humanDelay(page);
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
    }
    await humanDelay(page);

    await page.goto('/inventory/count');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    await page.getByRole('button', { name: '新增盘点单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '盘点' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);
    await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
  });

  test('6. 企业账户财务与系统：应收/应付/用户/角色', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(ENTERPRISE.username);
    await page.getByPlaceholder('请输入密码').fill(ENTERPRISE.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await humanDelay(page);

    await page.goto('/finance/receivable');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    await page.getByRole('button', { name: '查询' }).first().click().catch(() => {});
    await humanDelay(page);

    await page.goto('/finance/payable');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    await page.getByRole('button', { name: '查询' }).first().click().catch(() => {});
    await humanDelay(page);

    await page.goto('/system/user');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    const addUser = page.getByRole('button', { name: '新增用户' });
    if (await addUser.isVisible()) {
      await addUser.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
    }
    await humanDelay(page);

    await page.goto('/system/role');
    await humanDelay(page);
    await expect(page.locator('.main-content')).toBeVisible({ timeout: 8000 });
    await humanDelay(page);
  });

  test('7. 未登录访问受保护页会跳转登录', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
    await page.goto('/purchase/supplier');
    await expect(page).toHaveURL(/\/login/);
  });
});
