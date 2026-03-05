// @ts-check
/**
 * 全部按钮流程 E2E 测试
 * 覆盖系统中所有页面的查询、新增、取消等按钮操作
 */
const { test, expect } = require('playwright/test');

/** 超管账号 */
const SUPER_ADMIN = { username: 'admin', password: 'Admin@2026' };

/** 企业账户（归属门店，无「企业管理」有「企业信息」）；需已执行 EnterpriseUserSeeder（依赖 STORE0001） */
const ENTERPRISE = { username: 'enterprise', password: 'Enterprise@2026' };

function humanDelay(page) {
  return page.waitForTimeout(200 + Math.floor(Math.random() * 200));
}

async function login(page) {
  // 清除上一用例（如企业账户）的 session，确保能显示登录表单
  await page.context().clearCookies();
  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  });
  await page.goto('/login');
  await page.getByPlaceholder('请输入用户名或邮箱').fill(SUPER_ADMIN.username);
  await page.getByPlaceholder('请输入密码').fill(SUPER_ADMIN.password);
  await page.getByRole('button', { name: '登录' }).click();
  await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
  await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
  await humanDelay(page);
}

async function loginAsEnterprise(page) {
  // 清除上一用例（如超管）的 session，避免企业账户登录被拒绝
  await page.context().clearCookies();
  // 清除 localStorage/sessionStorage，否则残留 token 会导致路由重定向到 dashboard 而非显示登录表单
  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  });
  await page.goto('/login');
  await humanDelay(page);
  await page.getByPlaceholder('请输入用户名或邮箱').fill(ENTERPRISE.username);
  await page.getByPlaceholder('请输入密码').fill(ENTERPRISE.password);
  await humanDelay(page);
  await page.getByRole('button', { name: '登录' }).click();
  await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
  await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
  await humanDelay(page);
}

/** 通用：点击查询按钮（如存在） */
async function clickQueryIfExists(page) {
  const btn = page.getByRole('button', { name: '查询' }).first();
  if (await btn.isVisible().catch(() => false)) {
    await btn.click();
    await humanDelay(page);
  }
}

/** 通用：新增弹窗流程 - 点击新增 -> 验证弹窗 -> 点击取消/关闭 */
async function testAddThenCancel(page, addButtonName, dialogKeyword, cancelButtonName = '取消') {
  const addBtn = page.getByRole('button', { name: addButtonName });
  if (!(await addBtn.isVisible().catch(() => false))) return;
  await addBtn.click();
  await humanDelay(page);
  const dialog = page.getByRole('dialog').filter({ hasText: dialogKeyword });
  await expect(dialog).toBeVisible({ timeout: 5000 });
  const cancelBtn = page.getByRole('dialog').getByRole('button', { name: cancelButtonName });
  if (await cancelBtn.isVisible().catch(() => false)) {
    await cancelBtn.click();
  } else {
    const closeBtn = page.getByRole('dialog').getByRole('button', { name: '关闭' });
    if (await closeBtn.isVisible().catch(() => false)) await closeBtn.click();
  }
  await humanDelay(page);
}

