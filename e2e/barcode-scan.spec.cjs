// @ts-check
/**
 * PDA 扫码功能 E2E 测试
 * 模拟人类操作：在扫码输入框中输入条码/编码后按回车，验证商品被正确添加/选中
 */
const { test, expect } = require('playwright/test');

const SUPER_ADMIN = { username: 'admin', password: 'Admin@2026' };

/** 种子数据中的商品：PROD001 条形码 6901234567890 */
const TEST_BARCODE = '6901234567890';
const TEST_CODE = 'PROD001';
const TEST_PRODUCT_NAME = '智能手机';

function humanDelay(page) {
  return page.waitForTimeout(200 + Math.floor(Math.random() * 200));
}

/** 模拟 PDA 扫码：在输入框中输入条码并回车 */
async function simulateBarcodeScan(page, barcodeInput, barcode = TEST_BARCODE) {
  await barcodeInput.click();
  await humanDelay(page);
  await barcodeInput.fill(barcode);
  await humanDelay(page);
  await barcodeInput.press('Enter');
  await page.waitForTimeout(500); // 等待 API 请求完成
}

/** 在弹窗中通过键盘选择 el-select 第一项，选完后关闭下拉避免遮挡下一个 select
 * @param options.force - 为 true 时跳过 scrollIntoViewIfNeeded，使用 force 点击，用于被前一个下拉遮挡的 select
 */
async function selectFirstOption(page, selectLocator, options = {}) {
  const timeout = options.timeout ?? 15000;
  if (!options.force) {
    await selectLocator.scrollIntoViewIfNeeded({ timeout });
    await selectLocator.click();
  } else {
    await selectLocator.click({ force: true, timeout });
  }
  await page.waitForTimeout(1000); // 等待下拉展开及异步选项加载（供应商/仓库等）
  await page.keyboard.press('ArrowDown');
  await page.waitForTimeout(400);
  await page.keyboard.press('Enter');
  await page.waitForTimeout(200);
  await page.keyboard.press('Escape'); // 关闭 popper，避免「无数据」浮层遮挡下一个 select
  await page.waitForTimeout(300); // 等待 popper 完全关闭后再操作下一个 select
  await humanDelay(page);
}

