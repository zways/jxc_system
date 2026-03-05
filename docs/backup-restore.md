# 备份与恢复

本文说明进销存系统的备份策略、执行方式及恢复步骤。

## 备份内容

| 内容 | 说明 |
|------|------|
| **MySQL 全库** | 所有业务数据（企业、用户、商品、订单、库存、报表等），以 gzip 压缩的 `.sql.gz` 存放 |
| **存储文件** | 可选：`storage/app` 下的上传文件等，当前脚本未包含，可按需扩展 |

备份文件默认存放在 **`storage/app/backups/`**，命名格式：`mysql_YYYYMMDD_HHMMSS.sql.gz`。

## 自动备份（定时任务）

- **频率**：每日凌晨 2 点（应用时区，默认 `Asia/Shanghai`）
- **环境**：仅在 `production`、`staging` 下执行
- **保留**：默认保留最近 **7 天**，更早的自动删除

定时任务由 Laravel Scheduler 驱动。使用 Docker 时，**scheduler** 已在默认 supervisord 配置中运行（若使用独立 queue 服务且未在容器内跑 scheduler，需在宿主机或单独容器中跑 `php artisan schedule:run` 每分钟一次）。

## 手动执行备份

### 在容器内

```bash
# 使用默认保留天数（7 天）
php artisan backup:run

# 指定保留最近 14 天
php artisan backup:run --keep=14
```

或直接执行脚本（需已配置 `DB_*` 环境变量）：

```bash
/var/www/html/scripts/backup.sh
```

### 在宿主机（Docker）

```bash
docker compose exec app php artisan backup:run
```

备份文件会落在容器内 `storage/app/backups/`，若需持久化到宿主机，请为 `app` 服务挂载该目录，例如：

```yaml
volumes:
  - ./backups:/var/www/html/storage/app/backups
```

## 环境变量（可选）

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `BACKUP_DIR` | `storage/app/backups` | 备份存放目录（可为绝对路径） |
| `BACKUP_KEEP_DAYS` | `7` | 保留最近 N 天的备份，超期自动删除 |
| `BACKUP_ROOT` | `/var/www/html` | 项目根目录（脚本内用于定位路径） |

数据库连接使用与 Laravel 相同的 `DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`。

## 恢复步骤

### 1. 准备备份文件

将需要恢复的 `mysql_YYYYMMDD_HHMMSS.sql.gz` 放到可访问的位置（如宿主机或容器内）。

### 2. 停止应用（建议）

避免恢复过程中有新写入：

```bash
docker compose stop app queue
```

### 3. 解压并导入 MySQL

**方式 A：在 MySQL 容器内**

```bash
# 将备份文件复制进 mysql 容器（若在宿主机）
docker cp ./mysql_20260213_020001.sql.gz jxc-mysql:/tmp/

# 进入 mysql 容器
docker compose exec mysql sh

# 解压并导入（按实际库名、用户名修改）
gunzip -c /tmp/mysql_20260213_020001.sql.gz | mysql -u jxc_user -p jxc_system
```

**方式 B：在宿主机（已安装 mysql-client）**

```bash
gunzip -c mysql_20260213_020001.sql.gz | docker compose exec -T mysql mysql -u jxc_user -p jxc_system
```

输入 `DB_PASSWORD` 对应密码。

### 4. 启动应用

```bash
docker compose start app queue
```

### 5. 验证

登录系统检查关键业务数据、企业与用户是否正常。

## 建议

- **周期**：生产环境建议至少每日一次自动备份，重要变更前可额外执行一次 `backup:run`。
- **异地/离线**：将 `storage/app/backups` 挂载到宿主机后，用 cron 或其它工具将备份同步到另一台机器或对象存储，避免单机故障。
- **恢复演练**：定期在测试环境做一次从备份恢复的演练，确认流程与耗时。
