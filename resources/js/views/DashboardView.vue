<template>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2>首页仪表盘</h2>
            <div class="date-range">
                <el-date-picker
                    v-model="dateRange"
                    type="daterange"
                    range-separator="至"
                    start-placeholder="开始日期"
                    end-placeholder="结束日期"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                />
                <el-button
                    type="primary"
                    @click="refreshData"
                    style="margin-left: 10px"
                    >刷新</el-button
                >
            </div>
        </div>

        <!-- KPI 指标卡片 -->
        <div class="kpi-cards">
            <el-card class="kpi-card" v-for="card in kpiCards" :key="card.key">
                <template #header>
                    <div class="card-header">
                        <span>{{ card.title }}</span>
                        <el-tag :type="card.trendType" size="small">{{
                            card.trend
                        }}</el-tag>
                    </div>
                </template>
                <div class="card-content">
                    <div class="value">{{ card.value }}</div>
                    <div class="subtitle">{{ card.subtitle }}</div>
                </div>
                <div class="trend-visual">
                    <div class="trend-bar-bg">
                        <div
                            class="trend-bar-fill"
                            :class="getTrendState(card.trend)"
                            :style="{ width: `${getTrendWidth(card.trend)}%` }"
                        ></div>
                    </div>
                    <div class="trend-text">{{ getTrendText(card.trend) }}</div>
                </div>
            </el-card>
        </div>

        <!-- 图表区域 -->
        <div class="charts-section">
            <el-card class="chart-card">
                <template #header>
                    <div class="card-header">
                        <span>销售趋势图</span>
                    </div>
                </template>
                <div class="chart-container">
                    <div ref="salesChartRef" style="height: 400px"></div>
                </div>
            </el-card>

            <el-card class="chart-card">
                <template #header>
                    <div class="card-header">
                        <span>库存状况</span>
                    </div>
                </template>
                <div class="chart-container">
                    <div ref="inventoryChartRef" style="height: 400px"></div>
                </div>
            </el-card>
        </div>

        <!-- 快捷操作和待办事项 -->
        <div class="quick-actions-section">
            <el-card class="quick-actions-card">
                <template #header>
                    <div class="card-header">
                        <span>快捷操作</span>
                    </div>
                </template>
                <div class="quick-actions-grid">
                    <div
                        class="action-item"
                        v-for="action in quickActions"
                        :key="action.key"
                        @click="handleQuickAction(action.key)"
                    >
                        <div class="action-icon">
                            <el-icon :size="30" color="#409EFF">
                                <shopping-cart
                                    v-if="action.iconName === 'ShoppingCart'"
                                />
                                <goods v-if="action.iconName === 'Goods'" />
                                <document
                                    v-if="action.iconName === 'Document'"
                                />
                                <office-building
                                    v-if="action.iconName === 'OfficeBuilding'"
                                />
                            </el-icon>
                        </div>
                        <div class="action-title">{{ action.title }}</div>
                    </div>
                </div>
            </el-card>

            <el-card class="todo-card">
                <template #header>
                    <div class="card-header">
                        <span>待办事项</span>
                    </div>
                </template>
                <el-table :data="todoList" style="width: 100%">
                    <el-table-column prop="title" label="任务标题" />
                    <el-table-column prop="priority" label="优先级">
                        <template #default="{ row }">
                            <el-tag :type="getPriorityTagType(row.priority)">{{
                                row.priority
                            }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="dueDate" label="截止时间" />
                    <el-table-column label="操作">
                        <template #default="{ row }">
                            <el-button
                                size="small"
                                @click="handleTodoAction(row)"
                                >处理</el-button
                            >
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, onBeforeUnmount, reactive, nextTick } from "vue";
import { useRouter } from "vue-router";
import { ElMessage } from "element-plus";
import * as echarts from "echarts/core";
import { BarChart, LineChart, PieChart } from "echarts/charts";
import {
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent,
} from "echarts/components";
import { CanvasRenderer } from "echarts/renderers";
import {
    ShoppingCart,
    Goods,
    Document,
    OfficeBuilding,
} from "@element-plus/icons-vue";

echarts.use([
    BarChart,
    LineChart,
    PieChart,
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent,
    CanvasRenderer,
]);