// ——————— 企业账户先运行，避免在超管 session 之后首次登录失败 ———————
// ——————— 企业账户：全部按钮流程（无企业管理，有企业信息） ———————
test.describe('企业账户全部按钮流程 E2E', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('采购模块：供应商/采购单/入库', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/purchase/supplier');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await page.getByRole('button', { name: '新增供应商' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '供应商' })).toBeVisible({ timeout: 5000 });
    await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
    await humanDelay(page);

    await page.goto('/purchase/order');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增采购单', '采购', '取消');
    await humanDelay(page);

    await page.goto('/purchase/inbound');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增入库单', '入库', '取消');
  });

  test('销售模块：客户/销售单/出库/退货/换货', async ({ page }) => {
    await loginAsEnterprise(page);
    const salesPages = [
      { path: '/sales/customer', addBtn: '新增客户', keyword: '客户' },
      { path: '/sales/order', addBtn: '新增销售单', keyword: '销售' },
      { path: '/sales/outbound', addBtn: '新增出库单', keyword: '出库' },
      { path: '/sales/return', addBtn: '新增退货单', keyword: '退货' },
      { path: '/sales/exchange', addBtn: '新增换货单', keyword: '换货' },
    ];
    for (const p of salesPages) {
      await page.goto(p.path);
      await humanDelay(page);
      await clickQueryIfExists(page);
      await testAddThenCancel(page, p.addBtn, p.keyword, '取消');
    }
  });

  test('库存模块：库存/调拨/盘点/调整', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/inventory/current');
    await humanDelay(page);
    await clickQueryIfExists(page);

    await page.goto('/inventory/transfer');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增调拨单', '调拨', '取消');

    await page.goto('/inventory/count');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增盘点单', '盘点', '取消');

    await page.goto('/inventory/adjustment');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增调整', '调整', '取消');
  });

  test('财务模块：应收/应付/收支/对账', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/finance/receivable');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增应收', '应收', '取消');

    await page.goto('/finance/payable');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增应付', '应付', '取消');

    await page.goto('/finance/transaction');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增流水', '流水', '取消');

    await page.goto('/finance/reconciliation');
    await humanDelay(page);
    await page.getByRole('button', { name: '刷新汇总' }).click();
    await humanDelay(page);
    await page.getByRole('button', { name: '查看应付明细' }).click();
    await expect(page).toHaveURL(/\/finance\/payable/);
    await humanDelay(page);
    await page.goto('/finance/reconciliation');
    await humanDelay(page);
    await page.getByRole('button', { name: '查看应收明细' }).click();
    await expect(page).toHaveURL(/\/finance\/receivable/);
  });

  test('报表模块：刷新数据、导出报表', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/reports');
    await humanDelay(page);
    await page.getByRole('button', { name: '刷新数据' }).click();
    await humanDelay(page);
    await page.getByRole('button', { name: '导出报表' }).click();
    await humanDelay(page);
    const exportItem = page.getByRole('menuitem', { name: /导出销售报表/ });
    if (await exportItem.isVisible().catch(() => false)) {
      await exportItem.click();
    }
    await humanDelay(page);
  });

  test('系统模块：用户/部门/角色/商品/仓库/门店/业务员/分类/单位', async ({ page }) => {
    await loginAsEnterprise(page);
    const systemPages = [
      { path: '/system/user', addBtn: '新增用户', keyword: '用户' },
      { path: '/system/department', addBtn: '新增部门', keyword: '部门' },
      { path: '/system/role', addBtn: '新增角色', keyword: '角色' },
      { path: '/system/product', addBtn: '新增商品', keyword: '商品' },
      { path: '/system/warehouse', addBtn: '新增仓库', keyword: '仓库' },
      { path: '/system/store', addBtn: '新增门店', keyword: '门店' },
      { path: '/system/agent', addBtn: '新增业务员', keyword: '业务员' },
      { path: '/system/category', addBtn: '新增分类', keyword: '分类' },
      { path: '/system/unit', addBtn: '新增单位', keyword: '单位' },
    ];
    for (const p of systemPages) {
      await page.goto(p.path);
      await humanDelay(page);
      await clickQueryIfExists(page);
      await testAddThenCancel(page, p.addBtn, p.keyword, '取消');
    }
  });

  test('系统模块：角色权限设置、操作日志、企业信息', async ({ page }) => {
    await loginAsEnterprise(page);

    await page.goto('/system/role');
    await humanDelay(page);
    const permBtn = page.getByRole('button', { name: '权限设置' }).first();
    if (await permBtn.isVisible().catch(() => false) && !(await permBtn.isDisabled().catch(() => true))) {
      await permBtn.click();
      await expect(page.getByRole('dialog').filter({ hasText: '权限设置' })).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
      await humanDelay(page);
    }

    await page.goto('/system/audit-log');
    await humanDelay(page);
    await clickQueryIfExists(page);
    const detailBtn = page.getByRole('button', { name: '详情' }).first();
    if (await detailBtn.isVisible().catch(() => false)) {
      await detailBtn.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '关闭', exact: true }).click();
    }
    await humanDelay(page);

    await page.goto('/system/tenant-profile');
    await humanDelay(page);
    await expect(page.locator('.main-content').or(page.locator('.dashboard-container'))).toBeVisible({ timeout: 8000 });
    const editBtn = page.getByRole('button', { name: '编辑' }).first();
    if (await editBtn.isVisible().catch(() => false)) {
      await editBtn.click();
      await humanDelay(page);
      const cancelBtn = page.getByRole('button', { name: '取消' });
      if (await cancelBtn.isVisible().catch(() => false)) {
        await cancelBtn.click();
      }
    }
  });

  test('企业账户访问企业管理应被重定向到 dashboard', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/system/tenant-list');
    await humanDelay(page);
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('顶部导航：新增单据下拉、导出数据', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/dashboard');
    await humanDelay(page);
    await page.getByRole('button', { name: '新增单据' }).click();
    await humanDelay(page);
    await page.getByRole('menuitem', { name: '销售订单' }).click();
    await expect(page).toHaveURL(/\/sales\/order/);
    await humanDelay(page);
    const salesDialog = page.getByRole('dialog').filter({ hasText: '销售' });
    if (await salesDialog.isVisible().catch(() => false)) {
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
      await humanDelay(page);
    }
    await page.getByRole('button', { name: '导出数据' }).click();
    await humanDelay(page);
  });

  test('列表行操作：查看、编辑（有数据时）', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/purchase/supplier');
    await humanDelay(page);
    await clickQueryIfExists(page);
    const viewBtn = page.locator('tbody tr').first().getByRole('button', { name: '查看' });
    if (await viewBtn.isVisible().catch(() => false)) {
      await viewBtn.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '关闭', exact: true }).or(
        page.getByRole('dialog').getByRole('button', { name: '取消' })
      ).first().click();
    }
    await humanDelay(page);

    await page.goto('/system/product');
    await humanDelay(page);
    await clickQueryIfExists(page);
    const editBtn = page.locator('tbody tr').first().getByRole('button', { name: '编辑' });
    if (await editBtn.isVisible().catch(() => false)) {
      await editBtn.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
    }
  });

  test('盘点明细：明细、保存明细、关闭', async ({ page }) => {
    await loginAsEnterprise(page);
    await page.goto('/inventory/count');
    await humanDelay(page);
    await clickQueryIfExists(page);
    const detailBtn = page.getByRole('button', { name: '明细' }).first();
    if (await detailBtn.isVisible().catch(() => false)) {
      await detailBtn.click();
      await expect(page.getByRole('dialog').filter({ hasText: '盘点明细' })).toBeVisible({ timeout: 5000 });
      await humanDelay(page);
      await page.getByRole('dialog').filter({ hasText: '盘点明细' }).getByRole('button', { name: '关闭', exact: true }).click();
    }
  });
});

