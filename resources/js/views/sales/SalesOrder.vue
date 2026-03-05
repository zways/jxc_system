<template>
    <div class="sales-order-container">
        <div class="page-header">
            <h3>销售订单管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="订单号/客户"
                    style="width: 240px; margin-right: 12px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-select v-model="statusFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="待处理" value="pending" />
                    <el-option label="已确认" value="confirmed" />
                    <el-option label="已发货" value="delivered" />
                    <el-option label="已取消" value="cancelled" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" :icon="Plus" @click="openAdd">新增销售单</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="order_number" label="订单编号" width="140" />
                <el-table-column label="客户" min-width="120">
                    <template #default="{ row }">{{ row.customer?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="order_date" label="订单日期" width="120" />
                <el-table-column prop="total_amount" label="总金额" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.total_amount || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="仓库" width="100">
                    <template #default="{ row }">{{ row.warehouse?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="170" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" link @click="viewDetail(row)">查看</el-button>
                        <el-button size="small" type="primary" link @click="editRow(row)">编辑</el-button>
                        <el-button
                            v-if="row.status !== 'delivered' && row.status !== 'cancelled'"
                            size="small"
                            type="success"
                            link
                            @click="deliverRow(row)"
                        >发货</el-button>
                        <el-button
                            v-if="row.status !== 'cancelled'"
                            size="small"
                            type="warning"
                            link
                            @click="cancelRow(row)"
                        >取消</el-button>
                        <el-button size="small" type="danger" link @click="removeRow(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-container">
                <el-pagination
                    v-model:current-page="pagination.currentPage"
                    v-model:page-size="pagination.pageSize"
                    :page-sizes="[10, 20, 50]"
                    :total="pagination.total"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑销售订单' : '新增销售订单'" width="560px" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
                <el-form-item label="客户" prop="customer_id">
                    <el-select v-model="form.customer_id" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="仓库" prop="warehouse_id">
                    <el-select v-model="form.warehouse_id" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="订单日期" prop="order_date">
                    <el-date-picker v-model="form.order_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>
                <el-form-item label="订单类型" prop="order_type">
                    <el-select v-model="form.order_type" placeholder="请选择" style="width: 100%">
                        <el-option label="零售" value="retail" />
                        <el-option label="批发" value="wholesale" />
                        <el-option label="POS" value="pos" />
                    </el-select>
                </el-form-item>
                <el-form-item label="销售明细" prop="items">
                    <div style="width: 100%">
                        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 10px">
                            <el-button size="small" @click="addItemRow">添加明细</el-button>
                            <BarcodeScanInput ref="barcodeScanRef" :autofocus="false" placeholder="扫码添加商品" size="small" :hint="''" style="width: 240px" @product="onScanProductForOrder" />
                        </div>
                        <el-table :data="form.items" border size="small">
                            <el-table-column label="商品" min-width="180">
                                <template #default="{ row }">
                                    <el-select v-model="row.product_id" placeholder="请选择" filterable style="width: 100%">
                                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                                    </el-select>
                                </template>
                            </el-table-column>
                            <el-table-column label="数量" width="120">
                                <template #default="{ row }">
                                    <el-input-number v-model="row.quantity" :min="0.01" :precision="2" style="width: 100%" />
                                </template>
                            </el-table-column>
                            <el-table-column label="单价" width="140">
                                <template #default="{ row }">
                                    <el-input-number v-model="row.unit_price" :min="0" :precision="2" style="width: 100%" />
                                </template>
                            </el-table-column>
                            <el-table-column label="金额" width="140" align="right">
                                <template #default="{ row }">
                                    ¥{{ Number((row.quantity || 0) * (row.unit_price || 0)).toFixed(2) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="操作" width="90" fixed="right">
                                <template #default="{ $index }">
                                    <el-button size="small" type="danger" link @click="removeItemRow($index)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div style="text-align: right; margin-top: 10px; font-weight: 600">
                            小计：¥{{ Number(calcSubtotal()).toFixed(2) }}
                        </div>
                    </div>
                </el-form-item>
                <el-form-item label="备注" prop="notes">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitForm">确定</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="detailDialogVisible" title="销售订单详情" width="720px" :close-on-click-modal="false">
            <div v-loading="detailLoading" style="min-height: 160px">
                <el-descriptions v-if="detail" :column="2" border>
                    <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
                    <el-descriptions-item label="订单编号">{{ detail.order_number || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="客户">{{ detail.customer?.name || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="仓库">{{ detail.warehouse?.name || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="订单日期">{{ detail.order_date || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="交付日期">{{ detail.delivery_date || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="订单类型">{{ detail.order_type || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="总金额">¥{{ Number(detail.total_amount || 0).toFixed(2) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ statusText(detail.status) }}</el-descriptions-item>
                    <el-descriptions-item label="付款状态">{{ detail.payment_status || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="配送状态">{{ detail.delivery_status || "-" }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatDate(detail.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="备注" :span="2">{{ detail.notes || "-" }}</el-descriptions-item>
                </el-descriptions>
                <el-divider v-if="detail?.items?.length" content-position="left">明细</el-divider>
                <el-table v-if="detail?.items?.length" :data="detail.items" border size="small">
                    <el-table-column label="商品" min-width="180">
                        <template #default="{ row }">{{ row.product?.name || row.product_name || "-" }}</template>
                    </el-table-column>
                    <el-table-column prop="unit" label="单位" width="90" />
                    <el-table-column prop="quantity" label="数量" width="120" align="right" />
                    <el-table-column prop="unit_price" label="单价" width="120" align="right" />
                    <el-table-column prop="line_amount" label="金额" width="120" align="right" />
                </el-table>
                <div v-else style="color: #909399; padding: 8px 0">暂无详情数据</div>
            </div>
            <template #footer>
                <el-button @click="detailDialogVisible = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, onMounted, onActivated, watch, nextTick, defineComponent } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Plus } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";
import BarcodeScanInput from "../../components/BarcodeScanInput.vue";

export default defineComponent({
    name: "SalesOrder",
    components: { BarcodeScanInput },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const loading = ref(false);
        const searchKeyword = ref("");
        const statusFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });
        const dialogVisible = ref(false);
        const isEdit = ref(false);
        const formRef = ref(null);
        const barcodeScanRef = ref(null);
        const customers = ref([]);
        const warehouses = ref([]);
        const products = ref([]);
        const detailDialogVisible = ref(false);
        const detailLoading = ref(false);
        const detail = ref(null);
        const form = reactive({
            id: null,
            customer_id: null,
            warehouse_id: null,
            order_date: "",
            order_type: "retail",
            notes: "",
            items: [],
        });
        const formRules = {
            customer_id: [{ required: true, message: "请选择客户", trigger: "change" }],
            warehouse_id: [{ required: true, message: "请选择仓库", trigger: "change" }],
            order_date: [{ required: true, message: "请选择订单日期", trigger: "change" }],
            items: [{ required: true, message: "请添加销售明细", trigger: "change" }],
        };

        const formatDate = (v) => (v ? String(v).slice(0, 10) : "");
        const statusText = (s) => ({ pending: "待处理", confirmed: "已确认", delivered: "已发货", cancelled: "已取消" }[s] || s);
        const statusTagType = (s) => ({ pending: "warning", confirmed: "primary", delivered: "success", cancelled: "info" }[s] || "info");

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };
        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("sales-orders", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        status: statusFilter.value || undefined,
                    },
                });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data;
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "加载失败");
                list.value = [];
            } finally {
                loading.value = false;
            }
        };

        const loadCustomers = async () => {
            try {
                const res = await window.axios.get("customers", { params: { per_page: 500 } });
                const { list: data } = parsePaginatedResponse(res);
                customers.value = data || [];
            } catch (_) {
                customers.value = [];
            }
        };
        const loadWarehouses = async () => {
            try {
                const res = await window.axios.get("warehouses", { params: { per_page: 500 } });
                const { list: data } = parsePaginatedResponse(res);
                warehouses.value = data || [];
            } catch (_) {
                warehouses.value = [];
            }
        };
        const loadProducts = async () => {
            try {
                const res = await window.axios.get("products", { params: { per_page: 500 } });
                const { list: data } = parsePaginatedResponse(res);
                products.value = data || [];
            } catch (_) {
                products.value = [];
            }
        };

        const addItemRow = () => {
            form.items.push({ product_id: null, quantity: 1, unit_price: 0, notes: "" });
        };
        const onScanProductForOrder = (product) => {
            if (!product?.id) return;
            if (!products.value.find((p) => p.id === product.id)) {
                products.value.push(product);
            }
            const existing = form.items.find((it) => it.product_id === product.id);
            if (existing) {
                existing.quantity = Number(existing.quantity || 0) + 1;
            } else {
                const price = form.order_type === "wholesale" ? product.wholesale_price : product.retail_price;
                const emptyRow = form.items.find((it) => !it.product_id);
                if (emptyRow) {
                    emptyRow.product_id = product.id;
                    emptyRow.quantity = 1;
                    emptyRow.unit_price = Number(price || product.retail_price || 0);
                    emptyRow.notes = "";
                } else {
                    form.items.push({
                        product_id: product.id,
                        quantity: 1,
                        unit_price: Number(price || product.retail_price || 0),
                        notes: "",
                    });
                }
            }
        };
        const removeItemRow = (idx) => {
            form.items.splice(idx, 1);
        };
        const calcSubtotal = () => {
            return (form.items || []).reduce((sum, it) => sum + Number(it.quantity || 0) * Number(it.unit_price || 0), 0);
        };

        const openAdd = () => {
            isEdit.value = false;
            form.id = null;
            form.customer_id = null;
            form.warehouse_id = null;
            form.order_date = new Date().toISOString().slice(0, 10);
            form.order_type = "retail";
            form.notes = "";
            form.items = [];
            addItemRow();
            dialogVisible.value = true;
            loadCustomers();
            loadWarehouses();
            loadProducts();
        };

        const editRow = async (row) => {
            if (!row?.id) return;
            isEdit.value = true;
            dialogVisible.value = true;
            loadCustomers();
            loadWarehouses();
            loadProducts();
            try {
                const res = await window.axios.get(`sales-orders/${row.id}`);
                const d = res.data?.data;
                form.id = d?.id || row.id;
                form.customer_id = d?.customer?.id || d?.customer_id || row.customer_id || null;
                form.warehouse_id = d?.warehouse?.id || d?.warehouse_id || row.warehouse_id || null;
                form.order_date = d?.order_date || new Date().toISOString().slice(0, 10);
                form.order_type = d?.order_type || "retail";
                form.notes = d?.notes || "";
                form.items = (d?.items || []).map((it) => ({
                    product_id: it.product?.id || it.product_id || null,
                    quantity: Number(it.quantity || 0) || 1,
                    unit_price: Number(it.unit_price || 0) || 0,
                    notes: it.notes || "",
                }));
                if (form.items.length === 0) addItemRow();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "加载订单失败");
                dialogVisible.value = false;
            }
        };

        const submitForm = async () => {
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            if (!form.items || form.items.length === 0) {
                ElMessage.warning("请添加销售明细");
                return;
            }
            if (form.items.some((it) => !it.product_id)) {
                ElMessage.warning("请为所有明细选择商品");
                return;
            }
            try {
                const payload = {
                    customer_id: form.customer_id,
                    warehouse_id: form.warehouse_id,
                    order_date: form.order_date,
                    delivery_date: form.order_date,
                    discount: 0,
                    tax_amount: 0,
                    shipping_cost: 0,
                    order_type: form.order_type,
                    ...(!isEdit.value ? { status: "pending", payment_status: "unpaid", delivery_status: "pending" } : {}),
                    notes: form.notes,
                    items: form.items.map((it) => ({
                        product_id: it.product_id,
                        quantity: it.quantity,
                        unit_price: it.unit_price,
                        notes: it.notes || undefined,
                    })),
                };

                if (isEdit.value && form.id) {
                    await window.axios.put(`sales-orders/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("sales-orders", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        const removeRow = async (row) => {
            if (!row?.id) return;
            try {
                await ElMessageBox.confirm(`确定删除销售订单「${row.order_number || row.id}」吗？`, "删除确认", { type: "warning" });
                await window.axios.delete(`sales-orders/${row.id}`);
                ElMessage.success("删除成功");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        const deliverRow = async (row) => {
            if (!row?.id) return;
            try {
                await ElMessageBox.confirm(
                    `确认将销售单「${row.order_number || row.id}」标记为已发货吗？系统将自动生成出库流水与应收（并校验库存）。`,
                    "发货确认",
                    {
                        type: "warning",
                        confirmButtonText: "确认发货",
                        cancelButtonText: "取消",
                    }
                );
                await window.axios.put(`sales-orders/${row.id}/deliver`);
                ElMessage.success("发货成功");
                loadList();
            } catch (e) {
                if (e === "cancel" || e?.message === "cancel") return;
                // 库存不足等校验会走 422，errors.stock 里有详细信息
                const msg =
                    e.response?.data?.message ||
                    (e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
                    "发货失败";
                ElMessage.error(msg);
            }
        };

        const cancelRow = async (row) => {
            if (!row?.id) return;
            try {
                await ElMessageBox.confirm(
                    `确定取消销售订单「${row.order_number || row.id}」吗？\n- 若已发货：将回滚出库流水与应收（如已收款需先作废收款流水）`,
                    "取消确认",
                    { type: "warning", confirmButtonText: "确定取消", cancelButtonText: "取消" }
                );
                await window.axios.put(`sales-orders/${row.id}/cancel`);
                ElMessage.success("取消成功");
                loadList();
            } catch (e) {
                if (e === "cancel" || e?.message === "cancel") return;
                const msg =
                    e.response?.data?.message ||
                    (e.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
                    "取消失败";
                ElMessage.error(msg);
            }
        };

        const viewDetail = (row) => {
            if (!row?.id) return;
            detailDialogVisible.value = true;
            detail.value = null;
            detailLoading.value = true;
            window.axios
                .get(`sales-orders/${row.id}`)
                .then((res) => {
                    detail.value = res.data?.data || null;
                })
                .catch((e) => {
                    ElMessage.error(e.response?.data?.message || "加载详情失败");
                    detail.value = null;
                })
                .finally(() => {
                    detailLoading.value = false;
                });
        };

        // 检查路由参数并打开新增弹窗
        const checkNewAction = () => {
            if (route.query.action === "new") {
                openAdd();
                router.replace({ path: route.path, query: {} });
            }
        };

        onMounted(() => {
            loadList();
            checkNewAction();
        });

        // keep-alive 缓存组件重新激活时触发
        onActivated(() => {
            checkNewAction();
        });

        // 监听路由变化
        watch(
            () => route.query.action,
            (newAction) => {
                if (newAction === "new") {
                    openAdd();
                    router.replace({ path: route.path, query: {} });
                }
            }
        );
        watch(dialogVisible, (visible) => {
            if (visible) nextTick(() => barcodeScanRef.value?.focus?.());
        });

        return {
            Plus,
            loading,
            searchKeyword,
            statusFilter,
            list,
            pagination,
            dialogVisible,
            isEdit,
            form,
            formRules,
            formRef,
            barcodeScanRef,
            customers,
            warehouses,
            products,
            detailDialogVisible,
            detailLoading,
            detail,
            formatDate,
            statusText,
            statusTagType,
            handleSearch,
            loadList,
            openAdd,
            editRow,
            removeRow,
            submitForm,
            deliverRow,
            cancelRow,
            viewDetail,
            addItemRow,
            removeItemRow,
            calcSubtotal,
            onScanProductForOrder,
        };
    },
});
</script>

<style scoped>
.sales-order-container {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.data-card {
    min-height: 400px;
}
.pagination-container {
    margin-top: 16px;
    text-align: right;
}
</style>
