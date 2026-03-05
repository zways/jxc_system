<template>
    <div class="reports-container">
        <div class="reports-header">
            <h2>报表中心</h2>
            <div class="header-actions">
                <el-date-picker
                    v-model="dateRange"
                    type="daterange"
                    range-separator="至"
                    start-placeholder="开始日期"
                    end-placeholder="结束日期"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    @change="handleDateChange"
                />
                <el-button type="primary" @click="refreshData" :loading="loading">
                    <el-icon><Refresh /></el-icon>
                    刷新数据
                </el-button>
                <el-dropdown @command="handleExport">
                    <el-button>
                        导出报表
                        <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="sales">导出销售报表</el-dropdown-item>
                            <el-dropdown-item command="purchase">导出采购报表</el-dropdown-item>
                            <el-dropdown-item command="inventory">导出库存报表</el-dropdown-item>
                            <el-dropdown-item command="finance">导出财务报表</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>

        <!-- 概览卡片 -->
        <div class="overview-cards">
            <el-card class="overview-card sales-card">
                <div class="card-icon">
                    <el-icon :size="32" color="#409EFF"><ShoppingCart /></el-icon>
                </div>
                <div class="card-info">
                    <div class="card-title">销售总额</div>
                    <div class="card-value">¥{{ formatNumber(overview.sales?.total_sales || 0) }}</div>
                    <div class="card-extra">
                        <span>订单数：{{ overview.sales?.total_orders || 0 }}</span>
                        <el-tag :type="overview.sales?.growth >= 0 ? 'success' : 'danger'" size="small">
                            {{ overview.sales?.growth >= 0 ? '+' : '' }}{{ overview.sales?.growth || 0 }}%
                        </el-tag>
                    </div>
                </div>
            </el-card>
            <el-card class="overview-card purchase-card">
                <div class="card-icon">
                    <el-icon :size="32" color="#E6A23C"><Goods /></el-icon>
                </div>
                <div class="card-info">
                    <div class="card-title">采购总额</div>
                    <div class="card-value">¥{{ formatNumber(overview.purchases?.total_purchases || 0) }}</div>
                    <div class="card-extra">
                        <span>订单数：{{ overview.purchases?.total_orders || 0 }}</span>
                    </div>
                </div>
            </el-card>
            <el-card class="overview-card inventory-card">
                <div class="card-icon">
                    <el-icon :size="32" color="#67C23A"><Box /></el-icon>
                </div>
                <div class="card-info">
                    <div class="card-title">库存总值</div>
                    <div class="card-value">¥{{ formatNumber(overview.inventory?.total_value || 0) }}</div>
                    <div class="card-extra">
                        <span>商品数：{{ overview.inventory?.product_count || 0 }}</span>
                    </div>
                </div>
            </el-card>
            <el-card class="overview-card finance-card">
                <div class="card-icon">
                    <el-icon :size="32" color="#F56C6C"><Wallet /></el-icon>
                </div>
                <div class="card-info">
                    <div class="card-title">净资金头寸</div>
                    <div class="card-value" :class="{ 'negative': overview.finance?.net_position < 0 }">
                        ¥{{ formatNumber(overview.finance?.net_position || 0) }}
                    </div>
                    <div class="card-extra">
                        <span>应收：¥{{ formatNumber(overview.finance?.receivable || 0) }}</span>
                        <span>应付：¥{{ formatNumber(overview.finance?.payable || 0) }}</span>
                    </div>
                </div>
            </el-card>
        </div>

        <!-- 报表选项卡 -->
        <el-tabs v-model="activeTab" @tab-change="handleTabChange" class="reports-tabs">
            <!-- 销售报表 -->
            <el-tab-pane label="销售报表" name="sales">
                <div class="tab-content">
                    <!-- 销售汇总 -->
                    <el-row :gutter="20" class="summary-row">
                        <el-col :span="6">
                            <el-statistic title="订单总数" :value="salesReport.summary?.total_orders || 0" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="销售总额" :value="salesReport.summary?.total_sales || 0" prefix="¥" :precision="2" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="平均订单金额" :value="salesReport.summary?.avg_order_value || 0" prefix="¥" :precision="2" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="总折扣" :value="salesReport.summary?.total_discount || 0" prefix="¥" :precision="2" />
                        </el-col>
                    </el-row>

                    <el-row :gutter="20">
                        <!-- 销售趋势 -->
                        <el-col :span="16">
                            <el-card>
                                <template #header>
                                    <div class="card-header">
                                        <span>销售趋势</span>
                                        <el-radio-group v-model="salesGroupBy" size="small" @change="fetchSalesReport">
                                            <el-radio-button value="day">按日</el-radio-button>
                                            <el-radio-button value="week">按周</el-radio-button>
                                            <el-radio-button value="month">按月</el-radio-button>
                                        </el-radio-group>
                                    </div>
                                </template>
                                <el-table :data="salesReport.trend || []" stripe style="width: 100%" max-height="400">
                                    <el-table-column prop="period" label="时间段" min-width="120" />
                                    <el-table-column prop="order_count" label="订单数" min-width="100" />
                                    <el-table-column prop="total_amount" label="销售金额" min-width="150">
                                        <template #default="{ row }">
                                            ¥{{ formatNumber(row.total_amount) }}
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="订单均价" min-width="120">
                                        <template #default="{ row }">
                                            ¥{{ row.order_count ? formatNumber(row.total_amount / row.order_count) : '0.00' }}
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>

                        <!-- 订单类型分布 -->
                        <el-col :span="8">
                            <el-card>
                                <template #header>订单类型分布</template>
                                <div class="distribution-list">
                                    <div v-for="item in salesReport.order_type_distribution || []" :key="item.order_type" class="distribution-item">
                                        <div class="dist-header">
                                            <span class="dist-label">{{ getOrderTypeLabel(item.order_type) }}</span>
                                            <span class="dist-count">{{ item.count }}单</span>
                                        </div>
                                        <el-progress 
                                            :percentage="calculatePercentage(item.count, getTotalOrderCount('order_type'))"
                                            :color="getOrderTypeColor(item.order_type)"
                                        />
                                        <div class="dist-amount">金额：¥{{ formatNumber(item.total_amount) }}</div>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 客户销售排行 -->
                    <el-card style="margin-top: 20px">
                        <template #header>客户销售排行 TOP 10</template>
                        <el-table :data="salesReport.top_customers || []" stripe>
                            <el-table-column type="index" label="排名" width="80" />
                            <el-table-column label="客户信息" min-width="200">
                                <template #default="{ row }">
                                    <div>{{ row.customer?.name || '-' }}</div>
                                    <div class="text-muted">{{ row.customer?.customer_code || '-' }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="order_count" label="订单数量" min-width="100" />
                            <el-table-column label="销售金额" min-width="150">
                                <template #default="{ row }">
                                    ¥{{ formatNumber(row.total_amount) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="占比" min-width="150">
                                <template #default="{ row }">
                                    <el-progress 
                                        :percentage="calculatePercentage(row.total_amount, salesReport.summary?.total_sales)"
                                        :stroke-width="10"
                                    />
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- 采购报表 -->
            <el-tab-pane label="采购报表" name="purchase">
                <div class="tab-content">
                    <!-- 采购汇总 -->
                    <el-row :gutter="20" class="summary-row">
                        <el-col :span="6">
                            <el-statistic title="订单总数" :value="purchaseReport.summary?.total_orders || 0" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="采购总额" :value="purchaseReport.summary?.total_purchases || 0" prefix="¥" :precision="2" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="平均订单金额" :value="purchaseReport.summary?.avg_order_value || 0" prefix="¥" :precision="2" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="总运费" :value="purchaseReport.summary?.total_shipping || 0" prefix="¥" :precision="2" />
                        </el-col>
                    </el-row>

                    <el-row :gutter="20">
                        <!-- 采购趋势 -->
                        <el-col :span="16">
                            <el-card>
                                <template #header>
                                    <div class="card-header">
                                        <span>采购趋势</span>
                                        <el-radio-group v-model="purchaseGroupBy" size="small" @change="fetchPurchaseReport">
                                            <el-radio-button value="day">按日</el-radio-button>
                                            <el-radio-button value="week">按周</el-radio-button>
                                            <el-radio-button value="month">按月</el-radio-button>
                                        </el-radio-group>
                                    </div>
                                </template>
                                <el-table :data="purchaseReport.trend || []" stripe style="width: 100%" max-height="400">
                                    <el-table-column prop="period" label="时间段" min-width="120" />
                                    <el-table-column prop="order_count" label="订单数" min-width="100" />
                                    <el-table-column label="采购金额" min-width="150">
                                        <template #default="{ row }">
                                            ¥{{ formatNumber(row.total_amount) }}
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>

                        <!-- 订单状态分布 -->
                        <el-col :span="8">
                            <el-card>
                                <template #header>订单状态分布</template>
                                <div class="distribution-list">
                                    <div v-for="item in purchaseReport.status_distribution || []" :key="item.status" class="distribution-item">
                                        <div class="dist-header">
                                            <span class="dist-label">{{ getPurchaseStatusLabel(item.status) }}</span>
                                            <span class="dist-count">{{ item.count }}单</span>
                                        </div>
                                        <el-progress 
                                            :percentage="calculatePercentage(item.count, getTotalPurchaseStatusCount())"
                                            :color="getStatusColor(item.status)"
                                        />
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 供应商采购排行 -->
                    <el-card style="margin-top: 20px">
                        <template #header>供应商采购排行 TOP 10</template>
                        <el-table :data="purchaseReport.top_suppliers || []" stripe>
                            <el-table-column type="index" label="排名" width="80" />
                            <el-table-column label="供应商信息" min-width="200">
                                <template #default="{ row }">
                                    <div>{{ row.supplier?.name || '-' }}</div>
                                    <div class="text-muted">{{ row.supplier?.supplier_code || '-' }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="order_count" label="订单数量" min-width="100" />
                            <el-table-column label="采购金额" min-width="150">
                                <template #default="{ row }">
                                    ¥{{ formatNumber(row.total_amount) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="占比" min-width="150">
                                <template #default="{ row }">
                                    <el-progress 
                                        :percentage="calculatePercentage(row.total_amount, purchaseReport.summary?.total_purchases)"
                                        :stroke-width="10"
                                        color="#E6A23C"
                                    />
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- 库存报表 -->
            <el-tab-pane label="库存报表" name="inventory">
                <div class="tab-content">
                    <!-- 库存汇总 -->
                    <el-row :gutter="20" class="summary-row">
                        <el-col :span="6">
                            <el-statistic title="商品总数" :value="inventoryReport.product_stats?.total_products || 0" suffix="件" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="商品分类" :value="inventoryReport.product_stats?.total_categories || 0" suffix="类" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="库存总值" :value="inventoryReport.product_stats?.total_value || 0" prefix="¥" :precision="2" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="仓库数量" :value="(inventoryReport.warehouse_distribution || []).length" suffix="个" />
                        </el-col>
                    </el-row>

                    <el-row :gutter="20">
                        <!-- 分类库存 -->
                        <el-col :span="12">
                            <el-card>
                                <template #header>分类库存分布</template>
                                <el-table :data="inventoryReport.inventory_by_category || []" stripe max-height="350">
                                    <el-table-column label="分类名称" min-width="150">
                                        <template #default="{ row }">
                                            {{ row.category?.name || '未分类' }}
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="product_count" label="商品数量" min-width="100" />
                                    <el-table-column label="库存价值" min-width="120">
                                        <template #default="{ row }">
                                            ¥{{ formatNumber(row.total_value) }}
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>

                        <!-- 仓库分布 -->
                        <el-col :span="12">
                            <el-card>
                                <template #header>仓库信息</template>
                                <el-table :data="inventoryReport.warehouse_distribution || []" stripe max-height="350">
                                    <el-table-column prop="code" label="仓库编码" min-width="100" />
                                    <el-table-column prop="name" label="仓库名称" min-width="120" />
                                    <el-table-column label="类型" min-width="80">
                                        <template #default="{ row }">
                                            <el-tag :type="getWarehouseTypeTag(row.type)" size="small">
                                                {{ getWarehouseTypeLabel(row.type) }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="location" label="位置" min-width="150" />
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 库存预警 -->
                    <el-card style="margin-top: 20px">
                        <template #header>
                            <div class="card-header">
                                <span>库存商品列表</span>
                                <el-tag type="warning" size="small">低库存预警</el-tag>
                            </div>
                        </template>
                        <el-table :data="inventoryReport.low_stock_products || []" stripe>
                            <el-table-column prop="code" label="商品编码" min-width="120" />
                            <el-table-column prop="name" label="商品名称" min-width="150" />
                            <el-table-column prop="unit" label="单位" min-width="80" />
                            <el-table-column prop="min_stock" label="最低库存" min-width="100" />
                            <el-table-column prop="max_stock" label="最高库存" min-width="100" />
                            <el-table-column label="采购价" min-width="100">
                                <template #default="{ row }">
                                    ¥{{ formatNumber(row.purchase_price) }}
                                </template>
                            </el-table-column>
                            <el-table-column label="零售价" min-width="100">
                                <template #default="{ row }">
                                    ¥{{ formatNumber(row.retail_price) }}
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>

                    <!-- 库存变动类型统计 -->
                    <el-card style="margin-top: 20px">
                        <template #header>库存变动类型统计</template>
                        <div class="transaction-stats">
                            <div v-for="item in inventoryReport.transaction_type_stats || []" :key="item.transaction_type" class="stat-item">
                                <el-tag :type="getTransactionTypeTag(item.transaction_type)" size="large">
                                    {{ getTransactionTypeLabel(item.transaction_type) }}
                                </el-tag>
                                <div class="stat-value">{{ item.count }} 次</div>
                                <div class="stat-quantity">数量：{{ formatNumber(item.total_quantity) }}</div>
                            </div>
                        </div>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- 财务报表 -->
            <el-tab-pane label="财务报表" name="finance">
                <div class="tab-content">
                    <!-- 财务汇总 -->
                    <el-row :gutter="20" class="summary-row">
                        <el-col :span="6">
                            <el-statistic title="应收账款" :value="financeReport.summary?.total_receivable || 0" prefix="¥" :precision="2" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="应付账款" :value="financeReport.summary?.total_payable || 0" prefix="¥" :precision="2" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic 
                                title="净资金头寸" 
                                :value="financeReport.summary?.net_position || 0" 
                                prefix="¥" 
                                :precision="2"
                                :value-style="{ color: (financeReport.summary?.net_position || 0) >= 0 ? '#67C23A' : '#F56C6C' }"
                            />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic 
                                title="逾期应收" 
                                :value="financeReport.summary?.overdue_receivable || 0" 
                                prefix="¥" 
                                :precision="2"
                                :value-style="{ color: '#F56C6C' }"
                            />
                        </el-col>
                    </el-row>

                    <el-row :gutter="20">
                        <!-- 应收账龄分析 -->
                        <el-col :span="12">
                            <el-card>
                                <template #header>应收账款账龄分析</template>
                                <div class="aging-analysis">
                                    <div class="aging-item">
                                        <span class="aging-label">未到期</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.receivable_aging?.current, 'receivable')"
                                            color="#67C23A"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.receivable_aging?.current || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">1-30天</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.receivable_aging?.['1_30_days'], 'receivable')"
                                            color="#E6A23C"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.receivable_aging?.['1_30_days'] || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">31-60天</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.receivable_aging?.['31_60_days'], 'receivable')"
                                            color="#F56C6C"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.receivable_aging?.['31_60_days'] || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">61-90天</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.receivable_aging?.['61_90_days'], 'receivable')"
                                            color="#F56C6C"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.receivable_aging?.['61_90_days'] || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">90天以上</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.receivable_aging?.over_90_days, 'receivable')"
                                            color="#909399"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.receivable_aging?.over_90_days || 0) }}</span>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>

                        <!-- 应付账龄分析 -->
                        <el-col :span="12">
                            <el-card>
                                <template #header>应付账款账龄分析</template>
                                <div class="aging-analysis">
                                    <div class="aging-item">
                                        <span class="aging-label">未到期</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.payable_aging?.current, 'payable')"
                                            color="#67C23A"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.payable_aging?.current || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">1-30天</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.payable_aging?.['1_30_days'], 'payable')"
                                            color="#E6A23C"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.payable_aging?.['1_30_days'] || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">31-60天</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.payable_aging?.['31_60_days'], 'payable')"
                                            color="#F56C6C"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.payable_aging?.['31_60_days'] || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">61-90天</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.payable_aging?.['61_90_days'], 'payable')"
                                            color="#F56C6C"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.payable_aging?.['61_90_days'] || 0) }}</span>
                                    </div>
                                    <div class="aging-item">
                                        <span class="aging-label">90天以上</span>
                                        <el-progress 
                                            :percentage="calculateAgingPercentage(financeReport.payable_aging?.over_90_days, 'payable')"
                                            color="#909399"
                                        />
                                        <span class="aging-value">¥{{ formatNumber(financeReport.payable_aging?.over_90_days || 0) }}</span>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="20" style="margin-top: 20px">
                        <!-- 应收账款客户排行 -->
                        <el-col :span="12">
                            <el-card>
                                <template #header>应收账款客户排行</template>
                                <el-table :data="financeReport.top_receivable_customers || []" stripe max-height="300">
                                    <el-table-column type="index" label="排名" width="60" />
                                    <el-table-column label="客户名称" min-width="150">
                                        <template #default="{ row }">
                                            {{ row.customer?.name || '-' }}
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="count" label="单据数" min-width="80" />
                                    <el-table-column label="欠款金额" min-width="120">
                                        <template #default="{ row }">
                                            <span class="text-danger">¥{{ formatNumber(row.total_balance) }}</span>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>

                        <!-- 应付账款供应商排行 -->
                        <el-col :span="12">
                            <el-card>
                                <template #header>应付账款供应商排行</template>
                                <el-table :data="financeReport.top_payable_suppliers || []" stripe max-height="300">
                                    <el-table-column type="index" label="排名" width="60" />
                                    <el-table-column label="供应商名称" min-width="150">
                                        <template #default="{ row }">
                                            {{ row.supplier?.name || '-' }}
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="count" label="单据数" min-width="80" />
                                    <el-table-column label="应付金额" min-width="120">
                                        <template #default="{ row }">
                                            <span class="text-warning">¥{{ formatNumber(row.total_balance) }}</span>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage } from 'element-plus';
