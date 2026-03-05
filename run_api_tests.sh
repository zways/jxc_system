#!/bin/bash
# 超管 API 回归测试 - 重新测试一遍
set -e
BASE="http://127.0.0.1:8000/api/v1"
cd "$(dirname "$0")"

echo ">>> 1. 登录"
LOGIN=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d '{"username":"admin","password":"Admin@2026"}')
TOKEN=$(echo "$LOGIN" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
if [ -z "$TOKEN" ]; then echo "FAIL: 登录失败"; exit 1; fi
echo "PASS: 登录成功"

echo ">>> 2. 健康检查"
curl -s "$BASE/health" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 3. auth/me"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/auth/me" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 4. 用户列表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/users?per_page=5" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 5. 角色列表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/roles?per_page=5" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 6. 权限列表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/permissions?per_page=10" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 7. 部门/门店/仓库/单位"
for path in departments stores warehouses units; do
  curl -s -H "Authorization: Bearer $TOKEN" "$BASE/$path?per_page=2" | grep -q '"success":true' && echo "  $path PASS" || echo "  $path FAIL"
done

echo ">>> 8. 商品/分类/业务员"
for path in products product-categories business-agents; do
  curl -s -H "Authorization: Bearer $TOKEN" "$BASE/$path?per_page=2" | grep -q '"success":true' && echo "  $path PASS" || echo "  $path FAIL"
done

echo ">>> 9. 供应商/客户"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/suppliers?per_page=2" | grep -q '"success":true' && echo "  suppliers PASS" || echo "  suppliers FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/customers?per_page=2" | grep -q '"success":true' && echo "  customers PASS" || echo "  customers FAIL"

echo ">>> 10. 采购订单 列表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/purchase-orders?per_page=2" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 11. 采购订单 创建"
CRE_PO=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders" -d '{"supplier_id":1,"warehouse_id":1,"items":[{"product_id":1,"quantity":2,"unit_price":100}],"notes":"retest"}')
echo "$CRE_PO" | grep -q '"success":true' && echo "PASS" || echo "FAIL: $CRE_PO"

echo ">>> 12. 销售订单 列表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/sales-orders?per_page=2" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 13. 销售订单 创建"
CRE_SO=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/sales-orders" -d '{"customer_id":1,"warehouse_id":1,"items":[{"product_id":1,"quantity":1,"unit_price":200}],"notes":"retest"}')
echo "$CRE_SO" | grep -q '"success":true' && echo "PASS" || echo "FAIL"

echo ">>> 14. 库存流水与汇总"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-transactions?per_page=2" | grep -q '"success":true' && echo "  transactions PASS" || echo "  transactions FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/inventory-transactions/stock-summary" | grep -q '"success":true' && echo "  stock-summary PASS" || echo "  stock-summary FAIL"

echo ">>> 15. 应收/应付/财务流水"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-receivable?per_page=2" | grep -q '"success":true' && echo "  receivable PASS" || echo "  receivable FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/accounts-payable?per_page=2" | grep -q '"success":true' && echo "  payable PASS" || echo "  payable FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/financial-transactions?per_page=2" | grep -q '"success":true' && echo "  financial PASS" || echo "  financial FAIL"

echo ">>> 16. 报表"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/overview" | grep -q '"success":true' && echo "  overview PASS" || echo "  overview FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/sales?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && echo "  sales PASS" || echo "  sales FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/purchase?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && echo "  purchase PASS" || echo "  purchase FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/inventory" | grep -q '"success":true' && echo "  inventory PASS" || echo "  inventory FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/reports/finance?start_date=2026-01-01&end_date=2026-12-31" | grep -q '"success":true' && echo "  finance PASS" || echo "  finance FAIL"

echo ">>> 17. 审计日志与企业"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/audit-logs?per_page=2" | grep -q '"success":true' && echo "  audit-logs PASS" || echo "  audit-logs FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/tenant/list" | grep -q '"success":true' && echo "  tenant/list PASS" || echo "  tenant/list FAIL"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/tenant/current" | grep -q '"success":true' && echo "  tenant/current PASS" || echo "  tenant/current FAIL"

echo ">>> 18. 采购收货 (使用刚创建的采购单)"
PO_ID=$(echo "$CRE_PO" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$PO_ID" ]; then
  curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE/purchase-orders/$PO_ID/receive" | grep -q '"success":true' && echo "PASS" || echo "FAIL"
else
  echo "SKIP (no PO id)"
fi

echo ">>> 19. 销售发货 (使用刚创建的销售单)"
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
echo "========== 重新测试完成 =========="