export default {
    name: "DashboardView",
    setup() {
        const router = useRouter();
        const loading = ref(false);

        // 初始化日期范围为本月
        const today = new Date();
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const dateRange = ref([
            startOfMonth.toISOString().split("T")[0],
            today.toISOString().split("T")[0],
        ]);

        // KPI 卡片数据
        const kpiCards = reactive([
            {
                key: "sales",
                title: "总销售额",
                value: "¥0",
                subtitle: "本月",
                trend: "0%",
                trendType: "info",
            },
            {
                key: "orders",
                title: "订单数量",
                value: "0",
                subtitle: "本月",
                trend: "0%",
                trendType: "info",
            },
            {
                key: "inventory",
                title: "库存总额",
                value: "¥0",
                subtitle: "当前",
                trend: "-",
                trendType: "info",
            },
            {
                key: "receivable",
                title: "应收账款",
                value: "¥0",
                subtitle: "待收",
                trend: "-",
                trendType: "info",
            },
        ]);

        // 快捷操作
        const quickActions = [
            { key: "new-order", title: "新建销售订单", iconName: "ShoppingCart" },
            { key: "new-purchase", title: "新建采购单", iconName: "ShoppingCart" },
            { key: "inventory-check", title: "库存盘点", iconName: "Goods" },
            { key: "financial-report", title: "财务报表", iconName: "Document" },
            { key: "customer-management", title: "客户管理", iconName: "OfficeBuilding" },
            { key: "supplier-management", title: "供应商管理", iconName: "OfficeBuilding" },
        ];

        // 待办事项
        const todoList = ref([]);

        // 图表引用
        const salesChartRef = ref(null);
        const inventoryChartRef = ref(null);
        // ECharts 实例
        let salesChartInstance = null;
        let inventoryChartInstance = null;

        // 格式化金额
        const formatCurrency = (value) => {
            const num = Number(value) || 0;
            if (num >= 10000) {
                return `¥${(num / 10000).toFixed(2)}万`;
            }
            return `¥${num.toLocaleString("zh-CN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        };

        // ── 图表初始化 ─────────────────────────────
        const initSalesChart = (trendData) => {
            if (!salesChartRef.value) return;
            if (!salesChartInstance) {
                salesChartInstance = echarts.init(salesChartRef.value);
            }
            const dates = (trendData || []).map((d) => d.period);
            const amounts = (trendData || []).map((d) => Number(d.total_amount));
            const counts = (trendData || []).map((d) => Number(d.order_count));

            salesChartInstance.setOption({
                tooltip: { trigger: "axis" },
                legend: { data: ["销售金额", "订单数"] },
                grid: { left: "3%", right: "4%", bottom: "3%", containLabel: true },
                xAxis: { type: "category", boundaryGap: false, data: dates },
                yAxis: [
                    { type: "value", name: "金额(¥)", position: "left" },
                    { type: "value", name: "订单数", position: "right" },
                ],
                series: [
                    {
                        name: "销售金额",
                        type: "line",
                        smooth: true,
                        areaStyle: { opacity: 0.15 },
                        data: amounts,
                        itemStyle: { color: "#409EFF" },
                    },
                    {
                        name: "订单数",
                        type: "bar",
                        yAxisIndex: 1,
                        data: counts,
                        itemStyle: { color: "#67C23A" },
                        barMaxWidth: 30,
                    },
                ],
            });
        };

        const initInventoryChart = (categoryData) => {
            if (!inventoryChartRef.value) return;
            if (!inventoryChartInstance) {
                inventoryChartInstance = echarts.init(inventoryChartRef.value);
            }
            const pieData = (categoryData || []).map((c) => ({
                name: c.category?.name || `分类${c.category_id}`,
                value: Number(c.total_value),
            }));

            inventoryChartInstance.setOption({
                tooltip: { trigger: "item", formatter: "{b}: ¥{c} ({d}%)" },
                legend: { orient: "vertical", left: "left", top: "middle" },
                series: [
                    {
                        name: "库存价值",
                        type: "pie",
                        radius: ["35%", "65%"],
                        center: ["60%", "50%"],
                        avoidLabelOverlap: true,
                        label: { show: true, formatter: "{b}\n¥{c}" },
                        emphasis: { label: { show: true, fontSize: 14, fontWeight: "bold" } },
                        data: pieData.length ? pieData : [{ name: "暂无数据", value: 0 }],
                    },
                ],
            });
        };

        const parseTrendValue = (trend) => {
            if (trend === null || trend === undefined) return null;
            const text = String(trend).trim();
            if (!text || text === "-") return null;
            const match = text.match(/-?\d+(\.\d+)?/);
            return match ? Number(match[0]) : null;
        };

        const getTrendState = (trend) => {
            const value = parseTrendValue(trend);
            if (value === null) return "none";
            return value >= 0 ? "up" : "down";
        };

        const getTrendWidth = (trend) => {
            const value = parseTrendValue(trend);
            if (value === null) return 100;
            const absValue = Math.min(Math.abs(value), 100);
            return Math.max(absValue, 8);
        };

        const getTrendText = (trend) => {
            const value = parseTrendValue(trend);
            if (value === null) return "暂无环比数据";
            const absValue = Math.abs(value);
            return value >= 0
                ? `较上期增长 ${absValue}%`
                : `较上期下降 ${absValue}%`;
        };

        const handleResize = () => {
            salesChartInstance?.resize();
            inventoryChartInstance?.resize();
        };

        // ── 数据获取 ─────────────────────────────
        const fetchDashboardData = async () => {
            loading.value = true;
            try {
                const params = {};
                if (dateRange.value && dateRange.value.length === 2) {
                    params.start_date = dateRange.value[0];
                    params.end_date = dateRange.value[1];
                }

                const [overviewRes, salesRes, inventoryRes] = await Promise.all([
                    window.axios.get("reports/overview", { params }),
                    window.axios.get("reports/sales", { params }).catch(() => null),
                    window.axios.get("reports/inventory", { params }).catch(() => null),
                ]);

                if (overviewRes.data.success) {
                    const data = overviewRes.data.data;
                    const salesCard = kpiCards.find((c) => c.key === "sales");
                    if (salesCard) {
                        salesCard.value = formatCurrency(data.sales?.total_sales);
                        const growth = Number(data.sales?.growth || 0);
                        salesCard.trend = `${growth >= 0 ? "+" : ""}${growth}%`;
                        salesCard.trendType = growth >= 0 ? "success" : "danger";
                    }
                    const ordersCard = kpiCards.find((c) => c.key === "orders");
                    if (ordersCard) {
                        ordersCard.value = Number(data.sales?.total_orders || 0).toLocaleString();
                    }
                    const inventoryCard = kpiCards.find((c) => c.key === "inventory");
                    if (inventoryCard) {
                        inventoryCard.value = formatCurrency(data.inventory?.total_value);
                        inventoryCard.subtitle = `${data.inventory?.product_count || 0}种商品`;
                    }
                    const receivableCard = kpiCards.find((c) => c.key === "receivable");
                    if (receivableCard) {
                        receivableCard.value = formatCurrency(data.finance?.receivable);
                        receivableCard.trendType = Number(data.finance?.receivable || 0) > 0 ? "warning" : "success";
                    }
                }

                // 渲染图表
                await nextTick();

                if (salesRes?.data?.success) {
                    initSalesChart(salesRes.data.data?.trend || []);
                }
                if (inventoryRes?.data?.success) {
                    initInventoryChart(inventoryRes.data.data?.inventory_by_category || []);
                }

            } catch (error) {
                console.error("获取仪表盘数据失败:", error);
                ElMessage.error("获取数据失败，请稍后重试");
            } finally {
                loading.value = false;
            }
        };

        const fetchTodoList = async () => {
            try {
                const [purchaseResponse, salesResponse] = await Promise.all([
                    window.axios.get("purchase-orders", { params: { status: "pending", per_page: 5 } }),
                    window.axios.get("sales-orders", { params: { status: "pending", per_page: 5 } }),
                ]);

                const todos = [];
                if (purchaseResponse.data.success && purchaseResponse.data.data?.data) {
                    purchaseResponse.data.data.data.forEach((order) => {
                        todos.push({
                            title: `待处理采购单: ${order.order_number}`,
                            priority: "高",
                            dueDate: order.expected_delivery_date || "-",
                            type: "purchase",
                            id: order.id,
                        });
                    });
                }
                if (salesResponse.data.success && salesResponse.data.data?.data) {
                    salesResponse.data.data.data.forEach((order) => {
                        todos.push({
                            title: `待处理销售单: ${order.order_number}`,
                            priority: "高",
                            dueDate: order.delivery_date || "-",
                            type: "sales",
                            id: order.id,
                        });
                    });
                }
                if (todos.length === 0) {
                    todos.push({ title: "暂无待办事项", priority: "低", dueDate: "-", type: "info" });
                }
                todoList.value = todos;
            } catch (error) {
                console.error("获取待办事项失败:", error);
            }
        };

        const getPriorityTagType = (priority) => {
            switch (priority) {
                case "高": return "danger";
                case "中": return "warning";
                case "低": return "info";
                default: return "info";
            }
        };

        const handleQuickAction = (actionKey) => {
            const routes = {
                "new-order": "/sales/order",
                "new-purchase": "/purchase/order",
                "inventory-check": "/inventory/count",
                "financial-report": "/reports",
                "customer-management": "/sales/customer",
                "supplier-management": "/purchase/supplier",
            };
            const path = routes[actionKey];
            if (path) {
                router.push(path);
            } else {
                ElMessage.warning(`未知操作: ${actionKey}`);
            }
        };

        // 待办事项 — 跳转到具体订单
        const handleTodoAction = (row) => {
            if (row.type === "purchase" && row.id) {
                router.push({ path: "/purchase/order", query: { id: row.id } });
            } else if (row.type === "sales" && row.id) {
                router.push({ path: "/sales/order", query: { id: row.id } });
            }
        };

        const refreshData = () => {
            fetchDashboardData();
            fetchTodoList();
        };

        onMounted(() => {
            fetchDashboardData();
            fetchTodoList();
            window.addEventListener("resize", handleResize);
        });

        onBeforeUnmount(() => {
            window.removeEventListener("resize", handleResize);
            salesChartInstance?.dispose();
            inventoryChartInstance?.dispose();
        });

        return {
            dateRange,
            loading,
            kpiCards,
            quickActions,
            todoList,
            salesChartRef,
            inventoryChartRef,
            getTrendState,
            getTrendWidth,
            getTrendText,
            getPriorityTagType,
            handleQuickAction,
            handleTodoAction,
            refreshData,
        };
    },
};
</script>

<style scoped>
.dashboard-container {
    padding: 20px;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.kpi-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.kpi-card {
    display: flex;
    flex-direction: column;
}

.kpi-card :deep(.el-card__body) {
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.value {
    font-size: 24px;
    font-weight: bold;
    color: #303133;
    margin-bottom: 5px;
}

.subtitle {
    font-size: 14px;
    color: #909399;
}

.trend-visual {
    margin-top: 12px;
}

.trend-bar-bg {
    width: 100%;
    height: 10px;
    border-radius: 999px;
    background: #ebeef5;
    overflow: hidden;
}

.trend-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.trend-bar-fill.up {
    background: linear-gradient(90deg, #67c23a 0%, #95d475 100%);
}

.trend-bar-fill.down {
    background: linear-gradient(90deg, #f56c6c 0%, #fab6b6 100%);
}

.trend-bar-fill.none {
    background: repeating-linear-gradient(
        45deg,
        #c0c4cc 0,
        #c0c4cc 6px,
        #dcdfe6 6px,
        #dcdfe6 12px
    );
}

.trend-text {
    margin-top: 8px;
    font-size: 12px;
    color: #606266;
}

.charts-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.chart-card {
    min-height: 450px;
}

.chart-card :deep(.el-card__body) {
    overflow: hidden;
}

.chart-container {
    width: 100%;
    height: 400px;
}

.quick-actions-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.quick-actions-card {
    min-height: 300px;
}

.quick-actions-card :deep(.el-card__body) {
    overflow: hidden;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.action-item {
    text-align: center;
    cursor: pointer;
    padding: 15px;
    border-radius: 4px;
    transition: all 0.3s;
}

.action-item:hover {
    background-color: #f5f7fa;
}

.action-icon {
    margin-bottom: 10px;
}

.action-title {
    font-size: 14px;
    color: #606266;
}

.todo-card {
    min-height: 300px;
}

.todo-card :deep(.el-card__body) {
    overflow: hidden;
}
</style>
