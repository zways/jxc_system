<template>
    <div class="transfer-management-container">
        <div class="page-header">
            <h3>库存调拨管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="流水号/商品"
                    style="width: 240px; margin-right: 12px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && handleSearch()"
                />
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" :icon="Plus" @click="openAdd">新增调拨单</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="transaction_number" label="流水号" width="150" />
                <el-table-column label="商品" min-width="140">
                    <template #default="{ row }">{{ row.product?.name || "-" }}</template>
                </el-table-column>
                <el-table-column label="仓库" width="120">
                    <template #default="{ row }">{{ row.warehouse?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="transaction_type" label="类型" width="90">
                    <template #default="{ row }">
                        {{
                            row.reference_type === "transfer"
                                ? (Number(row.quantity || 0) < 0 ? "调拨出库" : "调拨入库")
                                : row.transaction_type === "transfer"
                                  ? "调拨"
                                  : row.transaction_type
                        }}
                    </template>
                </el-table-column>
                <el-table-column prop="quantity" label="数量" width="90" align="right" />
                <el-table-column prop="unit_cost" label="单位成本" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.unit_cost || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="批次号" width="110" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.batch_number || "-" }}</template>
                </el-table-column>
                <el-table-column label="序列号" width="110" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.serial_number || "-" }}</template>
                </el-table-column>
                <el-table-column prop="reference_document" label="关联单据" width="120" show-overflow-tooltip />
                <el-table-column prop="notes" label="备注" min-width="120" show-overflow-tooltip />
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
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
        <el-dialog v-model="dialogVisible" title="新增调拨单" width="560px" :trap-focus="false" @open="formRef?.clearValidate?.()" @opened="onTransferDialogOpened">
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
                <el-form-item label="商品" prop="product_id">
                    <div style="display: flex; gap: 8px; align-items: flex-start; width: 100%">
                        <el-select v-model="form.product_id" placeholder="请选择或扫码" filterable style="flex: 1">
                            <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                        </el-select>
                        <BarcodeScanInput ref="barcodeScanRef" :autofocus="false" placeholder="扫码" size="default" :hint="''" style="width: 200px" @product="(p) => (form.product_id = p.id)" />
                    </div>
                </el-form-item>
                <el-form-item label="调出仓库" prop="from_warehouse_id">
                    <el-select v-model="form.from_warehouse_id" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="调入仓库" prop="to_warehouse_id">
                    <el-select v-model="form.to_warehouse_id" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="数量" prop="quantity">
                    <el-input-number v-model="form.quantity" :min="1" style="width: 100%" @change="onQuantityChange" />
                </el-form-item>
                <el-form-item v-if="selectedProduct?.track_batch" label="批次">
                    <el-select v-model="form.batch_number" placeholder="请选择批次" filterable allow-create default-first-option style="width: 100%" @change="onBatchSelect">
                        <el-option v-for="b in batchesAvailable" :key="b.batch_number" :label="`${b.batch_number} (库存 ${b.quantity})`" :value="b.batch_number">
                            <span>{{ b.batch_number }}</span>
                            <span style="color: var(--el-text-color-secondary); margin-left: 8px">库存 {{ b.quantity }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item v-if="selectedProduct?.track_serial" label="调拨序列号">
                    <el-select v-model="form.serial_numbers" multiple placeholder="请选择要调拨的序列号" style="width: 100%" :max-collapse-tags="3">
                        <el-option v-for="s in serialsAvailable" :key="s.serial_number" :label="s.serial_number" :value="s.serial_number" />
                    </el-select>
                </el-form-item>
                <el-form-item label="单位成本" prop="unit_cost">
                    <el-input-number v-model="form.unit_cost" :min="0" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item label="备注" prop="notes">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitAdd">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, onActivated, watch, nextTick, defineComponent } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Plus } from "@element-plus/icons-vue";
import { ElMessage } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";
import BarcodeScanInput from "../../components/BarcodeScanInput.vue";

export default defineComponent({
    name: "TransferManagement",
    components: { BarcodeScanInput },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const loading = ref(false);
        const searchKeyword = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });
        const dialogVisible = ref(false);
        const formRef = ref(null);
        const products = ref([]);
        const warehouses = ref([]);
        const batchesAvailable = ref([]);
        const serialsAvailable = ref([]);
        const form = reactive({
            product_id: null,
            from_warehouse_id: null,
            to_warehouse_id: null,
            quantity: 1,
            unit_cost: 0,
            batch_number: "",
            production_date: null,
            expiry_date: null,
            serial_numbers: [],
            notes: "",
        });
        const selectedProduct = computed(() => products.value.find((p) => p.id === form.product_id) || null);
        const selectedBatch = computed(() => batchesAvailable.value.find((b) => b.batch_number === form.batch_number) || null);
        const formRules = {
            product_id: [{ required: true, message: "请选择商品", trigger: "change" }],
            from_warehouse_id: [{ required: true, message: "请选择调出仓库", trigger: "change" }],
            to_warehouse_id: [{ required: true, message: "请选择调入仓库", trigger: "change" }],
            quantity: [{ required: true, message: "请输入数量", trigger: "blur" }],
        };
        const loadBatchesAvailable = async () => {
            if (!form.product_id || !form.from_warehouse_id) {
                batchesAvailable.value = [];
                return;
            }
            try {
                const res = await window.axios.get("inventory-transactions/batches-available", {
                    params: { product_id: form.product_id, warehouse_id: form.from_warehouse_id },
                });
                batchesAvailable.value = res.data?.data || [];
            } catch {
                batchesAvailable.value = [];
            }
        };
        const loadSerialsAvailable = async () => {
            if (!form.product_id || !form.from_warehouse_id) {
                serialsAvailable.value = [];
                return;
            }
            try {
                const res = await window.axios.get("inventory-transactions/serials-available", {
                    params: { product_id: form.product_id, warehouse_id: form.from_warehouse_id },
                });
                serialsAvailable.value = res.data?.data || [];
            } catch {
                serialsAvailable.value = [];
            }
        };
        const onBatchSelect = () => {
            const b = selectedBatch.value;
            if (b) {
                form.production_date = b.production_date || null;
                form.expiry_date = b.expiry_date || null;
            }
        };
        const onQuantityChange = () => {
            if (selectedProduct.value?.track_serial && Array.isArray(form.serial_numbers) && form.serial_numbers.length > form.quantity) {
                form.serial_numbers = form.serial_numbers.slice(0, form.quantity);
            }
        };

        const formatDate = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("inventory-transactions", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        transaction_type: "transfer",
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

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
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

        const loadWarehouses = async () => {
            try {
                const res = await window.axios.get("warehouses", { params: { per_page: 500 } });
                const { list: data } = parsePaginatedResponse(res);
                warehouses.value = data || [];
            } catch (_) {
                warehouses.value = [];
            }
        };

        const openAdd = () => {
            form.product_id = null;
            form.from_warehouse_id = null;
            form.to_warehouse_id = null;
            form.quantity = 1;
            form.unit_cost = 0;
            form.batch_number = "";
            form.production_date = null;
            form.expiry_date = null;
            form.serial_numbers = [];
            form.notes = "";
            batchesAvailable.value = [];
            serialsAvailable.value = [];
            dialogVisible.value = true;
            loadProducts();
            loadWarehouses();
        };

        const submitAdd = async () => {
            if (selectedProduct.value?.track_serial) {
                const len = Array.isArray(form.serial_numbers) ? form.serial_numbers.length : 0;
                if (len !== form.quantity) {
                    ElMessage.warning("请选择与调拨数量一致的序列号");
                    return;
                }
            }
            try {
                await formRef.value.validate();
            } catch (_) {
                return;
            }
            if (form.from_warehouse_id === form.to_warehouse_id) {
                ElMessage.warning("调出仓库和调入仓库不能相同");
                return;
            }
            try {
                const payload = {
                    product_id: form.product_id,
                    from_warehouse_id: form.from_warehouse_id,
                    to_warehouse_id: form.to_warehouse_id,
                    quantity: form.quantity,
                    unit_cost: form.unit_cost || undefined,
                    notes: form.notes || undefined,
                };
                if (selectedProduct.value?.track_batch && form.batch_number) {
                    payload.batch_number = form.batch_number.trim();
                    payload.production_date = form.production_date || null;
                    payload.expiry_date = form.expiry_date || null;
                }
                if (selectedProduct.value?.track_serial && Array.isArray(form.serial_numbers) && form.serial_numbers.length) {
                    payload.serial_numbers = form.serial_numbers.slice(0, form.quantity);
                }
                await window.axios.post("inventory-transactions/transfer", payload);
                ElMessage.success("调拨成功");
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "调拨失败");
            }
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
        watch(
            () => [form.product_id, form.from_warehouse_id],
            () => {
                loadBatchesAvailable();
                loadSerialsAvailable();
                nextTick(onQuantityChange);
            }
        );
        const onTransferDialogOpened = () => {
            const focusScanInput = () => {
                const wrappers = document.querySelectorAll("[data-barcode-scan]");
                for (const w of wrappers) {
                    const input = w.querySelector?.("input");
                    if (!input || typeof input.focus !== "function") continue;
                    const rect = input.getBoundingClientRect();
                    if (rect.width > 0 && rect.height > 0) {
                        input.focus();
                        return true;
                    }
                }
                barcodeScanRef.value?.focus?.();
                return false;
            };
            nextTick(() => {
                let attempts = 0;
                const tryFocus = () => {
                    if (focusScanInput()) return;
                    attempts += 1;
                    if (attempts < 20) setTimeout(tryFocus, 50);
                };
                tryFocus();
            });
        };

        return {
            Plus,
            loading,
            searchKeyword,
            list,
            pagination,
            dialogVisible,
            form,
            formRules,
            formRef,
            products,
            warehouses,
            selectedProduct,
            selectedBatch,
            batchesAvailable,
            serialsAvailable,
            onBatchSelect,
            onQuantityChange,
            loadBatchesAvailable,
            loadSerialsAvailable,
            formatDate,
            loadList,
            handleSearch,
            openAdd,
            submitAdd,
            onTransferDialogOpened,
        };
    },
});
</script>

<style scoped>
.transfer-management-container {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.data-card {
    min-height: 400px;
}
.pagination-container {
    margin-top: 16px;
    text-align: right;
}
</style>
