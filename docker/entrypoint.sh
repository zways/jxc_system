#!/bin/sh
set -e

echo "========================================"
echo "  进销存系统 - 容器启动"
echo "========================================"

# 等待数据库就绪（如果配置了 MySQL）
if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ]; then
    echo "等待数据库就绪..."
    max_retries=30
    counter=0
    while ! php -r "try { new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306}', '${DB_USERNAME}', '${DB_PASSWORD}'); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
        counter=$((counter + 1))
        if [ $counter -ge $max_retries ]; then
            echo "数据库连接超时，继续启动..."
            break
        fi
        echo "  数据库未就绪，等待中... ($counter/$max_retries)"
        sleep 2
    done
    echo "数据库已就绪"
fi

# Laravel 初始化
echo "缓存配置..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 运行迁移（生产环境自动迁移，可通过 RUN_MIGRATIONS=false 禁用）
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "运行数据库迁移..."
    php artisan migrate --force --no-interaction
fi

# 创建存储链接
php artisan storage:link 2>/dev/null || true

# 若使用独立 queue 服务，则不在本容器内运行队列与定时任务
if [ "${RUN_QUEUE_IN_CONTAINER}" = "false" ]; then
    echo "使用独立 queue 服务，已切换为仅 Web 配置"
    cp /etc/supervisord-app-only.conf /etc/supervisord.conf
fi

echo "========================================"
echo "  启动完成！监听端口 80"
echo "========================================"

# 执行传入的命令
exec "$@"
