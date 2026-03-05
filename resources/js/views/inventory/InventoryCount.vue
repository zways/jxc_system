<template>
    <div class="inventory-count-container">
        <div class="page-header">
            <h3>库存盘点管理</h3>
            <div class="header-actions">
                <el-input
                    v-model="searchKeyword"
                    placeholder="盘点单号/仓库"
                    style="width: 240px; margin-right: 12px"
                    clearable
                />
                <el-select v-model="statusFilter" placeholder="状态" clearable style="width: 120px; margin-right: 12px" @change="handleSearch">
                    <el-option label="待盘点" value="pending" />
                    <el-option label="进行中" value="in_progress" />
                    <el-option label="已完成" value="completed" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增盘点单</el-button>
            </div>
        </div>
        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="count_number" label="盘点单号" width="150" />
                <el-table-column label="仓库" width="120">
                    <template #default="{ row }">{{ row.warehouse?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="type" label="类型" width="100">
                    <template #default="{ row }">{{ typeText(row.type) }}</template>
                </el-table-column>
                <el-table-column prop="count_date" label="盘点日期" width="120" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="variance_amount" label="差异金额" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.variance_amount || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="notes" label="备注" min-width="140" show-overflow-tooltip />
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="280" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="openItems(row)">明细</el-button>
                        <el-button size="small" type="primary" :disabled="row.status === 'completed' || row.status === 'verified'" @click="editRow(row)">编辑</el-button>
                        <el-tooltip v-if="(row.items_count ?? 0) === 0" content="请先点击「明细」添加商品并保存" placement="top">
                            <span>
                                <el-button size="small" type="success" disabled>完成</el-button>
                            </span>
                        </el-tooltip>
                        <el-button v-else size="small" type="success" :disabled="row.status === 'completed' || row.status === 'verified'" @click="completeCount(row)">
                            完成
                        </el-button>
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

        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑盘点单' : '新增盘点单'" width="560px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px">
                <el-form-item label="仓库" prop="warehouse_id">
                    <el-select v-model="form.warehouse_id" placeholder="请选择" filterable style="width: 100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型" prop="type">
                    <el-select v-model="form.type" placeholder="请选择" style="width: 100%">
                        <el-option label="全盘" value="full" />
                        <el-option label="部分" value="partial" />
                        <el-option label="循环" value="cycle" />
                    </el-select>
                </el-form-item>
                <el-form-item label="盘点日期" prop="count_date">
                    <el-date-picker v-model="form.count_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>
                <el-form-item label="备注" prop="notes">
                    <el-input v-model="form.notes" type="textarea" :rows="2" placeholder="选填" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitForm">确定</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="itemsDialogVisible" title="盘点明细" width="860px" :close-on-click-modal="false" @opened="onItemsDialogOpened">
            <div v-loading="itemsLoading" style="min-height: 200px">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; gap: 12px; flex-wrap: wrap">
                    <div style="color: #606266; flex: 1">
                        盘点单：{{ currentCount?.count_number || '-' }}，仓库：{{ currentCount?.warehouse?.name || '-' }}
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px">
                        <BarcodeScanInput
                            ref="barcodeScanRef"
                            :autofocus="false"
                            placeholder="扫码添加商品"
                            size="small"
                            :hint="''"
                            style="width: 280px"
                            @product="onScanProduct"
                        />
                        <el-button size="small" @click="addItemRow">添加行</el-button>
                    </div>
                </div>
                <el-table :data="itemsForm" border size="small">
                    <el-table-column label="商品" min-width="220">
                        <template #default="{ row }">
                            <el-select v-model="row.product_id" placeholder="请选择" filterable style="width: 100%">
                                <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </template>
                    </el-table-column>
                    <el-table-column label="账面数量" width="120" align="right">
                        <template #default="{ row }">{{ row.book_quantity ?? "-" }}</template>
                    </el-table-column>
                    <el-table-column label="实盘数量" width="140">
                        <template #default="{ row }">
                            <el-input-number v-model="row.counted_quantity" :min="0" :precision="2" style="width: 100%" />
                        </template>
                    </el-table-column>
                    <el-table-column label="差异数量" width="120" align="right">
                        <template #default="{ row }">
                            <span :style="{ color: Number(row.variance_quantity || 0) === 0 ? '#909399' : (Number(row.variance_quantity || 0) > 0 ? '#67C23A' : '#F56C6C') }">
                                {{ row.variance_quantity ?? "-" }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="备注" min-width="160">
                        <template #default="{ row }">
                            <el-input v-model="row.notes" placeholder="选填" />
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="90" fixed="right">
                        <template #default="{ $index }">
                            <el-button size="small" type="danger" link @click="removeItemRow($index)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div style="margin-top: 10px; color: #909399">
                    提示：账面数量/差异数量在保存后由系统计算。
                </div>
            </div>
            <template #footer>
                <el-button @click="itemsDialogVisible = false">关闭</el-button>
                <el-button type="primary" @click="saveItems">保存明细</el-button>
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
    name: "InventoryCount",
    components: { BarcodeScanInput },
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const statusFilter = ref("");
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });
        const dialogVisible = ref(false);
        const isEdit = ref(false);
        const formRef = ref(null);
        const warehouses = ref([]);
        const products = ref([]);

        const itemsDialogVisible = ref(false);
        const itemsLoading = ref(false);
        const currentCount = ref(null);
        const itemsForm = ref([]);
        const barcodeScanRef = ref(null);
        const form = reactive({
            id: null,
            warehouse_id: null,
            type: "full",
            count_date: "",
            notes: "",
        });
        const formRules = {
            warehouse_id: [{ required: true, message: "请选择仓库", trigger: "change" }],
            type: [{ required: true, message: "请选择类型", trigger: "change" }],
            count_date: [{ required: true, message: "请选择盘点日期", trigger: "change" }],
        };

        const formatDate = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");
        const typeText = (t) => ({ full: "全盘", partial: "部分", cycle: "循环" }[t] || t);
        const statusText = (s) => ({ pending: "待盘点", in_progress: "进行中", completed: "已完成", verified: "已审核" }[s] || s);
        const statusTagType = (s) => ({ pending: "warning", in_progress: "primary", completed: "success", verified: "success" }[s] || "info");

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
                const res = await window.axios.get("products", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                products.value = data || [];
            } catch (_) {
                products.value = [];
            }
        };

        const handleSearch = () => {
            pagination.currentPage = 1;
            loadList();
        };
        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("inventory-counts", {
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

        const resetForm = () => {
            form.id = null;
            form.warehouse_id = null;
            form.type = "full";
            form.count_date = new Date().toISOString().slice(0, 10);
            form.notes = "";
        };

        const openAdd = () => {
            resetForm();
            isEdit.value = false;
            dialogVisible.value = true;
            loadWarehouses();
        };

        const editRow = (row) => {
            form.id = row.id;
            form.warehouse_id = row.warehouse_id || row.warehouse?.id || null;
            form.type = row.type || "full";
            form.count_date = row.count_date ? String(row.count_date).slice(0, 10) : "";
            form.notes = row.notes || "";
            isEdit.value = true;
            dialogVisible.value = true;
            loadWarehouses();
        };

        const submitForm = async () => {
            try {
                await formRef.value.validate();
            } catch (_) {
                return;
            }
            try {
                if (isEdit.value) {
                    await window.axios.put(`inventory-counts/${form.id}`, {
                        warehouse_id: form.warehouse_id,
                        type: form.type,
                        count_date: form.count_date,
                        notes: form.notes,
                    });
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("inventory-counts", {
                        warehouse_id: form.warehouse_id,
                        type: form.type,
                        count_date: form.count_date,
                        notes: form.notes,
                    });
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "提交失败");
            }
        };

        const openItems = async (row) => {
            if (!row?.id) return;
            itemsDialogVisible.value = true;
            itemsLoading.value = true;
            currentCount.value = row;
            itemsForm.value = [];
            await loadProducts();
            try {
                const res = await window.axios.get(`inventory-counts/${row.id}`);
                currentCount.value = res.data?.data || row;
                const itemsRes = await window.axios.get(`inventory-counts/${row.id}/items`);
                const items = itemsRes.data?.data || [];
                itemsForm.value = (items || []).map((it) => ({
                    product_id: it.product?.id || it.product_id,
                    book_quantity: it.book_quantity,
                    counted_quantity: Number(it.counted_quantity || 0),
                    variance_quantity: it.variance_quantity,
                    notes: it.notes || "",
                }));
                if (itemsForm.value.length === 0) addItemRow();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "加载明细失败");
                itemsDialogVisible.value = false;
            } finally {
                itemsLoading.value = false;
            }
        };

        const addItemRow = () => {
            itemsForm.value.push({ product_id: null, counted_quantity: 0, notes: "" });
        };

        const onScanProduct = (product) => {
            const pid = product.id;
            const existing = itemsForm.value.find((r) => r.product_id === pid);
            if (existing) {
                existing.counted_quantity = Number(existing.counted_quantity || 0) + 1;
            } else {
                itemsForm.value.push({
                    product_id: pid,
                    counted_quantity: 1,
                    notes: "",
                });
            }
        };

        const onItemsDialogOpened = () => {
            setTimeout(() => barcodeScanRef.value?.focus?.(), 100);
        };
        const removeItemRow = (idx) => {
            itemsForm.value.splice(idx, 1);
        };

        const saveItems = async () => {
            if (!currentCount.value?.id) return;
            const payloadItems = (itemsForm.value || []).filter((r) => r.product_id);
            if (payloadItems.length === 0) {
                ElMessage.warning("请至少选择一个商品");
                return;
            }
            itemsLoading.value = true;
            try {
                const res = await window.axios.put(`inventory-counts/${currentCount.value.id}/items`, {
                    items: payloadItems.map((r) => ({
                        product_id: r.product_id,
                        counted_quantity: r.counted_quantity,
                        notes: r.notes || undefined,
                    })),
                });
                const updated = res.data?.data;
                currentCount.value = updated || currentCount.value;
                itemsForm.value = (updated?.items || []).map((it) => ({
                    product_id: it.product?.id || it.product_id,
                    book_quantity: it.book_quantity,
                    counted_quantity: Number(it.counted_quantity || 0),
                    variance_quantity: it.variance_quantity,
                    notes: it.notes || "",
                }));
                ElMessage.success("已保存");
                loadList();
                itemsDialogVisible.value = false;
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "保存失败");
            } finally {
                itemsLoading.value = false;
            }
        };

        const completeCount = async (row) => {
            if (!row?.id) return;
            try {
                await ElMessageBox.confirm(`确定完成盘点单「${row.count_number || row.id}」吗？将生成库存调整流水。`, "完成确认", {
                    type: "warning",
                });
                await window.axios.post(`inventory-counts/${row.id}/complete`);
                ElMessage.success("已完成");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "完成操作失败");
                }
            }
        };

        const removeRow = async (row) => {
            if (!row?.id) return;
            try {
                await ElMessageBox.confirm(`确定删除盘点单「${row.count_number || row.id}」吗？`, "删除确认", { type: "warning" });
                await window.axios.delete(`inventory-counts/${row.id}`);
                ElMessage.success("删除成功");
                loadList();
            } catch (e) {
                if (e !== "cancel" && e?.message !== "cancel") {
                    ElMessage.error(e.response?.data?.message || "删除失败");
                }
            }
        };

        onMounted(() => {
            loadWarehouses();
            loadProducts();
            loadList();
        });

        return {
            loading,
            searchKeyword,
            statusFilter,
            list,
            pagination,
            formatDate,
            typeText,
            statusText,
            statusTagType,
            handleSearch,
            loadList,
            dialogVisible,
            isEdit,
            form,
            formRules,
            formRef,
            warehouses,
            openAdd,
            editRow,
            submitForm,
            // items
            products,
            itemsDialogVisible,
            itemsLoading,
            currentCount,
            itemsForm,
            openItems,
            barcodeScanRef,
            onItemsDialogOpened,
            onScanProduct,
            addItemRow,
            removeItemRow,
            saveItems,
            completeCount,
            removeRow,
        };
    },
});
</script>

<style scoped>
.inventory-count-container {
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
