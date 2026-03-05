<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 兼容历史命名：AccountsPayable / AccountPayable 共用同一张表。
 *
 * 说明：
 * - 数据表 migrations 中包含 softDeletes()，因此以带 SoftDeletes 的 `AccountPayable` 为主实现
 * - 这里保留 `AccountsPayable` 作为别名/适配层，避免全项目大范围改名
 */
class AccountsPayable extends AccountPayable
{
    use HasFactory;
}
