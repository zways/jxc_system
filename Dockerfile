# ============================================================
#  进销存系统 Docker 镜像
#  多阶段构建：前端打包 → PHP 运行时
# ============================================================

# ── Stage 1: 前端构建 ──────────────────────────────────────
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources/ ./resources/

RUN npm run build


# ── Stage 2: Composer 依赖安装 ──────────────────────────────
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev


# ── Stage 3: 生产运行时 ────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

# 使用国内镜像源，提高 apk 稳定性（特别是在中国大陆环境）
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
    && apk update

# 系统依赖（mariadb-client 用于备份脚本 mysqldump）
RUN apk add --no-cache \
    nginx \
    supervisor \
    mariadb-client \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    curl-dev \
    icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        bcmath \
        gd \
        intl \
        opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/pear

# 让 mysql CLI 默认关闭 SSL，避免 schema:load 时 ERROR 2026
RUN printf '#!/bin/sh\nexec /usr/bin/mysql --ssl=OFF "$@"\n' > /usr/local/bin/mysql \
    && chmod +x /usr/local/bin/mysql

# PHP 配置优化
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.interned_strings_buffer=64"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.jit=1255"; \
    echo "opcache.jit_buffer_size=128M"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
    echo "upload_max_filesize=50M"; \
    echo "post_max_size=60M"; \
    echo "memory_limit=256M"; \
    echo "max_execution_time=120"; \
    } > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# 复制应用代码
COPY --from=composer /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

# 创建必要目录并设置权限
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx 配置
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor 配置（默认含 queue + scheduler；RUN_QUEUE_IN_CONTAINER=false 时由 entrypoint 切换为 app-only）
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/supervisord-app-only.conf /etc/supervisord-app-only.conf

# 入口脚本（备份脚本 scripts/backup.sh 已随应用复制，需可执行）
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chmod +x /var/www/html/scripts/backup.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
