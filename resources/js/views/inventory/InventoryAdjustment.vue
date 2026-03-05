<template>
    <div class="inventory-adjustment-container">
        <div class="page-header">
            <h3>库存调整</h3>
            <div class="header-actions">
                <el-input v-model="searchKeyword" placeholder="调整单号/商品" style="width: 240px; margin-right: 12px" clearable @keyup="(e) => e.key === 'Enter' && handleSearch()" />
                <el-select v-model="typeFilter" placeholder="类型" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="增加" value="increase" />
                    <el-option label="减少" value="decrease" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增调整</el-button>
            </div>
        </div>

        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="adjustment_number" label="调整单号" width="160" />
                <el-table-column label="商品" min-width="140">
                    <template #default="{ row }">{{ row.product?.name || "-" }}</template>
                </el-table-column>
                <el-table-column label="仓库" width="140">
                    <template #default="{ row }">{{ row.warehouse?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="adjustment_type" label="类型" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.adjustment_type === 'increase' ? 'success' : 'danger'">
                            {{ row.adjustment_type === "increase" ? "增加" : "减少" }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="quantity" label="数量" width="100" align="right" />
                <el-table-column prop="adjustment_reason" label="原因" min-width="160" show-overflow-tooltip />
                <el-table-column prop="adjustment_date" label="日期" width="120" />
                <el-table-column prop="notes" label="备注" min-width="140" show-overflow-tooltip />
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">查看</el-button>
                        <el-button size="small" type="danger" @click="removeRow(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-container">
                <el-pagination
                    v-model:current-page="pagination.currentPage"
                    v-model:page-size="pagination.pageSize"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="pagination.total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="640px" :close-on-click-modal="false" :trap-focus="false" @open="formRef?.clearValidate?.()" @opened="onAdjustmentDialogOpened">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
                <el-form-item label="商品" prop="product_id">
                    <div style="display: flex; gap: 8px; align-items: flex-start; width: 100%">
                        <el-select v-model="form.product_id" :disabled="isViewMode" filterable style="flex: 1" placeholder="请选择或扫码">
                            <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                        </el-select>
                        <BarcodeScanInput
                            v-show="!isViewMode"
                            ref="barcodeScanRef"
                            :autofocus="false"
                            placeholder="扫码"
                            size="default"
                            :hint="''"
                            style="width: 200px"
                            @product="(p) => (form.product_id = p.id)"
                        />
                    </div>
                </el-form-item>
                <el-form-item label="仓库" prop="warehouse_id">
                    <el-select v-model="form.warehouse_id" :disabled="isViewMode" filterable style="width: 100%" placeholder="请选择">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型" prop="adjustment_type">
                    <el-select v-model="form.adjustment_type" :disabled="isViewMode" style="width: 100%">
                        <el-option label="增加" value="increase" />
                        <el-option label="减少" value="decrease" />
                    </el-select>
                </el-form-item>
                <el-form-item label="数量" prop="quantity">
                    <el-input-number v-model="form.quantity" :disabled="isViewMode" :min="0.01" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item label="原因" prop="adjustment_reason">
                    <el-input v-model="form.adjustment_reason" :disabled="isViewMode" placeholder="选填" />
                </el-form-item>
                <el-form-item label="日期" prop="adjustment_date">
                    <el-date-picker v-model="form.adjustment_date" :disabled="isViewMode" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>
                <el-form-item label="备注" prop="notes">
                    <el-input v-model="form.notes" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
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
import { ref, reactive, onMounted, watch, nextTick, defineComponent } from "vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";
import BarcodeScanInput from "../../components/BarcodeScanInput.vue";

export default defineComponent({
    name: "InventoryAdjustment",
    components: { BarcodeScanInput },
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const typeFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });

        const warehouses = ref([]);
        const products = ref([]);

        const dialogVisible = ref(false);
        const dialogTitle = ref("新增调整");
        const isViewMode = ref(false);
        const formRef = ref(null);
        const barcodeScanRef = ref(null);
        const form = reactive({
            id: null,
            product_id: null,
            warehouse_id: null,
            adjustment_type: "increase",
            quantity: 1,
            adjustment_reason: "",
            adjustment_date: "",
            notes: "",
        });

        const rules = {
            product_id: [{ required: true, message: "请选择商品", trigger: "change" }],
            warehouse_id: [{ required: true, message: "请选择仓库", trigger: "change" }],
            adjustment_type: [{ required: true, message: "请选择类型", trigger: "change" }],
            quantity: [{ required: true, message: "请输入数量", trigger: "blur" }],
        };

        const formatDateTime = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");

        const loadWarehouses = async () => {
            try {
                const res = await window.axios.get("warehouses", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                warehouses.value = data || [];
            } catch (_) {
                warehouses.value = [];
            }
        };

        const loadProducts = async () => {
            try {
                const res = await window.axios.get("products", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                products.value = data || [];
            } catch (_) {
                products.value = [];
            }
        };

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("inventory-adjustments", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        adjustment_type: typeFilter.value || undefined,
                    },
                });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data || [];
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                list.value = [];
                ElMessage.error(e.response?.data?.message || "加载库存调整失败");
            } finally {
                loading.value = false;
            }
        };

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };

        const resetForm = () => {
            form.id = null;
            form.product_id = null;
            form.warehouse_id = null;
            form.adjustment_type = "increase";
            form.quantity = 1;
            form.adjustment_reason = "";
            form.adjustment_date = new Date().toISOString().slice(0, 10);
            form.notes = "";
        };

        const openAdd = async () => {
            resetForm();
            dialogTitle.value = "新增调整";
            isViewMode.value = false;
            dialogVisible.value = true;
            await loadWarehouses();
            await loadProducts();
        };

        const viewDetail = async (row) => {
            resetForm();
            Object.assign(form, {
                id: row.id,
                product_id: row.product?.id || row.product_id,
                warehouse_id: row.warehouse?.id || row.warehouse_id,
                adjustment_type: row.adjustment_type || "increase",
                quantity: Number(row.quantity || 0),
                adjustment_reason: row.adjustment_reason || "",
                adjustment_date: row.adjustment_date || new Date().toISOString().slice(0, 10),
                notes: row.notes || "",
            });
            dialogTitle.value = "调整详情";
            isViewMode.value = true;
            dialogVisible.value = true;
            await loadWarehouses();
            await loadProducts();
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除调整单 "${row.adjustment_number}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`inventory-adjustments/${row.id}`);
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
            try {
                await formRef.value.validate();
            } catch {
                return;
            }
            try {
                const payload = {
                    product_id: form.product_id,
                    warehouse_id: form.warehouse_id,
                    adjustment_type: form.adjustment_type,
                    quantity: form.quantity,
                    adjustment_reason: form.adjustment_reason || null,
                    adjustment_date: form.adjustment_date || null,
                    notes: form.notes || null,
                };
                await window.axios.post("inventory-adjustments", payload);
                ElMessage.success("新增成功");
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        const onAdjustmentDialogOpened = () => {
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

        onMounted(async () => {
            await loadWarehouses();
            await loadProducts();
            loadList();
        });

        return {
            loading,
            searchKeyword,
            typeFilter,
            list,
            pagination,
            warehouses,
            products,
            dialogVisible,
            dialogTitle,
            isViewMode,
            form,
            rules,
            formRef,
            formatDateTime,
            loadList,
            handleSearch,
            openAdd,
            viewDetail,
            removeRow,
            submitForm,
            onAdjustmentDialogOpened,
        };
    },
});
</script>

<style scoped>
.inventory-adjustment-container {
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

