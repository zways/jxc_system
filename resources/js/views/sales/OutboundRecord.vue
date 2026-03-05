<template>
    <div class="outbound-record-container">
        <div class="page-header">
            <h3>出库记录管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="流水号/商品/批次/序列号"
                    style="width: 240px; margin-right: 12px"
                    clearable
                    @keyup="(e) => e.key === 'Enter' && loadList()"
                />
                <el-button type="primary" @click="loadList">查询</el-button>
                <el-button type="success" :icon="Plus" @click="openAdd">新增出库单</el-button>
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
                <el-table-column prop="transaction_type" label="类型" width="80">
                    <template #default="{ row }">{{ typeText(row.transaction_type) }}</template>
                </el-table-column>
                <el-table-column prop="quantity" label="数量" width="90" align="right" />
                <el-table-column prop="unit_cost" label="单位成本" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.unit_cost || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="总金额" width="100" align="right">
                    <template #default="{ row }">¥{{ Number((row.quantity || 0) * (row.unit_cost || 0)).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="批次号" width="120" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.batch_number || "-" }}</template>
                </el-table-column>
                <el-table-column label="序列号" width="120" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.serial_number || "-" }}</template>
                </el-table-column>
                <el-table-column label="生产/效期" width="140">
                    <template #default="{ row }">{{ row.production_date || "-" }} / {{ row.expiry_date || "-" }}</template>
                </el-table-column>
                <el-table-column label="关联单据" width="140" show-overflow-tooltip>
                    <template #default="{ row }">{{ extractDoc(row.notes) || "-" }}</template>
                </el-table-column>
                <el-table-column prop="notes" label="备注" min-width="140" show-overflow-tooltip>
                    <template #default="{ row }">{{ extractRemark(row.notes) || "-" }}</template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">查看</el-button>
                        <el-button size="small" type="primary" @click="editRow(row)">编辑</el-button>
                        <el-button size="small" type="danger" @click="removeRow(row)">删除</el-button>
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
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px" :close-on-click-modal="false" :trap-focus="false" @open="formRef?.clearValidate?.()" @opened="onOutboundDialogOpened">
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
                <el-form-item label="商品" prop="product_id">
                    <div style="display: flex; gap: 8px; align-items: flex-start; width: 100%">
                        <el-select v-model="form.product_id" :disabled="isViewMode" placeholder="请选择或扫码" filterable style="flex: 1">
                            <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                        </el-select>
                        <BarcodeScanInput v-show="!isViewMode" ref="barcodeScanRef" :autofocus="false" placeholder="扫码" size="default" :hint="''" style="width: 200px" @product="(p) => (form.product_id = p.id)" />
                    </div>
                </el-form-item>
                <el-form-item label="仓库" prop="warehouse_id">
                    <el-select v-model="form.warehouse_id" :disabled="isViewMode" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="数量" prop="quantity">
                    <el-input-number v-model="form.quantity" :min="1" :disabled="isViewMode" style="width: 100%" @change="onQuantityChange" />
                </el-form-item>
                <el-form-item v-if="selectedProduct?.track_batch" label="批次" prop="batch_number">
                    <el-select v-model="form.batch_number" :disabled="isViewMode" placeholder="请选择批次" filterable allow-create default-first-option style="width: 100%" @change="onBatchSelect">
                        <el-option v-for="b in batchesAvailable" :key="b.batch_number" :label="`${b.batch_number} (库存 ${b.quantity})`" :value="b.batch_number">
                            <span>{{ b.batch_number }}</span>
                            <span style="color: var(--el-text-color-secondary); margin-left: 8px">库存 {{ b.quantity }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item v-if="selectedProduct?.track_batch && selectedBatch" label="生产/效期">
                    <span>{{ selectedBatch.production_date || "-" }} / {{ selectedBatch.expiry_date || "-" }}</span>
                </el-form-item>
                <el-form-item v-if="selectedProduct?.track_serial" label="出库序列号" prop="serial_numbers">
                    <el-select v-model="form.serial_numbers" :disabled="isViewMode" multiple placeholder="请选择要出库的序列号" style="width: 100%" :max-collapse-tags="3">
                        <el-option v-for="s in serialsAvailable" :key="s.serial_number" :label="s.serial_number" :value="s.serial_number" />
                    </el-select>
                </el-form-item>
                <el-form-item label="单位成本" prop="unit_cost">
                    <el-input-number v-model="form.unit_cost" :min="0" :precision="2" :disabled="isViewMode" style="width: 100%" />
                </el-form-item>
                <el-form-item label="关联单据" prop="reference_document">
                    <el-input v-model="form.reference_document" :disabled="isViewMode" placeholder="可选，如：SO202602070001" />
                </el-form-item>
                <el-form-item label="备注" prop="notes">
                    <el-input v-model="form.notes" :disabled="isViewMode" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitForm">{{ isViewMode ? "关闭" : "确定" }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, onActivated, watch, nextTick, defineComponent } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Plus } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";
import BarcodeScanInput from "../../components/BarcodeScanInput.vue";

export default defineComponent({
    name: "OutboundRecord",
    components: { BarcodeScanInput },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const loading = ref(false);
        const searchKeyword = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });
        const dialogVisible = ref(false);
        const dialogTitle = ref("新增出库单");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const barcodeScanRef = ref(null);
        const products = ref([]);
        const warehouses = ref([]);
        const batchesAvailable = ref([]);
        const serialsAvailable = ref([]);
        const form = reactive({
            id: null,
            product_id: null,
            warehouse_id: null,
            quantity: 1,
            unit_cost: 0,
            batch_number: "",
            production_date: null,
            expiry_date: null,
            serial_numbers: [],
            reference_document: "",
            notes: "",
        });
        const selectedProduct = computed(() => products.value.find((p) => p.id === form.product_id) || null);
        const selectedBatch = computed(() => batchesAvailable.value.find((b) => b.batch_number === form.batch_number) || null);
        const formRules = {
            product_id: [{ required: true, message: "请选择商品", trigger: "change" }],
            warehouse_id: [{ required: true, message: "请选择仓库", trigger: "change" }],
            quantity: [{ required: true, message: "请输入数量", trigger: "blur" }],
            unit_cost: [{ required: true, message: "请输入单位成本", trigger: "blur" }],
            batch_number: [
                {
                    validator: (_rule, value, cb) => {
                        if (selectedProduct.value?.track_batch && !(value && String(value).trim())) {
                            cb(new Error("该商品启用批次管理，请选择批次"));
                        } else {
                            cb();
                        }
                    },
                    trigger: "change",
                },
            ],
            serial_numbers: [
                {
                    validator: (_rule, value, cb) => {
                        if (selectedProduct.value?.track_serial) {
                            const len = Array.isArray(value) ? value.length : 0;
                            if (len !== form.quantity) {
                                cb(new Error("请选择与出库数量一致的序列号"));
                            } else {
                                cb();
                            }
                        } else {
                            cb();
                        }
                    },
                    trigger: "change",
                },
            ],
        };
        const loadBatchesAvailable = async () => {
            if (!form.product_id || !form.warehouse_id) {
                batchesAvailable.value = [];
                return;
            }
            try {
                const res = await window.axios.get("inventory-transactions/batches-available", {
                    params: { product_id: form.product_id, warehouse_id: form.warehouse_id },
                });
                batchesAvailable.value = res.data?.data || [];
            } catch {
                batchesAvailable.value = [];
            }
        };
        const loadSerialsAvailable = async () => {
            if (!form.product_id || !form.warehouse_id) {
                serialsAvailable.value = [];
                return;
            }
            try {
                const res = await window.axios.get("inventory-transactions/serials-available", {
                    params: { product_id: form.product_id, warehouse_id: form.warehouse_id },
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
        const typeText = (t) => ({ in: "入库", out: "出库", adjust: "调整", transfer: "调拨" }[t] || t);
        const extractDoc = (notes) => {
            const s = notes ? String(notes) : "";
            const m = s.match(/单据[:：]\s*([^\n\r]+)/);
            return m ? m[1].trim() : "";
        };
        const extractRemark = (notes) => {
            const s = notes ? String(notes) : "";
            const lines = s.split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
            const filtered = lines.filter((l) => !/^单据[:：]/.test(l));
            return filtered.join("；");
        };

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("inventory-transactions", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        transaction_type: "out",
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
            form.id = null;
            form.product_id = null;
            form.warehouse_id = null;
            form.quantity = 1;
            form.unit_cost = 0;
            form.batch_number = "";
            form.production_date = null;
            form.expiry_date = null;
            form.serial_numbers = [];
            form.reference_document = "";
            form.notes = "";
            batchesAvailable.value = [];
            serialsAvailable.value = [];
            dialogTitle.value = "新增出库单";
            isEdit.value = false;
            isViewMode.value = false;
            dialogVisible.value = true;
            loadProducts();
            loadWarehouses();
        };

        const viewDetail = (row) => {
            form.id = row.id;
            form.product_id = row.product_id;
            form.warehouse_id = row.warehouse_id;
            form.quantity = Number(row.quantity || 0);
            form.unit_cost = Number(row.unit_cost || 0);
            form.batch_number = row.batch_number || "";
            form.production_date = row.production_date ? String(row.production_date).slice(0, 10) : null;
            form.expiry_date = row.expiry_date ? String(row.expiry_date).slice(0, 10) : null;
            form.serial_numbers = row.serial_number ? [row.serial_number] : [];
            form.reference_document = extractDoc(row.notes || "");
            form.notes = extractRemark(row.notes || "");
            dialogTitle.value = "出库详情";
            isEdit.value = false;
            isViewMode.value = true;
            dialogVisible.value = true;
            loadProducts();
            loadWarehouses();
            loadBatchesAvailable();
            loadSerialsAvailable();
        };

        const editRow = (row) => {
            form.id = row.id;
            form.product_id = row.product_id;
            form.warehouse_id = row.warehouse_id;
            form.quantity = Number(row.quantity || 0);
            form.unit_cost = Number(row.unit_cost || 0);
            form.batch_number = row.batch_number || "";
            form.production_date = row.production_date ? String(row.production_date).slice(0, 10) : null;
            form.expiry_date = row.expiry_date ? String(row.expiry_date).slice(0, 10) : null;
            form.serial_numbers = row.serial_number ? [row.serial_number] : [];
            form.reference_document = extractDoc(row.notes || "");
            form.notes = extractRemark(row.notes || "");
            dialogTitle.value = "编辑出库单";
            isEdit.value = true;
            isViewMode.value = false;
            dialogVisible.value = true;
            loadProducts();
            loadWarehouses();
            loadBatchesAvailable();
            loadSerialsAvailable();
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除该出库记录（${row.transaction_number}）吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`inventory-transactions/${row.id}`);
                ElMessage.success("删除成功");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        const submitForm = async () => {
            if (isViewMode.value) {
                dialogVisible.value = false;
                return;
            }
            if (selectedProduct.value?.track_serial) {
                const len = Array.isArray(form.serial_numbers) ? form.serial_numbers.length : 0;
                if (len !== form.quantity) {
                    ElMessage.warning("请选择与出库数量一致的序列号");
                    return;
                }
            }
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            try {
                const notesParts = [];
                if (form.reference_document) notesParts.push(`单据:${form.reference_document}`);
                if (form.notes) notesParts.push(form.notes);
                const payload = {
                    product_id: form.product_id,
                    warehouse_id: form.warehouse_id,
                    transaction_type: "out",
                    quantity: form.quantity,
                    unit_cost: form.unit_cost,
                    reference_type: "manual",
                    notes: notesParts.join("\n") || null,
                };
                if (selectedProduct.value?.track_batch && form.batch_number) {
                    payload.batch_number = form.batch_number.trim();
                    payload.production_date = form.production_date || null;
                    payload.expiry_date = form.expiry_date || null;
                }
                if (selectedProduct.value?.track_serial && Array.isArray(form.serial_numbers) && form.serial_numbers.length) {
                    payload.serial_numbers = form.serial_numbers.slice(0, form.quantity);
                    payload.quantity = payload.serial_numbers.length;
                }
                if (isEdit.value && form.id) {
                    if (payload.serial_numbers) {
                        delete payload.serial_numbers;
                        payload.serial_number = form.serial_numbers[0] || null;
                    }
                    await window.axios.put(`inventory-transactions/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("inventory-transactions", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? JSON.stringify(e.response.data.errors) : "提交失败"));
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
            () => [form.product_id, form.warehouse_id],
            () => {
                loadBatchesAvailable();
                loadSerialsAvailable();
                nextTick(onQuantityChange);
            }
        );
        const onOutboundDialogOpened = () => {
            if (!isViewMode.value) {
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
            }
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
            formatDate,
            typeText,
            extractDoc,
            extractRemark,
            loadList,
            openAdd,
            submitForm,
            onOutboundDialogOpened,
            dialogTitle,
            isViewMode,
            viewDetail,
            editRow,
            removeRow,
        };
    },
});
</script>

<style scoped>
.outbound-record-container {
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
