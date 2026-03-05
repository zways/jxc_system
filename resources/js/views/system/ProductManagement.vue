<template>
    <div class="product-management-container">
        <div class="page-header">
            <h3>商品管理</h3>
            <div class="header-actions">
                <el-input v-model="searchKeyword" placeholder="商品名称/编码/分类" style="width: 260px; margin-right: 12px" clearable @keyup="(e) => e.key === 'Enter' && handleSearch()" />
                <el-select v-model="categoryFilter" placeholder="分类" clearable style="width: 180px; margin-right: 12px" @change="handleSearch">
                    <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                </el-select>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button type="success" @click="openAdd">新增商品</el-button>
            </div>
        </div>

        <el-card class="data-card">
            <el-table :data="list" v-loading="loading" border row-key="id">
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column prop="code" label="商品编码" width="120" />
                <el-table-column prop="name" label="商品名称" min-width="160" />
                <el-table-column label="分类" width="140">
                    <template #default="{ row }">{{ row.category?.name || "-" }}</template>
                </el-table-column>
                <el-table-column prop="unit" label="单位" width="80" />
                <el-table-column prop="purchase_price" label="采购价" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.purchase_price || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="retail_price" label="零售价" width="100" align="right">
                    <template #default="{ row }">¥{{ Number(row.retail_price || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? "启用" : "禁用" }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDateTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="210" fixed="right">
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
                    :page-sizes="[10, 20, 50, 100]"
                    :total="pagination.total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="760px" :close-on-click-modal="false" @open="formRef?.clearValidate?.()">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="110px">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="商品编码" prop="code">
                            <el-input v-model="form.code" :disabled="isViewMode" placeholder="如：PROD001" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="商品名称" prop="name">
                            <el-input v-model="form.name" :disabled="isViewMode" placeholder="请输入商品名称" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="分类" prop="category_id">
                            <el-select v-model="form.category_id" :disabled="isViewMode" filterable style="width: 100%" placeholder="请选择">
                                <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="条形码" prop="barcode">
                            <el-input v-model="form.barcode" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="规格型号" prop="specification">
                            <el-input v-model="form.specification" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="24">
                        <el-form-item label="商品描述" prop="description">
                            <el-input v-model="form.description" :disabled="isViewMode" type="textarea" :rows="2" placeholder="选填" />
                        </el-form-item>
                    </el-col>

                    <el-col :span="8">
                        <el-form-item label="基本单位" prop="unit">
                            <el-select v-model="form.unit" :disabled="isViewMode" filterable allow-create default-first-option style="width: 100%" placeholder="如：件/台">
                                <el-option v-for="u in units" :key="u.id" :label="u.name" :value="u.name" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="辅助单位" prop="second_unit">
                            <el-input v-model="form.second_unit" :disabled="isViewMode" placeholder="选填" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="转换率" prop="conversion_rate">
                            <el-input-number v-model="form.conversion_rate" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>

                    <el-col :span="8">
                        <el-form-item label="采购价" prop="purchase_price">
                            <el-input-number v-model="form.purchase_price" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="零售价" prop="retail_price">
                            <el-input-number v-model="form.retail_price" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="批发价" prop="wholesale_price">
                            <el-input-number v-model="form.wholesale_price" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>

                    <el-col :span="8">
                        <el-form-item label="最低库存" prop="min_stock">
                            <el-input-number v-model="form.min_stock" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="最高库存" prop="max_stock">
                            <el-input-number v-model="form.max_stock" :disabled="isViewMode" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="状态" prop="is_active">
                            <el-select v-model="form.is_active" :disabled="isViewMode" style="width: 100%">
                                <el-option label="启用" :value="true" />
                                <el-option label="禁用" :value="false" />
                            </el-select>
                        </el-form-item>
                    </el-col>

                    <el-col :span="8">
                        <el-form-item label="序列号管理" prop="track_serial">
                            <el-switch v-model="form.track_serial" :disabled="isViewMode" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="批次管理" prop="track_batch">
                            <el-switch v-model="form.track_batch" :disabled="isViewMode" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitForm">{{ isViewMode ? "关闭" : "确定" }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { ref, reactive, onMounted, defineComponent } from "vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { parsePaginatedResponse } from "../../utils/api";

