# 进销存管理系统 API 文档

## 概述

本系统提供了一套完整的进销存管理功能，包括商品管理、供应商管理、客户管理、采购管理、销售管理、库存管理等功能。

## API 基础信息

-   **API 版本**: v1
-   **API 根路径**: `/api/v1`
-   **内容类型**: `application/json`

## 认证

所有 API 请求都需要认证。在请求头中添加以下字段：

```
Authorization: Bearer {your-token-here}
Content-Type: application/json
```

## 资源接口

### 产品管理

#### 获取产品列表

-   **GET** `/api/v1/products`
-   参数：
    -   `search` (可选): 搜索关键词
    -   `category_id` (可选): 按分类筛选
    -   `is_active` (可选): 按状态筛选
    -   `page` (可选): 页码
    -   `per_page` (可选): 每页数量

#### 创建产品

-   **POST** `/api/v1/products`
-   请求体：

```json
{
    "code": "PROD001",
    "name": "产品名称",
    "description": "产品描述",
    "category_id": 1,
    "barcode": "1234567890123",
    "specification": "规格",
    "unit": "个",
    "second_unit": "箱",
    "conversion_rate": 20,
    "purchase_price": 100,
    "retail_price": 150,
    "wholesale_price": 130,
    "min_stock": 10,
    "max_stock": 100,
    "track_serial": false,
    "track_batch": false,
    "is_active": true
}
```

#### 获取单个产品

-   **GET** `/api/v1/products/{id}`

#### 更新产品

-   **PUT** `/api/v1/products/{id}`
-   请求体同创建产品

#### 删除产品

-   **DELETE** `/api/v1/products/{id}`

### 供应商管理

#### 获取供应商列表

-   **GET** `/api/v1/suppliers`
-   参数：
    -   `search` (可选): 搜索关键词
    -   `is_active` (可选): 按状态筛选
    -   `page` (可选): 页码
    -   `per_page` (可选): 每页数量

#### 创建供应商

-   **POST** `/api/v1/suppliers`
-   请求体：

```json
{
    "supplier_code": "SUP001",
    "name": "供应商名称",
    "contact_person": "联系人",
    "phone": "13800138000",
    "email": "supplier@example.com",
    "address": "供应商地址",
    "tax_number": "税号",
    "credit_limit": 50000,
    "payment_terms": "付款条件",
    "rating": 4,
    "notes": "备注",
    "is_active": true
}
```

### 客户管理

#### 获取客户列表

-   **GET** `/api/v1/customers`
-   参数：
    -   `search` (可选): 搜索关键词
    -   `is_active` (可选): 按状态筛选
    -   `page` (可选): 页码
    -   `per_page` (可选): 每页数量

#### 创建客户

-   **POST** `/api/v1/customers`
-   请求体：

```json
{
    "customer_code": "CUST001",
    "name": "客户名称",
    "contact_person": "联系人",
    "phone": "13800138000",
    "email": "customer@example.com",
    "address": "客户地址",
    "tax_number": "税号",
    "credit_limit": 50000,
    "customer_level": "VIP客户",
    "payment_terms": "付款条件",
    "rating": 4,
    "notes": "备注",
    "is_active": true
}
```

### 仓库管理

#### 获取仓库列表

-   **GET** `/api/v1/warehouses`
-   参数：
    -   `search` (可选): 搜索关键词
    -   `is_active` (可选): 按状态筛选
    -   `page` (可选): 页码
    -   `per_page` (可选): 每页数量

#### 创建仓库

-   **POST** `/api/v1/warehouses`
-   请求体：

```json
{
    "code": "WH001",
    "name": "仓库名称",
    "location": "仓库位置",
    "manager": "仓库管理员",
    "description": "仓库描述",
    "type": "normal",
    "is_active": true,
    "notes": "备注"
}
```

### 产品分类管理

#### 获取分类列表

-   **GET** `/api/v1/product-categories`
-   参数：
    -   `search` (可选): 搜索关键词
    -   `is_active` (可选): 按状态筛选
    -   `page` (可选): 页码
    -   `per_page` (可选): 每页数量

#### 创建分类

-   **POST** `/api/v1/product-categories`
-   请求体：

```json
{
    "name": "分类名称",
    "description": "分类描述",
    "parent_id": null,
    "level": 1,
    "sort_order": 0,
    "is_active": true
}
```

## 系统测试接口

#### 测试系统功能

-   **GET** `/api/v1/test-system`
-   返回系统当前数据统计信息

## 响应格式

成功响应格式：

```json
{
    "success": true,
    "data": {},
    "message": "操作成功信息"
}
```

错误响应格式：

```json
{
    "success": false,
    "message": "错误信息",
    "errors": {}
}
```

## 注意事项

1. 所有价格字段均为数值类型
2. 所有外键字段需确保关联记录存在
3. 部分字段有唯一性约束，请避免重复
4. 日期格式为 `YYYY-MM-DD`
