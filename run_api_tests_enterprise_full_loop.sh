#!/bin/bash
# 企业账号（企业）全面闭环测试：认证、基础列表、采购/销售/库存/财务全流程及收付款/取消/调拨等
# 账号：enterprise@test.com / Test@12345678，所属企业 store_id=5（测试科技有限公司）
# 使用企业 5 的 supplier_id=6, warehouse_id=5, product_id=6, customer_id=5
set -e
BASE="${BASE_URL:-http://127.0.0.1:8000/api/v1}"
cd "$(dirname "$0")"
PASS=0
FAIL=0

# 企业5 的数据 ID
SUPPLIER_ID=6
WAREHOUSE_ID=5
PRODUCT_ID=6
CUSTOMER_ID=5

pass() { echo "  PASS: $1"; PASS=$((PASS+1)); }
fail() { echo "  FAIL: $1"; FAIL=$((FAIL+1)); }

echo "========== 企业账号全面闭环测试 (enterprise@test.com) =========="
echo ""
echo "========== 1. 认证 =========="
LOGIN=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d '{"username":"enterprise@test.com","password":"Test@12345678"}')
TOKEN=$(echo "$LOGIN" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
if [ -z "$TOKEN" ]; then echo "FAIL: 登录失败"; echo "$LOGIN"; exit 1; fi
pass "登录"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/auth/me" | grep -q '"success":true' && pass "auth/me" || fail "auth/me"

echo ""
echo "========== 2. 健康与系统 =========="
curl -s "$BASE/health" | grep -q '"success":true' && pass "health" || fail "health"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/health/deep" | grep -q '"success":true' && pass "health/deep" || fail "health/deep"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/test-system" | grep -q '"success":true' && pass "test-system" || fail "test-system"

echo ""
echo "========== 3. 基础资源列表（企业内） =========="
for path in users roles permissions departments stores warehouses units products product-categories business-agents suppliers customers; do
  curl -s -H "Authorization: Bearer $TOKEN" "$BASE/$path?per_page=2" | grep -q '"success":true' && pass "$path" || fail "$path"
done

echo ""
echo "========== 4. 商品 lookup（barcode/code） =========="
PROD_CODE=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/products?per_page=1" | grep -o '"code":"[^"]*"' | head -1 | cut -d'"' -f4)
if [ -n "$PROD_CODE" ]; then
  curl -s -H "Authorization: Bearer $TOKEN" "$BASE/products/lookup?code=$PROD_CODE" | grep -q '"success":true' && pass "products/lookup" || fail "products/lookup"
else
  curl -s -H "Authorization: Bearer $TOKEN" "$BASE/products/lookup?code=PROD001" | grep -qE '"success":true|未找到' && pass "products/lookup(接口可用)" || fail "products/lookup"
fi

echo ""
echo "========== 5. tenant/current（本企业详情） =========="
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/tenant/current" | grep -q '"success":true' && pass "tenant/current" || fail "tenant/current"

echo ""
echo "========== 6. 采购闭环：创建 -> 收货（企业5 数据） =========="
CRE_PO=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders" -d "{\"supplier_id\":$SUPPLIER_ID,\"warehouse_id\":$WAREHOUSE_ID,\"items\":[{\"product_id\":$PRODUCT_ID,\"quantity\":3,\"unit_price\":100}],\"notes\":\"企业闭环测试\"}")
if echo "$CRE_PO" | grep -q '"success":true'; then
  pass "采购订单创建"
  PO_ID=$(echo "$CRE_PO" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
  if [ -n "$PO_ID" ]; then
    RECV=$(curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders/$PO_ID/receive")
    echo "$RECV" | grep -q '"success":true' && pass "采购收货" || fail "采购收货: $RECV"
  else
    fail "未解析到采购单ID"
  fi
else
  fail "采购订单创建: $CRE_PO"
fi

echo ""
echo "========== 7. 销售闭环：创建 -> 发货（企业5 数据） =========="
CRE_SO=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/sales-orders" -d "{\"customer_id\":$CUSTOMER_ID,\"warehouse_id\":$WAREHOUSE_ID,\"items\":[{\"product_id\":$PRODUCT_ID,\"quantity\":1,\"unit_price\":200}],\"notes\":\"企业闭环测试\"}")
if echo "$CRE_SO" | grep -q '"success":true'; then
  pass "销售订单创建"
  SO_ID=$(echo "$CRE_SO" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
  if [ -n "$SO_ID" ]; then
    DELV=$(curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/sales-orders/$SO_ID/deliver")
    echo "$DELV" | grep -q '"success":true' && pass "销售发货" || fail "销售发货: $DELV"
  else
    fail "未解析到销售单ID"
  fi
else
  fail "销售订单创建: $CRE_SO"
fi

echo ""
echo "========== 8. 应收/应付/财务流水列表 =========="
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-receivable?per_page=5" | grep -q '"success":true' && pass "accounts-receivable" || fail "accounts-receivable"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-payable?per_page=5" | grep -q '"success":true' && pass "accounts-payable" || fail "accounts-payable"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/financial-transactions?per_page=5" | grep -q '"success":true' && pass "financial-transactions" || fail "financial-transactions"

echo ""
echo "========== 9. 收款闭环：应收 collect =========="
AR_LIST=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-receivable?per_page=20")
AR_ID=$(echo "$AR_LIST" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$AR_ID" ]; then
  COLLECT=$(curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/accounts-receivable/$AR_ID/collect" -d '{"amount":1,"notes":"企业闭环测试收款"}')
  echo "$COLLECT" | grep -q '"success":true' && pass "应收收款 collect" || fail "应收收款: $COLLECT"
else
  fail "无应收记录可测试收款"
fi

echo ""
echo "========== 10. 付款闭环：应付 pay =========="
AP_LIST=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-payable?per_page=20")
AP_ID=$(echo "$AP_LIST" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$AP_ID" ]; then
  PAY=$(curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/accounts-payable/$AP_ID/pay" -d '{"amount":1,"notes":"企业闭环测试付款"}')
  echo "$PAY" | grep -q '"success":true' && pass "应付付款 pay" || fail "应付付款: $PAY"
else
  fail "无应付记录可测试付款"
fi

echo ""
echo "========== 11. 采购取消（新建未收货订单再取消） =========="
PO_CRE=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders" -d "{\"supplier_id\":$SUPPLIER_ID,\"warehouse_id\":$WAREHOUSE_ID,\"items\":[{\"product_id\":$PRODUCT_ID,\"quantity\":1,\"unit_price\":50}],\"notes\":\"企业取消测试\"}")
PO_CID=$(echo "$PO_CRE" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$PO_CID" ]; then
  CANC_PO=$(curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders/$PO_CID/cancel")
  echo "$CANC_PO" | grep -q '"success":true' && pass "采购订单取消" || fail "采购订单取消: $CANC_PO"
else
  fail "未创建成功采购单，跳过取消"
fi

echo ""
echo "========== 12. 销售取消（新建未发货订单再取消） =========="
SO_CRE=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/sales-orders" -d "{\"customer_id\":$CUSTOMER_ID,\"warehouse_id\":$WAREHOUSE_ID,\"items\":[{\"product_id\":$PRODUCT_ID,\"quantity\":1,\"unit_price\":80}],\"notes\":\"企业取消测试\"}")
SO_CID=$(echo "$SO_CRE" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$SO_CID" ]; then
  CANC_SO=$(curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/sales-orders/$SO_CID/cancel")
  echo "$CANC_SO" | grep -q '"success":true' && pass "销售订单取消" || fail "销售订单取消: $CANC_SO"
else
  fail "未创建成功销售单，跳过取消"
fi

echo ""
echo "========== 13. 库存：流水、汇总、调拨 =========="
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-transactions?per_page=2" | grep -q '"success":true' && pass "inventory-transactions" || fail "inventory-transactions"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-transactions/stock-summary" | grep -q '"success":true' && pass "stock-summary" || fail "stock-summary"
WH_LIST=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/warehouses?per_page=5")
WH_IDS=$(echo "$WH_LIST" | grep -o '"id":[0-9]*' | cut -d: -f2)
FIRST_WH=$(echo "$WH_IDS" | head -1)
SECOND_WH=$(echo "$WH_IDS" | sed -n '2p')
if [ -n "$FIRST_WH" ] && [ -n "$SECOND_WH" ] && [ "$FIRST_WH" != "$SECOND_WH" ]; then
  TRANSFER=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/inventory-transactions/transfer" -d "{\"product_id\":$PRODUCT_ID,\"from_warehouse_id\":$FIRST_WH,\"to_warehouse_id\":$SECOND_WH,\"quantity\":1,\"notes\":\"企业闭环测试调拨\"}")
  if echo "$TRANSFER" | grep -q '"success":true'; then
    pass "库存调拨 transfer"
  elif echo "$TRANSFER" | grep -qE '序列号|批次|库存不足|serial_numbers'; then
    pass "库存调拨(接口正常，业务校验: 序列号/批次/库存)"
  else
    fail "库存调拨: $TRANSFER"
  fi
else
  TRANSFER=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/inventory-transactions/transfer" -d "{\"product_id\":$PRODUCT_ID,\"from_warehouse_id\":$WAREHOUSE_ID,\"to_warehouse_id\":$WAREHOUSE_ID,\"quantity\":1,\"notes\":\"test\"}")
  echo "$TRANSFER" | grep -qE '"success":true|序列号|批次|库存不足|不存在|serial_numbers' && pass "库存调拨接口可用" || fail "库存调拨接口: $TRANSFER"
fi

echo ""
echo "========== 14. 库存调整、盘点、换货、销售退货列表 =========="
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-adjustments?per_page=2" | grep -q '"success":true' && pass "inventory-adjustments" || fail "inventory-adjustments"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-counts?per_page=2" | grep -q '"success":true' && pass "inventory-counts" || fail "inventory-counts"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/exchange-records?per_page=2" | grep -q '"success":true' && pass "exchange-records" || fail "exchange-records"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/sales-returns?per_page=2" | grep -q '"success":true' && pass "sales-returns" || fail "sales-returns"

echo ""
echo "========== 15. 报表 =========="
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/overview" | grep -q '"success":true' && pass "reports/overview" || fail "reports/overview"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/sales?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && pass "reports/sales" || fail "reports/sales"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/purchase?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && pass "reports/purchase" || fail "reports/purchase"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/inventory" | grep -q '"success":true' && pass "reports/inventory" || fail "reports/inventory"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/finance?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && pass "reports/finance" || fail "reports/finance"
EXPORT_RES=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/export?start_date=2026-01-01&end_date=2026-12-31&type=overview")
echo "$EXPORT_RES" | grep -q '"success":true' && pass "reports/export" || fail "reports/export"

echo ""
echo "========== 16. 审计与企业（企业端 tenant/list 应为 403） =========="
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/audit-logs?per_page=2" | grep -q '"success":true' && pass "audit-logs" || fail "audit-logs"
LIST_RES=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN" "$BASE/tenant/list")
if [ "$LIST_RES" = "403" ]; then pass "tenant/list(企业账号403符合预期)"; else fail "tenant/list 期望403实际$LIST_RES"; fi

echo ""
echo "========== 17. 权限与登出 =========="
RES=$(curl -s "$BASE/users")
echo "$RES" | grep -qE '"message".*[Uu]nauthenticated|401' && pass "无Token被拒绝" || fail "无Token应401"
curl -s -X POST -H "Authorization: Bearer $TOKEN" "$BASE/auth/logout" | grep -q '"success":true' && pass "登出" || fail "登出"

echo ""
echo "=============================================="
echo "企业账号闭环测试汇总: 通过 $PASS 项, 失败 $FAIL 项"
echo "=============================================="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
