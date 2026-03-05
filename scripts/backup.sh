#!/bin/sh
# 进销存系统 - 数据备份脚本
# 可在容器内执行（需 DB_* 环境变量）或宿主机通过 docker exec 执行
#
# 备份内容：MySQL 全库导出（gzip）
# 可选：storage/app 目录（上传文件等）
#
# 用法：
#   容器内: /var/www/html/scripts/backup.sh
#   或: php artisan backup:run（通过 Artisan 调用本脚本）
#
# 环境变量：
#   BACKUP_DIR    备份存放目录，默认 storage/app/backups
#   BACKUP_KEEP_DAYS  保留最近 N 天，默认 7
#   DB_*          数据库连接（与 .env 一致）

set -e

# 项目根目录（脚本在 scripts/ 下）
ROOT="${BACKUP_ROOT:-/var/www/html}"
cd "$ROOT"

BACKUP_DIR="${BACKUP_DIR:-$ROOT/storage/app/backups}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-7}"
DATE=$(date +%Y%m%d_%H%M%S)
MYSQL_DUMP=""

# 检测运行环境
if [ -n "$DB_HOST" ] && [ -n "$DB_DATABASE" ]; then
    MYSQL_DUMP="yes"
fi

if [ -z "$MYSQL_DUMP" ]; then
    echo "[backup] 未配置 DB_HOST/DB_DATABASE，跳过 MySQL 备份"
    exit 0
fi

mkdir -p "$BACKUP_DIR"

# ── MySQL 备份 ────────────────────────────────────────
DB_FILE="$BACKUP_DIR/mysql_${DATE}.sql.gz"
echo "[backup] 正在备份 MySQL -> $DB_FILE"

export MYSQL_PWD="${DB_PASSWORD:-}"
mysqldump -h "${DB_HOST:-127.0.0.1}" \
    -P "${DB_PORT:-3306}" \
    -u "${DB_USERNAME:-root}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    "${DB_DATABASE}" 2>/dev/null | gzip -c > "$DB_FILE"
unset MYSQL_PWD

if [ -f "$DB_FILE" ] && [ -s "$DB_FILE" ]; then
    echo "[backup] MySQL 备份完成: $DB_FILE"
else
    echo "[backup] 警告: MySQL 备份可能失败，请检查 $DB_FILE" >&2
    rm -f "$DB_FILE"
fi

# ── 清理过期备份（按修改时间保留最近 N 天）────────────
echo "[backup] 清理 $BACKUP_KEEP_DAYS 天前的备份..."
find "$BACKUP_DIR" -name "mysql_*.sql.gz" -type f -mtime +$BACKUP_KEEP_DAYS -delete 2>/dev/null || true

echo "[backup] 完成"
