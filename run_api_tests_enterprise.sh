#!/bin/bash
# 企业账号（企业）API 回归测试
# 账号：admin_g3knq5 / Test@12345678，所属企业 store_id=5（测试科技有限公司）
# 使用企业 5 的 supplier_id=6, warehouse_id=5, product_id=6, customer_id=5
set -e
BASE="${BASE_URL:-http://127.0.0.1:8000/api/v1}"
cd "$(dirname "$0")"

# 企业5 的数据 ID（从 jxc_system_structure_and_data.sql）
SUPPLIER_ID=6
WAREHOUSE_ID=5
PRODUCT_ID=6
CUSTOMER_ID=5

echo "========== 企业账号 API 测试 (enterprise@test.com) =========="
echo ">>> 1. 登录（企业管理员）"
LOGIN=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d '{"username":"enterprise@test.com","password":"Test@12345678"}')
TOKEN=$(echo "$LOGIN" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
if [ -z "$TOKEN" ]; then echo "FAIL: 登录失败"; echo "$LOGIN"; exit 1; fi
echo "PASS: 登录成功"

echo ">>> 2. 健康检查"
curl -s "$BASE/health" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 3. auth/me（应返回当前用户与企业信息）"
ME=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/auth/me")
echo "$ME" | grep -q '"success":true' && echo "PASS" || echo "FAIL"
echo "$ME" | grep -q '"store"' && echo "  (含 store 信息) PASS" || echo "  (含 store 信息) CHECK"

echo ">>> 4. tenant/current（企业端应返回本企业详情，非平台概览）"
CURRENT=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE/tenant/current")
echo "$CURRENT" | grep -q '"success":true' && echo "PASS" || echo "FAIL"
echo "$CURRENT" | grep -q '"store_code"' && echo "  (含 store_code) PASS" || echo "  (含 store_code) CHECK"

echo ">>> 5. tenant/list（企业账号应 403，仅超管可看）"
LIST_RES=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN" "$BASE/tenant/list")
if [ "$LIST_RES" = "403" ]; then echo "PASS (403 符合预期)"; else echo "CHECK: 期望 403，实际 $LIST_RES"; fi

echo ">>> 6. 用户/角色/权限列表（企业内）"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/users?per_page=5" | grep -q '"success":true' && echo "  users PASS" || echo "  users FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/roles?per_page=5" | grep -q '"success":true' && echo "  roles PASS" || echo "  roles FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/permissions?per_page=10" | grep -q '"success":true' && echo "  permissions PASS" || echo "  permissions FAIL"

echo ">>> 7. 部门/门店/仓库/单位（企业内）"
for path in departments stores warehouses units; do
  curl -s -H "Authorization: Bearer $TOKEN" "$BASE/$path?per_page=2" | grep -q '"success":true' && echo "  $path PASS" || echo "  $path FAIL"
done

echo ">>> 8. 商品/分类/业务员（企业内）"
for path in products product-categories business-agents; do
  curl -s -H "Authorization: Bearer $TOKEN" "$BASE/$path?per_page=2" | grep -q '"success":true' && echo "  $path PASS" || echo "  $path FAIL"
done

echo ">>> 9. 供应商/客户（企业内）"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/suppliers?per_page=2" | grep -q '"success":true' && echo "  suppliers PASS" || echo "  suppliers FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/customers?per_page=2" | grep -q '"success":true' && echo "  customers PASS" || echo "  customers FAIL"

echo ">>> 10. 采购订单 列表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/purchase-orders?per_page=2" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 11. 采购订单 创建（使用企业5 的 supplier/warehouse/product）"
CRE_PO=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders" -d "{\"supplier_id\":$SUPPLIER_ID,\"warehouse_id\":$WAREHOUSE_ID,\"items\":[{\"product_id\":$PRODUCT_ID,\"quantity\":2,\"unit_price\":100}],\"notes\":\"企业端测试采购\"}")
echo "$CRE_PO" | grep -q '"success":true' && echo "PASS" || echo "FAIL: $CRE_PO"

echo ">>> 12. 销售订单 列表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/sales-orders?per_page=2" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 13. 销售订单 创建（使用企业5 的 customer/warehouse/product）"
CRE_SO=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/sales-orders" -d "{\"customer_id\":$CUSTOMER_ID,\"warehouse_id\":$WAREHOUSE_ID,\"items\":[{\"product_id\":$PRODUCT_ID,\"quantity\":1,\"unit_price\":200}],\"notes\":\"企业端测试销售\"}")
echo "$CRE_SO" | grep -q '"success":true' && echo "PASS" || echo "FAIL: $CRE_SO"

echo ">>> 14. 库存流水与汇总"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-transactions?per_page=2" | grep -q '"success":true' && echo "  transactions PASS" || echo "  transactions FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-transactions/stock-summary" | grep -q '"success":true' && echo "  stock-summary PASS" || echo "  stock-summary FAIL"

echo ">>> 15. 应收/应付/财务流水"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-receivable?per_page=2" | grep -q '"success":true' && echo "  receivable PASS" || echo "  receivable FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-payable?per_page=2" | grep -q '"success":true' && echo "  payable PASS" || echo "  payable FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/financial-transactions?per_page=2" | grep -q '"success":true' && echo "  financial PASS" || echo "  financial FAIL"

echo ">>> 16. 报表（企业端仅本企业数据）"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/overview" | grep -q '"success":true' && echo "  overview PASS" || echo "  overview FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/sales?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && echo "  sales PASS" || echo "  sales FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/purchase?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && echo "  purchase PASS" || echo "  purchase FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/inventory" | grep -q '"success":true' && echo "  inventory PASS" || echo "  inventory FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/finance?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && echo "  finance PASS" || echo "  finance FAIL"

echo ">>> 17. 审计日志（企业内）"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/audit-logs?per_page=2" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 18. 采购收货（使用刚创建的采购单）"
PO_ID=$(echo "$CRE_PO" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$PO_ID" ]; then
  curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders/$PO_ID/receive" | grep -q '"success":true' && echo "PASS" || echo "FAIL"
else
  echo "SKIP (no PO id)"
fi

echo ">>> 19. 销售发货（使用刚创建的销售单）"
SO_ID=$(echo "$CRE_SO" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$SO_ID" ]; then
  curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/sales-orders/$SO_ID/deliver" | grep -q '"success":true' && echo "PASS" || echo "FAIL"
else
  echo "SKIP (no SO id)"
fi

echo ">>> 20. 无 Token 访问应被拒绝"
RES=$(curl -s "$BASE/users")
echo "$RES" | grep -qE '"message".*[Uu]nauthenticated|401' && echo "PASS" || echo "CHECK: $RES"

echo ">>> 21. 登出"
curl -s -X POST -H "Authorization: Bearer $TOKEN" "$BASE/auth/logout" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ""
echo "========== 企业账号 API 测试完成 =========="