test.describe('PDA 扫码功能 E2E 测试', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('请输入用户名或邮箱').fill(SUPER_ADMIN.username);
    await page.getByPlaceholder('请输入密码').fill(SUPER_ADMIN.password);
    await page.getByRole('button', { name: '登录' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.getByRole('heading', { name: '首页仪表盘' })).toBeVisible({ timeout: 10000 });
    await humanDelay(page);
  });

  test('1. 盘点明细：扫码添加商品并累加数量', async ({ page }) => {
    await page.goto('/inventory/count');
    await humanDelay(page);

    // 新增盘点单
    await page.getByRole('button', { name: '新增盘点单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '盘点' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    await page.getByRole('dialog').locator('.el-select').first().click();
    await humanDelay(page);
    await page.getByRole('listbox').getByText(/仓库|默认/).first().click();
    await humanDelay(page);

    await page.getByRole('dialog').getByRole('button', { name: '确定' }).click();
    await expect(page.getByText('新增成功')).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    // 点击第一行的「明细」
    await page.getByRole('button', { name: '明细' }).first().click();
    await expect(page.getByRole('dialog').filter({ hasText: '盘点明细' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const scanInput = page.getByPlaceholder('扫码添加商品');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    // 模拟扫码：输入条码 + 回车
    await simulateBarcodeScan(page, scanInput);

    // 验证表格中出现商品（智能手机）
    await expect(page.getByRole('dialog').getByText(TEST_PRODUCT_NAME).first()).toBeVisible({ timeout: 5000 });

    // 再扫一次同商品，实盘数量应累加为 2
    await simulateBarcodeScan(page, scanInput);
    const rowWithProduct = page.getByRole('dialog').locator('tr').filter({ hasText: TEST_PRODUCT_NAME });
    await expect(rowWithProduct.locator('.el-input-number input')).toHaveValue(/^2\.?0*$/, { timeout: 3000 });
  });

  test('2. 库存调整：扫码选择商品', async ({ page }) => {
    await page.goto('/inventory/adjustment');
    await humanDelay(page);

    await page.getByRole('button', { name: '新增调整' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '新增调整' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const scanInput = page.getByPlaceholder('扫码');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    await simulateBarcodeScan(page, scanInput);

    // 验证商品已被选中（el-select 显示商品名称）
    await expect(page.getByRole('dialog').filter({ hasText: TEST_PRODUCT_NAME }).first()).toBeVisible({ timeout: 5000 });
  });

  test('3. 库存调拨：扫码选择商品', async ({ page }) => {
    await page.goto('/inventory/transfer');
    await humanDelay(page);

    await page.getByRole('button', { name: '新增调拨单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '新增调拨单' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const scanInput = page.getByPlaceholder('扫码');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    await simulateBarcodeScan(page, scanInput);

    await expect(page.getByRole('dialog').filter({ hasText: TEST_PRODUCT_NAME }).first()).toBeVisible({ timeout: 5000 });
  });

  test('4. 入库记录：扫码选择商品', async ({ page }) => {
    await page.goto('/purchase/inbound');
    await humanDelay(page);

    await page.getByRole('button', { name: '新增入库单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '入库' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const scanInput = page.getByPlaceholder('扫码');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    await simulateBarcodeScan(page, scanInput);

    await expect(page.getByRole('dialog').filter({ hasText: TEST_PRODUCT_NAME }).first()).toBeVisible({ timeout: 5000 });
  });

  test('5. 出库记录：扫码选择商品', async ({ page }) => {
    await page.goto('/sales/outbound');
    await humanDelay(page);

    await page.getByRole('button', { name: '新增出库单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '出库' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const scanInput = page.getByPlaceholder('扫码');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    await simulateBarcodeScan(page, scanInput);

    await expect(page.getByRole('dialog').filter({ hasText: TEST_PRODUCT_NAME }).first()).toBeVisible({ timeout: 5000 });
  });

  test('6. 采购订单：扫码添加商品明细', async ({ page }) => {
    await page.goto('/purchase/order');
    await humanDelay(page);

    await page.getByRole('button', { name: '新增采购单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '采购' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const formDialog = page.getByRole('dialog').filter({ hasText: '新增采购订单' });
    await expect(formDialog).toBeVisible({ timeout: 5000 });

    // 先选择供应商和仓库（必填）- 按 label 定位，避免 nth 因 DOM 顺序或遮挡导致超时
    const supplierSelect = formDialog.locator('.el-form-item').filter({ hasText: '供应商' }).locator('.el-select');
    const warehouseSelect = formDialog.locator('.el-form-item').filter({ hasText: '仓库' }).locator('.el-select');
    await expect(supplierSelect).toBeVisible({ timeout: 10000 });
    await expect(warehouseSelect).toBeVisible({ timeout: 10000 });
    await selectFirstOption(page, supplierSelect);
    // 仓库 select 可能被前一个下拉的 popper 遮挡，用 force 点击避免 scrollIntoViewIfNeeded 超时
    await selectFirstOption(page, warehouseSelect, { timeout: 20000, force: true });

    const scanInput = formDialog.getByPlaceholder('扫码添加商品');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    const lookupDone = page.waitForResponse(
      (r) => r.url().includes('products/lookup') && r.request().method() === 'GET' && r.status() === 200,
      { timeout: 15000 }
    );
    await simulateBarcodeScan(page, scanInput);
    await lookupDone;
    await page.waitForTimeout(1500); // 等待 Vue 更新：扫码直接添加一个商品，表格只有一行

    const itemsTable = formDialog.locator('.el-table').first();
    await expect(itemsTable.locator('tbody tr').nth(0).getByText(TEST_PRODUCT_NAME)).toBeVisible({ timeout: 10000 });
  });

  test('7. 销售订单：扫码添加商品明细', async ({ page }) => {
    await page.goto('/sales/order');
    await humanDelay(page);

    await page.getByRole('button', { name: '新增销售单' }).click();
    await expect(page.getByRole('dialog').filter({ hasText: '销售' })).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const formDialog = page.getByRole('dialog').filter({ hasText: '新增销售订单' });
    await expect(formDialog).toBeVisible({ timeout: 5000 });

    // 选择客户和仓库 - 按 label 定位，避免 nth 因 DOM 顺序或遮挡导致超时
    const customerSelect = formDialog.locator('.el-form-item').filter({ hasText: '客户' }).locator('.el-select');
    const warehouseSelect = formDialog.locator('.el-form-item').filter({ hasText: '仓库' }).locator('.el-select');
    await expect(customerSelect).toBeVisible({ timeout: 10000 });
    await expect(warehouseSelect).toBeVisible({ timeout: 10000 });
    await selectFirstOption(page, customerSelect);
    await page.getByRole('listbox').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => { });
    await page.waitForTimeout(400);
    await selectFirstOption(page, warehouseSelect, { timeout: 20000 });

    const scanInput = formDialog.getByPlaceholder('扫码添加商品');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    const lookupDone = page.waitForResponse(
      (r) => r.url().includes('products/lookup') && r.request().method() === 'GET' && r.status() === 200,
      { timeout: 15000 }
    );
    await simulateBarcodeScan(page, scanInput);
    await lookupDone;
    await page.waitForTimeout(1500); // 等待 Vue 更新 products + 表格行

    // 扫码会填充初始空行，故只有一行（nth(0)）
    const itemsTable = formDialog.locator('.el-table').first();
    await expect(itemsTable.locator('tbody tr').nth(0).getByText(TEST_PRODUCT_NAME)).toBeVisible({ timeout: 10000 });
  });

  test('8. 按商品编码扫码（code 查询）', async ({ page }) => {
    await page.goto('/inventory/adjustment');
    await humanDelay(page);

    await page.getByRole('button', { name: '新增调整' }).click();
    const dialog = page.getByRole('dialog').filter({ hasText: '新增调整' });
    await expect(dialog).toBeVisible({ timeout: 5000 });
    await humanDelay(page);

    const scanInput = dialog.getByPlaceholder('扫码');
    await expect(scanInput).toBeVisible({ timeout: 5000 });

    const lookupDone = page.waitForResponse(
      (r) => r.url().includes('products/lookup') && r.request().method() === 'GET' && r.status() === 200,
      { timeout: 15000 }
    );
    await simulateBarcodeScan(page, scanInput, TEST_CODE);
    await lookupDone;
    await page.waitForTimeout(800); // 等待 Vue 更新 el-select 显示

    await expect(dialog.getByText(TEST_PRODUCT_NAME)).toBeVisible({ timeout: 8000 });
  });
});