export default defineComponent({
    name: "ProductManagement",
    setup() {
        const loading = ref(false);
        const searchKeyword = ref("");
        const categoryFilter = ref(null);
        const list = ref([]);
        const pagination = reactive({ currentPage: 1, pageSize: 20, total: 0 });

        const categories = ref([]);
        const units = ref([]);

        const dialogVisible = ref(false);
        const dialogTitle = ref("新增商品");
        const isEdit = ref(false);
        const isViewMode = ref(false);
        const formRef = ref(null);
        const form = reactive({
            id: null,
            code: "",
            name: "",
            description: "",
            category_id: null,
            barcode: "",
            specification: "",
            unit: "",
            second_unit: "",
            conversion_rate: 1,
            purchase_price: 0,
            retail_price: 0,
            wholesale_price: 0,
            min_stock: 0,
            max_stock: 999999,
            track_serial: false,
            track_batch: false,
            is_active: true,
        });

        const rules = {
            code: [{ required: true, message: "请输入商品编码", trigger: "blur" }],
            name: [{ required: true, message: "请输入商品名称", trigger: "blur" }],
            category_id: [{ required: true, message: "请选择分类", trigger: "change" }],
            unit: [{ required: true, message: "请输入基本单位", trigger: "change" }],
        };

        const formatDateTime = (v) => (v ? String(v).slice(0, 19).replace("T", " ") : "");

        const loadCategories = async () => {
            try {
                const res = await window.axios.get("product-categories", { params: { per_page: 1000 } });
                const { list: data } = parsePaginatedResponse(res);
                categories.value = data || [];
            } catch (_) {
                categories.value = [];
            }
        };

        const loadUnits = async () => {
            try {
                const res = await window.axios.get("units", { params: { per_page: 1000 } });
                const payload = res.data;
                const data = payload.data;
                const items = data && data.data ? data.data : Array.isArray(data) ? data : [];
                units.value = items || [];
            } catch (_) {
                units.value = [];
            }
        };

        const loadList = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("products", {
                    params: {
                        page: pagination.currentPage,
                        per_page: pagination.pageSize,
                        search: searchKeyword.value || undefined,
                        category_id: categoryFilter.value || undefined,
                    },
                });
                const { list: data, meta } = parsePaginatedResponse(res);
                list.value = data || [];
                if (meta.total != null) pagination.total = meta.total;
                if (meta.current_page != null) pagination.currentPage = meta.current_page;
                if (meta.per_page != null) pagination.pageSize = meta.per_page;
            } catch (e) {
                list.value = [];
                ElMessage.error(e.response?.data?.message || "加载商品列表失败");
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
            form.code = "";
            form.name = "";
            form.description = "";
            form.category_id = null;
            form.barcode = "";
            form.specification = "";
            form.unit = "";
            form.second_unit = "";
            form.conversion_rate = 1;
            form.purchase_price = 0;
            form.retail_price = 0;
            form.wholesale_price = 0;
            form.min_stock = 0;
            form.max_stock = 999999;
            form.track_serial = false;
            form.track_batch = false;
            form.is_active = true;
        };

        const openAdd = async () => {
            resetForm();
            isEdit.value = false;
            isViewMode.value = false;
            dialogTitle.value = "新增商品";
            dialogVisible.value = true;
            await loadCategories();
            await loadUnits();
        };

        const editRow = async (row) => {
            isEdit.value = true;
            isViewMode.value = false;
            dialogTitle.value = "编辑商品";
            dialogVisible.value = true;
            await loadCategories();
            await loadUnits();
            Object.assign(form, {
                id: row.id,
                code: row.code,
                name: row.name,
                description: row.description || "",
                category_id: row.category?.id || null,
                barcode: row.barcode || "",
                specification: row.specification || "",
                unit: row.unit || "",
                second_unit: row.second_unit || "",
                conversion_rate: Number(row.conversion_rate || 1),
                purchase_price: Number(row.purchase_price || 0),
                retail_price: Number(row.retail_price || 0),
                wholesale_price: Number(row.wholesale_price || 0),
                min_stock: Number(row.min_stock || 0),
                max_stock: Number(row.max_stock || 999999),
                track_serial: !!row.track_serial,
                track_batch: !!row.track_batch,
                is_active: !!row.is_active,
            });
        };

        const viewDetail = async (row) => {
            isEdit.value = false;
            isViewMode.value = true;
            dialogTitle.value = "商品详情";
            dialogVisible.value = true;
            await loadCategories();
            await loadUnits();
            Object.assign(form, {
                id: row.id,
                code: row.code,
                name: row.name,
                description: row.description || "",
                category_id: row.category?.id || null,
                barcode: row.barcode || "",
                specification: row.specification || "",
                unit: row.unit || "",
                second_unit: row.second_unit || "",
                conversion_rate: Number(row.conversion_rate || 1),
                purchase_price: Number(row.purchase_price || 0),
                retail_price: Number(row.retail_price || 0),
                wholesale_price: Number(row.wholesale_price || 0),
                min_stock: Number(row.min_stock || 0),
                max_stock: Number(row.max_stock || 999999),
                track_serial: !!row.track_serial,
                track_batch: !!row.track_batch,
                is_active: !!row.is_active,
            });
        };

        const removeRow = async (row) => {
            try {
                await ElMessageBox.confirm(`确定删除商品 "${row.name}" 吗？`, "删除确认", {
                    type: "warning",
                    confirmButtonText: "确定",
                    cancelButtonText: "取消",
                });
                await window.axios.delete(`products/${row.id}`);
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
                resetForm();
                return;
            }
            try {
                await formRef.value.validate();
            } catch (_) {
                return;
            }
            try {
                const payload = {
                    code: form.code,
                    name: form.name,
                    description: form.description || null,
                    category_id: form.category_id,
                    barcode: form.barcode || null,
                    specification: form.specification || null,
                    unit: form.unit,
                    second_unit: form.second_unit || null,
                    conversion_rate: form.conversion_rate,
                    purchase_price: form.purchase_price,
                    retail_price: form.retail_price,
                    wholesale_price: form.wholesale_price,
                    min_stock: form.min_stock,
                    max_stock: form.max_stock,
                    track_serial: form.track_serial,
                    track_batch: form.track_batch,
                    is_active: form.is_active,
                };

                if (isEdit.value && form.id) {
                    await window.axios.put(`products/${form.id}`, payload);
                    ElMessage.success("更新成功");
                } else {
                    await window.axios.post("products", payload);
                    ElMessage.success("新增成功");
                }
                dialogVisible.value = false;
                resetForm();
                loadList();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || (e.response?.data?.errors ? "请检查表单" : "提交失败"));
            }
        };

        onMounted(async () => {
            await loadCategories();
            await loadUnits();
            loadList();
        });

        return {
            loading,
            searchKeyword,
            categoryFilter,
            list,
            pagination,
            categories,
            units,
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
            editRow,
            viewDetail,
            removeRow,
            submitForm,
        };
    },
});
</script>

<style scoped>
.product-management-container {
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

