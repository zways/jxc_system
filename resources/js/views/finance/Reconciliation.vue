<template>
    <div class="reconciliation-container">
        <div class="page-header">
            <h3>对账管理</h3>
            <div class="header-actions">
                <el-button type="primary" @click="loadSummary">刷新汇总</el-button>
            </div>
        </div>
        <el-row :gutter="20" class="summary-cards" v-loading="loading">
            <el-col :span="12">
                <el-card class="summary-card payable" shadow="hover">
                    <template #header>
                        <span>应付账款汇总</span>
                    </template>
                    <div class="summary-body">
                        <div class="summary-value">¥{{ summary.payableTotal.toFixed(2) }}</div>
                        <div class="summary-desc">未付余额合计（{{ summary.payableCount }} 笔）</div>
                        <el-button type="primary" size="small" @click="$router.push('/finance/payable')" style="margin-top: 12px">
                            查看应付明细
                        </el-button>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card class="summary-card receivable" shadow="hover">
                    <template #header>
                        <span>应收账款汇总</span>
                    </template>
                    <div class="summary-body">
                        <div class="summary-value">¥{{ summary.receivableTotal.toFixed(2) }}</div>
                        <div class="summary-desc">未收余额合计（{{ summary.receivableCount }} 笔）</div>
                        <el-button type="success" size="small" @click="$router.push('/finance/receivable')" style="margin-top: 12px">
                            查看应收明细
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>
        <el-card class="data-card" style="margin-top: 20px">
            <template #header>
                <span>对账说明</span>
            </template>
            <div class="reconciliation-desc">
                <p>· 应付账款：来自采购、退货等产生的应付供应商款项，可按供应商与到期日与供应商对账。</p>
                <p>· 应收账款：来自销售产生的应收客户款项，可按客户与到期日与客户对账。</p>
                <p>· 请定期在「应付账款」「应收账款」页面导出或核对明细，确保账实一致。</p>
            </div>
        </el-card>
    </div>
</template>

<script>
import { ref, reactive, onMounted, defineComponent } from "vue";
import { ElMessage } from "element-plus";

export default defineComponent({
    name: "Reconciliation",
    setup() {
        const loading = ref(false);
        const summary = reactive({
            payableTotal: 0,
            payableCount: 0,
            receivableTotal: 0,
            receivableCount: 0,
        });

        const loadSummary = async () => {
            loading.value = true;
            try {
                const res = await window.axios.get("reports/finance");
                const data = res.data?.data;
                if (data) {
                    // 从报表接口获取已汇总的数据
                    const recStats = data.receivable_stats || [];
                    const payStats = data.payable_stats || [];
                    // 只统计未付/逾期的余额和笔数
                    const unpaidStatuses = ["unpaid", "overdue", "partial"];
                    summary.payableTotal = payStats
                        .filter((s) => unpaidStatuses.includes(s.status))
                        .reduce((sum, s) => sum + Number(s.balance || 0), 0);
                    summary.payableCount = payStats
                        .filter((s) => unpaidStatuses.includes(s.status))
                        .reduce((sum, s) => sum + Number(s.count || 0), 0);
                    summary.receivableTotal = recStats
                        .filter((s) => unpaidStatuses.includes(s.status))
                        .reduce((sum, s) => sum + Number(s.balance || 0), 0);
                    summary.receivableCount = recStats
                        .filter((s) => unpaidStatuses.includes(s.status))
                        .reduce((sum, s) => sum + Number(s.count || 0), 0);
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "加载汇总失败");
            } finally {
                loading.value = false;
            }
        };

        onMounted(() => loadSummary());

        return {
            loading,
            summary,
            loadSummary,
        };
    },
});
</script>

<style scoped>
.reconciliation-container {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.summary-cards {
    margin-bottom: 20px;
}
.summary-card {
    min-height: 160px;
}
.summary-body {
    text-align: center;
    padding: 10px 0;
}
.summary-value {
    font-size: 28px;
    font-weight: bold;
    color: #303133;
}
.summary-desc {
    font-size: 14px;
    color: #909399;
    margin-top: 8px;
}
.reconciliation-desc {
    font-size: 14px;
    color: #606266;
    line-height: 1.8;
}
.reconciliation-desc p {
    margin: 8px 0;
}
</style>