// ——————— 超管套件（在企业账户之后运行，login 会清除 storage 以切换用户） ———————
test.describe('全部按钮流程 E2E', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('采购模块：供应商/采购单/入库', async ({ page }) => {
    await login(page);

    // 供应商：查询、新增供应商（弹窗用 确认/取消）
    await page.goto('/purchase/supplier');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await page.getByRole('button', { name: '新增供应商' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '供应商' })).toBeVisible({ timeout: 5000 });
    await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
    await humanDelay(page);

    // 采购订单
    await page.goto('/purchase/order');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增采购单', '采购', '取消');
    await humanDelay(page);

    // 入库
    await page.goto('/purchase/inbound');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增入库单', '入库', '取消');
  });

  test('销售模块：客户/销售单/出库/退货/换货', async ({ page }) => {
    await login(page);

    const salesPages = [
      { path: '/sales/customer', addBtn: '新增客户', keyword: '客户' },
      { path: '/sales/order', addBtn: '新增销售单', keyword: '销售' },
      { path: '/sales/outbound', addBtn: '新增出库单', keyword: '出库' },
      { path: '/sales/return', addBtn: '新增退货单', keyword: '退货' },
      { path: '/sales/exchange', addBtn: '新增换货单', keyword: '换货' },
    ];
    for (const p of salesPages) {
      await page.goto(p.path);
      await humanDelay(page);
      await clickQueryIfExists(page);
      await testAddThenCancel(page, p.addBtn, p.keyword, '取消');
    }
  });

  test('库存模块：库存/调拨/盘点/调整', async ({ page }) => {
    await login(page);

    // 实时库存：仅查询
    await page.goto('/inventory/current');
    await humanDelay(page);
    await clickQueryIfExists(page);

    // 调拨
    await page.goto('/inventory/transfer');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增调拨单', '调拨', '取消');

    // 盘点
    await page.goto('/inventory/count');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增盘点单', '盘点', '取消');

    // 调整
    await page.goto('/inventory/adjustment');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增调整', '调整', '取消');
  });

  test('财务模块：应收/应付/收支/对账', async ({ page }) => {
    await login(page);

    // 应收
    await page.goto('/finance/receivable');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增应收', '应收', '取消');

    // 应付
    await page.goto('/finance/payable');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增应付', '应付', '取消');

    // 收支
    await page.goto('/finance/transaction');
    await humanDelay(page);
    await clickQueryIfExists(page);
    await testAddThenCancel(page, '新增流水', '流水', '取消');

    // 对账：刷新汇总、查看应付明细、查看应收明细
    await page.goto('/finance/reconciliation');
    await humanDelay(page);
    await page.getByRole('button', { name: '刷新汇总' }).click();
    await humanDelay(page);
    await page.getByRole('button', { name: '查看应付明细' }).click();
    await expect(page).toHaveURL(/\/finance\/payable/);
    await humanDelay(page);
    await page.goto('/finance/reconciliation');
    await humanDelay(page);
    await page.getByRole('button', { name: '查看应收明细' }).click();
    await expect(page).toHaveURL(/\/finance\/receivable/);
  });

  test('报表模块：刷新数据、导出报表', async ({ page }) => {
    await login(page);
    await page.goto('/reports');
    await humanDelay(page);
    await page.getByRole('button', { name: '刷新数据' }).click();
    await humanDelay(page);
    // 导出报表为下拉，点击主按钮展开
    await page.getByRole('button', { name: '导出报表' }).click();
    await humanDelay(page);
    // 下拉展开后点击第一项（导出销售报表）
    const exportItem = page.getByRole('menuitem', { name: /导出销售报表/ });
    if (await exportItem.isVisible().catch(() => false)) {
      await exportItem.click();
    }
    await humanDelay(page);
  });

  test('系统模块：用户/部门/角色/商品/仓库/门店/业务员/分类/单位', async ({ page }) => {
    await login(page);

    const systemPages = [
      { path: '/system/user', addBtn: '新增用户', keyword: '用户' },
      { path: '/system/department', addBtn: '新增部门', keyword: '部门' },
      { path: '/system/role', addBtn: '新增角色', keyword: '角色' },
      { path: '/system/product', addBtn: '新增商品', keyword: '商品' },
      { path: '/system/warehouse', addBtn: '新增仓库', keyword: '仓库' },
      { path: '/system/store', addBtn: '新增门店', keyword: '门店' },
      { path: '/system/agent', addBtn: '新增业务员', keyword: '业务员' },
      { path: '/system/category', addBtn: '新增分类', keyword: '分类' },
      { path: '/system/unit', addBtn: '新增单位', keyword: '单位' },
    ];
    for (const p of systemPages) {
      await page.goto(p.path);
      await humanDelay(page);
      await clickQueryIfExists(page);
      await testAddThenCancel(page, p.addBtn, p.keyword, '取消');
    }
  });

  test('系统模块：角色权限设置、操作日志、企业管理', async ({ page }) => {
    await login(page);

    // 角色：点击权限设置（如有数据）
    await page.goto('/system/role');
    await humanDelay(page);
    const permBtn = page.getByRole('button', { name: '权限设置' }).first();
    if (await permBtn.isVisible().catch(() => false) && !(await permBtn.isDisabled().catch(() => true))) {
      await permBtn.click();
      await expect(page.getByRole('dialog').filter({ hasText: '权限设置' })).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).click();
      await humanDelay(page);
    }

    // 操作日志：查询、详情
    await page.goto('/system/audit-log');
    await humanDelay(page);
    await clickQueryIfExists(page);
    const detailBtn = page.getByRole('button', { name: '详情' }).first();
    if (await detailBtn.isVisible().catch(() => false)) {
      await detailBtn.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '关闭' }).click();
    }
    await humanDelay(page);

    // 企业管理（超管专属）：查询、重置
    await page.goto('/system/tenant-list');
    await humanDelay(page);
    await page.getByRole('button', { name: '查询' }).first().click();
    await humanDelay(page);
    await page.getByRole('button', { name: '重置' }).click();
    await humanDelay(page);
  });

  test('顶部导航：新增单据下拉、导出数据', async ({ page }) => {
    await login(page);
    await page.goto('/dashboard');
    await humanDelay(page);

    // 新增单据下拉：点击展开并选择一项（会跳转并自动打开新增弹窗）
    await page.getByRole('button', { name: '新增单据' }).click();
    await humanDelay(page);
    await page.getByRole('menuitem', { name: '销售订单' }).click();
    await expect(page).toHaveURL(/\/sales\/order/);
    await humanDelay(page);
    // 销售订单页会因 query.action=new 自动打开新增弹窗，需先关闭才能点击导出数据
    const salesDialog = page.getByRole('dialog').filter({ hasText: '销售' });
    if (await salesDialog.isVisible().catch(() => false)) {
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
      await humanDelay(page);
    }
    // 导出数据
    await page.getByRole('button', { name: '导出数据' }).click();
    await humanDelay(page);
  });

  test('列表行操作：查看、编辑（有数据时）', async ({ page }) => {
    await login(page);

    // 供应商列表：如有数据则点查看
    await page.goto('/purchase/supplier');
    await humanDelay(page);
    await clickQueryIfExists(page);
    const viewBtn = page.locator('tbody tr').first().getByRole('button', { name: '查看' });
    if (await viewBtn.isVisible().catch(() => false)) {
      await viewBtn.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '关闭', exact: true }).or(
        page.getByRole('dialog').getByRole('button', { name: '取消' })
      ).first().click();
    }
    await humanDelay(page);

    // 商品列表：如有数据则点编辑
    await page.goto('/system/product');
    await humanDelay(page);
    await clickQueryIfExists(page);
    const editBtn = page.locator('tbody tr').first().getByRole('button', { name: '编辑' });
    if (await editBtn.isVisible().catch(() => false)) {
      await editBtn.click();
      await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5000 });
      await page.getByRole('dialog').getByRole('button', { name: '取消' }).first().click();
    }
  });

  test('盘点明细：明细、保存明细、关闭', async ({ page }) => {
    await login(page);
    await page.goto('/inventory/count');
    await humanDelay(page);
    await clickQueryIfExists(page);

    const detailBtn = page.getByRole('button', { name: '明细' }).first();
    if (await detailBtn.isVisible().catch(() => false)) {
      await detailBtn.click();
      await expect(page.getByRole('dialog').filter({ hasText: '盘点明细' })).toBeVisible({ timeout: 5000 });
      await humanDelay(page);
      // 弹窗有 header 关闭按钮(关闭此对话框)和 footer 关闭按钮，用 exact 匹配 footer
      await page.getByRole('dialog').filter({ hasText: '盘点明细' }).getByRole('button', { name: '关闭', exact: true }).click();
    }
  });
});