import {
    ShoppingCart,
    Goods,
    Box,
    Wallet,
    Refresh,
    ArrowDown
} from '@element-plus/icons-vue';

export default {
    name: 'ReportsView',
    components: {
        ShoppingCart,
        Goods,
        Box,
        Wallet,
        Refresh,
        ArrowDown
    },
    setup() {
        const loading = ref(false);
        const activeTab = ref('sales');
        const salesGroupBy = ref('day');
        const purchaseGroupBy = ref('day');

        // 日期范围默认为本月
        const now = new Date();
        const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        const dateRange = ref([
            startOfMonth.toISOString().split('T')[0],
            now.toISOString().split('T')[0]
        ]);

        // 报表数据
        const overview = reactive({});
        const salesReport = reactive({});
        const purchaseReport = reactive({});
        const inventoryReport = reactive({});
        const financeReport = reactive({});

        // 格式化数字
        const formatNumber = (num) => {
            if (num === null || num === undefined) return '0.00';
            return Number(num).toLocaleString('zh-CN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        // 计算百分比
        const calculatePercentage = (value, total) => {
            if (!total || total === 0) return 0;
            return Math.round((value / total) * 100);
        };

        // 获取订单类型标签
        const getOrderTypeLabel = (type) => {
            const labels = {
                retail: '零售',
                wholesale: '批发',
                pos: 'POS销售'
            };
            return labels[type] || type;
        };

        // 获取订单类型颜色
        const getOrderTypeColor = (type) => {
            const colors = {
                retail: '#409EFF',
                wholesale: '#67C23A',
                pos: '#E6A23C'
            };
            return colors[type] || '#909399';
        };

        // 获取采购状态标签
        const getPurchaseStatusLabel = (status) => {
            const labels = {
                pending: '待处理',
                confirmed: '已确认',
                received: '已收货',
                cancelled: '已取消'
            };
            return labels[status] || status;
        };

        // 获取状态颜色
        const getStatusColor = (status) => {
            const colors = {
                pending: '#E6A23C',
                confirmed: '#409EFF',
                received: '#67C23A',
                delivered: '#67C23A',
                cancelled: '#909399'
            };
            return colors[status] || '#909399';
        };

        // 获取总订单数
        const getTotalOrderCount = (type) => {
            if (type === 'order_type') {
                return (salesReport.order_type_distribution || []).reduce((sum, item) => sum + item.count, 0);
            }
            return 0;
        };

        // 获取采购状态总数
        const getTotalPurchaseStatusCount = () => {
            return (purchaseReport.status_distribution || []).reduce((sum, item) => sum + item.count, 0);
        };

        // 仓库类型标签
        const getWarehouseTypeLabel = (type) => {
            const labels = {
                normal: '常规仓库',
                frozen: '冷链仓库',
                liquid: '液体仓库'
            };
            return labels[type] || type;
        };

        const getWarehouseTypeTag = (type) => {
            const tags = {
                normal: 'info',
                frozen: 'primary',
                liquid: 'warning'
            };
            return tags[type] || 'info';
        };

        // 库存变动类型
        const getTransactionTypeLabel = (type) => {
            const labels = {
                in: '入库',
                out: '出库',
                adjust: '调整',
                transfer: '调拨'
            };
            return labels[type] || type;
        };

        const getTransactionTypeTag = (type) => {
            const tags = {
                in: 'success',
                out: 'danger',
                adjust: 'warning',
                transfer: 'info'
            };
            return tags[type] || 'info';
        };

        // 计算账龄百分比
        const calculateAgingPercentage = (value, type) => {
            const aging = type === 'receivable' ? financeReport.receivable_aging : financeReport.payable_aging;
            if (!aging) return 0;
            const total = Object.values(aging).reduce((sum, v) => sum + (v || 0), 0);
            if (total === 0) return 0;
            return Math.round(((value || 0) / total) * 100);
        };

        // 获取日期参数（防止 dateRange 为 null）
        const getDateParams = () => {
            if (dateRange.value && dateRange.value.length === 2) {
                return { start_date: dateRange.value[0], end_date: dateRange.value[1] };
            }
            // 默认本月
            const now = new Date();
            const s = new Date(now.getFullYear(), now.getMonth(), 1);
            return { start_date: s.toISOString().split('T')[0], end_date: now.toISOString().split('T')[0] };
        };

        // API 请求
        const fetchOverview = async () => {
            try {
                const params = getDateParams();
                const response = await window.axios.get('reports/overview', { params });
                if (response.data.success) {
                    Object.assign(overview, response.data.data);
                }
            } catch (error) {
                console.error('获取概览数据失败:', error);
            }
        };

        const fetchSalesReport = async () => {
            try {
                const params = {
                    ...getDateParams(),
                    group_by: salesGroupBy.value
                };
                const response = await window.axios.get('reports/sales', { params });
                if (response.data.success) {
                    Object.assign(salesReport, response.data.data);
                }
            } catch (error) {
                console.error('获取销售报表失败:', error);
            }
        };

        const fetchPurchaseReport = async () => {
            try {
                const params = {
                    ...getDateParams(),
                    group_by: purchaseGroupBy.value
                };
                const response = await window.axios.get('reports/purchase', { params });
                if (response.data.success) {
                    Object.assign(purchaseReport, response.data.data);
                }
            } catch (error) {
                console.error('获取采购报表失败:', error);
            }
        };

        const fetchInventoryReport = async () => {
            try {
                const response = await window.axios.get('reports/inventory');
                if (response.data.success) {
                    Object.assign(inventoryReport, response.data.data);
                }
            } catch (error) {
                console.error('获取库存报表失败:', error);
            }
        };

        const fetchFinanceReport = async () => {
            try {
                const params = getDateParams();
                const response = await window.axios.get('reports/finance', { params });
                if (response.data.success) {
                    Object.assign(financeReport, response.data.data);
                }
            } catch (error) {
                console.error('获取财务报表失败:', error);
            }
        };

        // 刷新数据
        const refreshData = async () => {
            loading.value = true;
            try {
                await Promise.all([
                    fetchOverview(),
                    fetchCurrentTabData()
                ]);
                ElMessage.success('数据刷新成功');
            } catch (error) {
                ElMessage.error('数据刷新失败');
            } finally {
                loading.value = false;
            }
        };

        // 获取当前选项卡数据
        const fetchCurrentTabData = async () => {
            switch (activeTab.value) {
                case 'sales':
                    await fetchSalesReport();
                    break;
                case 'purchase':
                    await fetchPurchaseReport();
                    break;
                case 'inventory':
                    await fetchInventoryReport();
                    break;
                case 'finance':
                    await fetchFinanceReport();
                    break;
            }
        };

        // 日期变更处理
        const handleDateChange = () => {
            refreshData();
        };

        // 选项卡切换
        const handleTabChange = (tabName) => {
            fetchCurrentTabData();
        };

        // 导出报表
        const handleExport = async (type) => {
            try {
                const dp = getDateParams();
                const params = {
                    report_type: type,
                    ...dp
                };
                const response = await window.axios.get('reports/export', { params });
                if (response.data.success) {
                    // 创建下载
                    const dataStr = JSON.stringify(response.data.data, null, 2);
                    const blob = new Blob([dataStr], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${type}_report_${dp.start_date}_${dp.end_date}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                    ElMessage.success('导出成功');
                }
            } catch (error) {
                ElMessage.error('导出失败');
            }
        };

        // 初始化
        onMounted(async () => {
            loading.value = true;
            try {
                await Promise.all([
                    fetchOverview(),
                    fetchSalesReport(),
                    fetchPurchaseReport(),
                    fetchInventoryReport(),
                    fetchFinanceReport()
                ]);
            } finally {
                loading.value = false;
            }
        });

        return {
            loading,
            activeTab,
            dateRange,
            salesGroupBy,
            purchaseGroupBy,
            overview,
            salesReport,
            purchaseReport,
            inventoryReport,
            financeReport,
            formatNumber,
            calculatePercentage,
            getOrderTypeLabel,
            getOrderTypeColor,
            getPurchaseStatusLabel,
            getStatusColor,
            getTotalOrderCount,
            getTotalPurchaseStatusCount,
            getWarehouseTypeLabel,
            getWarehouseTypeTag,
            getTransactionTypeLabel,
            getTransactionTypeTag,
            calculateAgingPercentage,
            refreshData,
            handleDateChange,
            handleTabChange,
            handleExport
        };
    }
};
</script>

<style scoped>
.reports-container {
    padding: 20px;
    background-color: #f5f7fa;
    min-height: 100vh;
}

.reports-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    background: #fff;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
}

.reports-header h2 {
    margin: 0;
    font-size: 20px;
    color: #303133;
}

.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* 概览卡片 */
.overview-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.overview-card {
    display: flex;
    align-items: center;
    padding: 20px;
}

.overview-card :deep(.el-card__body) {
    display: flex;
    align-items: center;
    width: 100%;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
}

.sales-card .card-icon {
    background: rgba(64, 158, 255, 0.1);
}

.purchase-card .card-icon {
    background: rgba(230, 162, 60, 0.1);
}

.inventory-card .card-icon {
    background: rgba(103, 194, 58, 0.1);
}

.finance-card .card-icon {
    background: rgba(245, 108, 108, 0.1);
}

.card-info {
    flex: 1;
}

.card-title {
    font-size: 14px;
    color: #909399;
    margin-bottom: 8px;
}

.card-value {
    font-size: 24px;
    font-weight: bold;
    color: #303133;
    margin-bottom: 8px;
}

.card-value.negative {
    color: #F56C6C;
}

.card-extra {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #909399;
}

/* 报表选项卡 */
.reports-tabs {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
}

.tab-content {
    padding: 10px 0;
}

.summary-row {
    margin-bottom: 20px;
    background: #f5f7fa;
    padding: 20px;
    border-radius: 8px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* 分布列表 */
.distribution-list {
    padding: 10px 0;
}

.distribution-item {
    margin-bottom: 20px;
}

.dist-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.dist-label {
    font-size: 14px;
    color: #606266;
}

.dist-count {
    font-size: 14px;
    color: #909399;
}

.dist-amount {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

/* 账龄分析 */
.aging-analysis {
    padding: 10px 0;
}

.aging-item {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
    gap: 12px;
}

.aging-label {
    width: 70px;
    font-size: 14px;
    color: #606266;
}

.aging-item .el-progress {
    flex: 1;
}

.aging-value {
    width: 100px;
    text-align: right;
    font-size: 14px;
    color: #303133;
    font-weight: 500;
}

/* 库存变动统计 */
.transaction-stats {
    display: flex;
    gap: 30px;
    padding: 20px 0;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 20px;
    font-weight: bold;
    color: #303133;
    margin: 12px 0 4px;
}

.stat-quantity {
    font-size: 12px;
    color: #909399;
}

/* 文本样式 */
.text-muted {
    color: #909399;
    font-size: 12px;
}

.text-danger {
    color: #F56C6C;
}

.text-warning {
    color: #E6A23C;
}

/* 响应式 */
@media (max-width: 1200px) {
    .overview-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .overview-cards {
        grid-template-columns: 1fr;
    }

    .reports-header {
        flex-direction: column;
        gap: 15px;
    }

    .header-actions {
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>
