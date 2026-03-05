# 进销存管理系统 — 部署说明文档

> 最后更新：2026-02-13

---

## 目录

- [1. 系统架构概览](#1-系统架构概览)
- [2. 环境要求](#2-环境要求)
- [3. 本地开发环境搭建](#3-本地开发环境搭建)
- [4. 环境变量配置详解](#4-环境变量配置详解)
- [5. 数据库部署](#5-数据库部署)
- [6. Redis 配置](#6-redis-配置)
- [7. 生产环境部署（裸机 / 云服务器）](#7-生产环境部署裸机--云服务器)
- [8. Docker 容器部署](#8-docker-容器部署)
- [9. 队列 Worker 配置](#9-队列-worker-配置)
- [10. Sentry 错误监控](#10-sentry-错误监控)
- [11. Nginx 反向代理配置](#11-nginx-反向代理配置)
- [12. HTTPS / SSL 证书](#12-https--ssl-证书)
- [13. 安全加固清单](#13-安全加固清单)
- [14. 监控与健康检查](#14-监控与健康检查)
- [15. 备份与恢复](#15-备份与恢复)
- [16. 更新与回滚](#16-更新与回滚)
- [17. 性能调优](#17-性能调优)
- [18. 故障排查](#18-故障排查)
- [附录 A：完整 .env 生产模板](#附录-a完整-env-生产模板)
- [附录 B：deploy.sh 脚本用法](#附录-b部署脚本-deploysh-用法)
- [附录 C：API 路由速查表](#附录-capi-路由速查表)

---

## 1. 系统架构概览

```
┌─────────────┐     ┌──────────────┐     ┌───────────┐
│   浏览器     │────▶│  Nginx       │────▶│  PHP-FPM  │
│  (Vue 3 SPA)│     │  反向代理     │     │  Laravel  │
└─────────────┘     └──────────────┘     └─────┬─────┘
                                               │
          ┌────────────────────────────────────┼────────────────────────────────────┐
          │                    │                │                │                    │
     ┌────▼────┐          ┌────▼────┐     ┌─────▼─────┐     ┌─────▼─────┐      ┌─────▼─────┐
     │  MySQL  │          │  Queue  │     │   Redis   │     │  Sentry   │      │ 定时任务   │
     │  8.0+   │          │ Worker  │     │   7.x    │     │  错误监控  │      │(可选)     │
     └─────────┘          └─────────┘     └─────┬─────┘     └───────────┘      └───────────┘
                        (审计日志等异步)              │
                                         ┌──────────┴──────────┐
                                         │ 缓存 / 会话 / 队列   │
                                         └─────────────────────┘
```
Docker 部署时：Queue Worker 为独立容器 `jxc-queue`；裸机部署时可为 Supervisor 进程或与 Web 同机。

**技术栈：**

| 层级 | 技术 | 版本 |
|------|------|------|
| 前端 | Vue 3 + Element Plus + Tailwind CSS | Vue 3.4 / Element Plus 2.7 / Tailwind 4 |
| 构建 | Vite | 7.x |
| 后端 | Laravel (PHP) | Laravel 12.x / PHP 8.2+ |
| 数据库 | MySQL | 8.0+ |
| 缓存/会话/队列 | Redis | 7.x |
| 错误监控 | Sentry | sentry-laravel 4.x |
| Web 服务器 | Nginx | 1.24+ |
| 容器化 | Docker + Docker Compose | 可选 |

**核心特性：**
- 多企业架构（按门店隔离）
- RBAC 权限控制（角色 + 权限，Redis 缓存）
- API Token 认证
- 审计日志（队列异步写入）
- 登录限流 + API 限流

---

## 2. 环境要求

### 2.1 最低配置（开发环境）

| 项目 | 要求 |
|------|------|
| 操作系统 | macOS / Linux / Windows (WSL2) |
| PHP | >= 8.2，需要扩展：`pdo_mysql`, `mbstring`, `xml`, `bcmath`, `gd`, `intl`, `redis` (phpredis) |
| Composer | >= 2.0 |
| Node.js | >= 20.x（构建前端） |
| npm | >= 10.x |
| MySQL | >= 8.0 |
| Redis | >= 6.0 |

### 2.2 推荐配置（生产环境）

| 项目 | 推荐 |
|------|------|
| CPU | 2 核+ |
| 内存 | 4 GB+（MySQL 2G + PHP 1G + Redis 256M + 系统 768M） |
| 磁盘 | 40 GB+ SSD |
| PHP | 8.2 或 8.3，开启 OPcache + JIT |
| MySQL | 8.0，InnoDB Buffer Pool 建议 1~2G |
| Redis | 7.x，最大内存 256MB，LRU 淘汰 |
| Nginx | 1.24+ |

### 2.3 PHP 扩展检查

```bash
# 检查已安装的 PHP 扩展
php -m | grep -E "(pdo_mysql|mbstring|xml|bcmath|gd|intl|redis|opcache)"

# 如果缺少扩展，Ubuntu/Debian 安装命令：
sudo apt install php8.2-mysql php8.2-mbstring php8.2-xml php8.2-bcmath \
    php8.2-gd php8.2-intl php8.2-redis php8.2-opcache php8.2-curl

# macOS (Homebrew)：
brew install php@8.2
pecl install redis
```

---

## 3. 本地开发环境搭建

### 3.1 快速启动（推荐）

```bash
# 1. 克隆代码
git clone <repository-url>
cd jxc_system

# 2. 一键安装（自动安装依赖、生成密钥、运行迁移、构建前端）
composer setup

# 3. 配置 .env（修改数据库和 Redis 连接信息）
#    编辑 .env 文件，设置 DB_* 和 REDIS_* 参数

# 4. 启动开发服务（同时启动 4 个进程）
composer dev
```

`composer dev` 会同时启动：

| 进程 | 说明 | 端口 |
|------|------|------|
| `php artisan serve` | Laravel 开发服务器 | http://localhost:8000 |
| `php artisan queue:listen` | 队列 Worker（监听 default + audit 队列） | - |
| `php artisan pail` | 实时日志查看 | - |
| `npm run dev` | Vite 前端热更新 | http://localhost:5173 |

### 3.2 手动步骤

```bash
# 安装 PHP 依赖
composer install

# 安装前端依赖
npm install

# 复制环境配置
cp .env.example .env

# 生成应用密钥
php artisan key:generate

# 编辑 .env 配置数据库和 Redis
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_DATABASE=jxc_system
# DB_USERNAME=root
# DB_PASSWORD=your_password
# CACHE_STORE=redis
# SESSION_DRIVER=redis
# QUEUE_CONNECTION=redis

# 运行数据库迁移
php artisan migrate

# 或导入完整 SQL 文件（含结构 + 示例数据）
mysql -u root -p jxc_system < jxc_system_structure_and_data.sql

# 启动各服务（分别在不同终端窗口）
php artisan serve                                              # 终端 1
php artisan queue:listen --queue=default,audit --tries=3       # 终端 2
npm run dev                                                    # 终端 3
```

### 3.3 运行测试

```bash
# 运行全部测试
composer test

# 或直接调用
php artisan test

# 运行特定测试文件
php artisan test --filter=ApiTest
```

测试环境自动使用 SQLite 内存数据库 + array 缓存 + sync 队列（见 `phpunit.xml`），无需额外配置。

---

## 4. 环境变量配置详解

以下是 `.env` 文件中所有关键配置项的说明。

### 4.1 应用基础

```env
APP_NAME=进销存系统              # 应用名称（显示在前端标题、邮件等处）
APP_ENV=production              # 环境：local / staging / production
APP_KEY=base64:xxxxxxxx        # 加密密钥（自动生成，切勿泄露）
APP_DEBUG=false                 # 生产环境必须设为 false
APP_URL=https://jxc.example.com # 应用对外访问 URL
APP_TIMEZONE=Asia/Shanghai      # 时区（Docker 中通过 environment 设置）
```

> **重要**：生产环境务必设置 `APP_ENV=production` 和 `APP_DEBUG=false`。开启 debug 会暴露敏感信息。

### 4.2 数据库

```env
DB_CONNECTION=mysql             # 数据库驱动
DB_HOST=127.0.0.1               # 数据库地址（Docker 中用 mysql）
DB_PORT=3306                    # 端口
DB_DATABASE=jxc_system          # 数据库名
DB_USERNAME=jxc_user            # 用户名（生产环境不要用 root）
DB_PASSWORD=strong_password     # 密码（使用强密码）
```

### 4.3 Redis

```env
REDIS_CLIENT=phpredis           # Redis 客户端（phpredis 比 predis 性能更好）
REDIS_HOST=127.0.0.1            # Redis 地址（Docker 中用 redis）
REDIS_PASSWORD=null             # Redis 密码（生产环境建议设置）
REDIS_PORT=6379                 # 端口
```

### 4.4 缓存 / 会话 / 队列

```env
CACHE_STORE=redis               # 缓存驱动：redis（生产推荐）/ database / file
CACHE_PREFIX=jxc                # 缓存 key 前缀（多应用共享 Redis 时防冲突）
SESSION_DRIVER=redis            # 会话驱动：redis（生产推荐）/ database / file
SESSION_LIFETIME=120            # 会话有效期（分钟）
QUEUE_CONNECTION=redis          # 队列驱动：redis（生产推荐）/ database / sync
```

### 4.5 日志

```env
LOG_CHANNEL=stack               # 日志通道
LOG_STACK=daily,sentry          # stack 包含的通道（daily 文件日志 + Sentry 上报）
LOG_LEVEL=warning               # 生产环境建议 warning 或 error
```

| LOG_LEVEL | 说明 | 建议场景 |
|-----------|------|----------|
| `debug` | 所有日志 | 本地开发 |
| `info` | 信息级以上 | 测试环境 |
| `warning` | 警告级以上 | 生产环境（推荐） |
| `error` | 错误级以上 | 高流量生产环境 |

### 4.6 Sentry 错误监控

```env
SENTRY_LARAVEL_DSN=https://xxx@xxx.ingest.sentry.io/xxx  # Sentry DSN（留空则不启用）
SENTRY_TRACES_SAMPLE_RATE=0.2   # 性能追踪采样率（0~1，0.2 = 20% 请求）
SENTRY_ENVIRONMENT=production   # 环境标识（默认取 APP_ENV）
```

### 4.7 密码加密

```env
BCRYPT_ROUNDS=12                # Bcrypt 哈希轮次（生产建议 12，开发可用 4 加快速度）
```

---

## 5. 数据库部署

### 方案一：导入 SQL 文件（推荐首次部署）

适用于需要预置数据（权限、角色、默认门店等）的场景。

```bash
# 1. 登录 MySQL，创建数据库和用户
mysql -u root -p

CREATE DATABASE jxc_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jxc_user'@'%' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON jxc_system.* TO 'jxc_user'@'%';
FLUSH PRIVILEGES;
EXIT;

# 2. 导入数据（含表结构 + 初始数据）
mysql -u jxc_user -p jxc_system < jxc_system_structure_and_data.sql
```

### 方案二：Laravel 迁移

适用于全新环境、不需要预置数据的场景。

```bash
# 运行所有迁移
php artisan migrate --force

# 如需填充示例数据
php artisan db:seed
```

### 数据库优化建议

MySQL 配置文件（`/etc/mysql/conf.d/custom.cnf` 或 Docker 中 `docker/mysql/my.cnf`）：

```ini
[mysqld]
# 字符集
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci

# InnoDB 性能
innodb_buffer_pool_size=256M      # 生产建议：物理内存的 50~70%
innodb_log_file_size=64M
innodb_flush_log_at_trx_commit=2  # 平衡性能和安全（1=最安全，2=较快）
innodb_flush_method=O_DIRECT

# 连接数
max_connections=200
wait_timeout=600
interactive_timeout=600

# 慢查询日志
slow_query_log=1
slow_query_log_file=/var/log/mysql/slow.log
long_query_time=2                 # 超过 2 秒记录为慢查询

# 安全
skip-name-resolve
sql_mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

---

## 6. Redis 配置

Redis 在本系统中承担三个核心角色：

| 角色 | DB 编号 | 说明 |
|------|---------|------|
| 缓存（Cache） | DB 1 | 权限缓存、业务数据缓存 |
| 会话（Session） | DB 0 | 用户会话存储 |
| 队列（Queue） | DB 0 | 审计日志异步写入等 |

### 6.1 安装 Redis

```bash
# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server

# macOS (Homebrew)
brew install redis
brew services start redis

# 验证连接
redis-cli ping   # 应返回 PONG
```

### 6.2 生产 Redis 配置建议

编辑 `/etc/redis/redis.conf`：

```conf
# 内存限制（根据服务器内存调整）
maxmemory 256mb
maxmemory-policy allkeys-lru    # 内存满时淘汰最近最少使用的 key

# 持久化（AOF 模式，更安全）
appendonly yes
appendfsync everysec

# 安全
requirepass your_redis_password  # 设置密码
bind 127.0.0.1                  # 仅监听本地（如 Redis 与应用不在同一机器，改为内网 IP）

# 连接数
maxclients 1000

# 禁用危险命令
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command CONFIG "JXC_CONFIG"
```

设置密码后，`.env` 中同步修改：

```env
REDIS_PASSWORD=your_redis_password
```

### 6.3 验证 Redis 连接

```bash
# 通过 Laravel 验证
php artisan tinker
>>> Illuminate\Support\Facades\Redis::ping()
# 应返回 true 或 "PONG"
```

---

## 7. 生产环境部署（裸机 / 云服务器）

### 7.1 服务器初始化

```bash
# 以 Ubuntu 22.04/24.04 为例

# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装基础工具
sudo apt install -y git curl unzip supervisor

# 安装 PHP 8.2 + 所需扩展
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-bcmath php8.2-gd php8.2-intl php8.2-redis php8.2-opcache \
    php8.2-curl php8.2-zip

# 安装 Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 安装 Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# 安装 Nginx
sudo apt install -y nginx

# 安装 MySQL 8.0
sudo apt install -y mysql-server

# 安装 Redis
sudo apt install -y redis-server
```

### 7.2 部署应用代码

```bash
# 创建应用目录
sudo mkdir -p /var/www/jxc_system
sudo chown $USER:www-data /var/www/jxc_system

# 克隆代码
cd /var/www/jxc_system
git clone <repository-url> .

# 安装 PHP 依赖（生产模式，不含开发依赖）
composer install --no-dev --optimize-autoloader --no-interaction

# 安装前端依赖并构建
npm ci --no-audit --no-fund
npm run build

# 复制并编辑环境配置
cp .env.example .env
nano .env       # 参照附录 A 填写所有生产配置

# 生成应用密钥
php artisan key:generate --force

# 数据库迁移（或导入 SQL）
php artisan migrate --force
# 或: mysql -u jxc_user -p jxc_system < jxc_system_structure_and_data.sql

# 创建存储链接
php artisan storage:link

# 缓存配置（生产必做，显著提升性能）
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 设置目录权限
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 7.3 PHP-FPM 优化

编辑 `/etc/php/8.2/fpm/pool.d/www.conf`：

```ini
[www]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm.sock

; 进程管理（根据服务器内存调整）
pm = dynamic
pm.max_children = 50            ; 2G 内存建议 30~50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000          ; 防止内存泄漏

; 慢请求日志
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm-slow.log
```

编辑 `/etc/php/8.2/fpm/conf.d/99-opcache.ini`：

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0       ; 生产环境关闭文件检查（部署后需重启 FPM）
opcache.jit=1255
opcache.jit_buffer_size=128M
```

```bash
# 重启 PHP-FPM
sudo systemctl restart php8.2-fpm
```

---

## 8. Docker 容器部署

### 8.1 快速启动

```bash
# 1. 复制并编辑环境配置
cp .env.example .env
nano .env

# 至少需要设置以下变量：
#   APP_KEY        （先留空，稍后生成）
#   DB_PASSWORD    （MySQL 用户密码）
#   DB_ROOT_PASSWORD（MySQL root 密码）

# 2. 构建并启动所有容器
docker compose up -d --build

# 3. 生成 APP_KEY（首次部署）
docker compose exec app php artisan key:generate --force

# 4. 导入初始数据（可选）
docker compose exec -T mysql mysql -u jxc_user -pjxc_password jxc_system \
    < jxc_system_structure_and_data.sql

# 5. 查看状态
docker compose ps
```

### 8.2 容器架构

当前 Compose 共 **4 个服务**：应用、队列、MySQL、Redis。应用容器内**不运行**队列与定时任务（由环境变量 `RUN_QUEUE_IN_CONTAINER=false` 控制），队列由独立 `queue` 服务处理，便于扩容与重启隔离。

```
docker compose up -d
  ├── jxc-app      (PHP-FPM + Nginx 仅 Web)   → 端口 8080
  ├── jxc-queue    (Queue Worker，处理审计日志等)  → 无端口
  ├── jxc-mysql    (MySQL 8.0)                → 端口 3307（外部）
  └── jxc-redis    (Redis 7 Alpine)           → 端口 6380（外部）
```

| 容器 | 镜像 | 端口映射 | 数据卷 |
|------|------|----------|--------|
| `jxc-app` | 自定义（多阶段构建） | `8080:80` | `app-storage`, `app-logs` |
| `jxc-queue` | 同 app 镜像 | 无 | `app-logs`（与 app 共享日志卷） |
| `jxc-mysql` | `mysql:8.0` | `3307:3306` | `mysql-data` |
| `jxc-redis` | `redis:7-alpine` | `6380:6379` | `redis-data` |

### 8.3 Docker 环境变量

在 `.env` 中配置 Docker 专用变量：

```env
# 应用对外端口
APP_PORT=8080

# Docker 中的数据库配置（容器间互联用服务名）
DB_HOST=mysql
DB_USERNAME=jxc_user
DB_PASSWORD=jxc_password
DB_ROOT_PASSWORD=root_password

# Docker 中的 Redis 配置
REDIS_HOST=redis
REDIS_PORT=6379

# 外部调试端口（可选，映射到宿主机）
DB_EXTERNAL_PORT=3307
REDIS_EXTERNAL_PORT=6380
```

### 8.4 容器内进程说明

- **`jxc-app`**：因设置了 `RUN_QUEUE_IN_CONTAINER=false`，仅运行 **php-fpm** 与 **nginx**（使用 `supervisord-app-only.conf`），不运行队列与定时任务。
- **`jxc-queue`**：独立运行 `php artisan queue:work redis`，处理 `default` 与 `audit` 等队列中的异步任务（如审计日志写入）。

**定时任务（Scheduler）**：当前 Compose 未单独提供 Scheduler 容器。若需执行定时任务（如每日备份 `backup:run`），可任选其一：

1. **宿主机 Crontab**：每分钟执行  
   `* * * * * docker compose exec app php artisan schedule:run --no-interaction`
2. **单容器模式**：若不使用独立 queue 服务，可去掉 `RUN_QUEUE_IN_CONTAINER=false`，让 app 容器使用默认 `supervisord.conf`（内带 queue-worker + scheduler），则定时任务在 app 容器内执行。

### 8.5 常用 Docker 命令

```bash
# 查看容器状态（应为 app / queue / mysql / redis 四个）
docker compose ps

# 查看应用日志
docker compose logs -f app

# 查看队列 Worker 日志
docker compose logs -f queue

# 进入应用容器
docker compose exec app sh

# 运行 Artisan 命令
docker compose exec app php artisan migrate --force
docker compose exec app php artisan cache:clear
docker compose exec app php artisan queue:restart

# 重启队列容器（使新代码生效，或配合 queue:restart 使用）
docker compose restart queue

# 重新构建（代码更新后）
docker compose up -d --build

# 停止所有容器
docker compose down

# 停止并清除数据卷（危险！会清除数据库）
docker compose down -v
```

> 生产环境若使用 `docker-compose.prod.yml` 覆盖配置，可执行：  
> `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d`  
> （当前仓库可能未包含 prod 覆盖文件，仅用 `docker-compose.yml` 即可。）

---

## 9. 队列 Worker 配置

系统使用 Redis 队列处理异步任务，主要包括：

| 队列 | 用途 | 重试次数 |
|------|------|----------|
| `default` | 通用任务 | 3 次 |
| `audit` | 审计日志写入（`WriteAuditLog` Job） | 3 次，指数退避 5s→15s→30s |

### 9.1 裸机部署：Supervisor 配置

创建 `/etc/supervisor/conf.d/jxc-worker.conf`：

```ini
[program:jxc-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/jxc_system/artisan queue:work redis --queue=default,audit --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2                                   ; 根据 CPU 核心数调整（建议 1~4）
redirect_stderr=true
stdout_logfile=/var/www/jxc_system/storage/logs/queue-worker.log
stopwaitsecs=3600                            ; 等待当前任务完成再停止
```

```bash
# 重新加载 Supervisor 配置
sudo supervisorctl reread
sudo supervisorctl update

# 查看 Worker 状态
sudo supervisorctl status jxc-queue-worker:*

# 重启 Worker（部署代码后必须执行）
sudo supervisorctl restart jxc-queue-worker:*
# 或通过 Artisan：
php artisan queue:restart
```

### 9.2 Docker 部署

队列由独立服务 **`jxc-queue`** 运行，无需在 app 容器内再起 Worker。更新代码或配置后建议：

```bash
# 通知所有 Worker 在处理完当前任务后重启（推荐）
docker compose exec app php artisan queue:restart

# 或直接重启 queue 容器
docker compose restart queue
```

### 9.3 失败任务处理

```bash
# 查看失败任务
php artisan queue:failed

# 重试所有失败任务
php artisan queue:retry all

# 重试指定任务
php artisan queue:retry <uuid>

# 清除所有失败任务
php artisan queue:flush
```

---

## 10. Sentry 错误监控

### 10.1 获取 DSN

1. 前往 [sentry.io](https://sentry.io) 注册账号（或使用自建 Sentry）
2. 创建项目，选择 **Laravel** 平台
3. 复制项目的 **DSN**

### 10.2 配置

在 `.env` 中设置：

```env
SENTRY_LARAVEL_DSN=https://your-key@xxx.ingest.sentry.io/your-project-id
SENTRY_TRACES_SAMPLE_RATE=0.2     # 性能追踪采样率（0.2 = 20% 的请求会上报性能数据）
SENTRY_ENVIRONMENT=production     # 可选，默认取 APP_ENV
```

### 10.3 验证

```bash
# 发送测试异常到 Sentry
php artisan sentry:test

# 如果配置正确，会在 Sentry 面板中看到一条测试异常
```

### 10.4 功能说明

系统已集成以下 Sentry 功能：

| 功能 | 说明 |
|------|------|
| **异常上报** | 所有未捕获的异常自动上报到 Sentry |
| **业务上下文** | 自动附加用户 ID、用户名、企业 ID、角色、请求 URL |
| **性能追踪** | SQL 查询、Redis 操作、队列任务自动记录 |
| **面包屑** | 日志、缓存操作、HTTP 请求等事件时间线 |
| **日志通道** | `LOG_STACK=daily,sentry` — warning 及以上级别同时上报到 Sentry |

### 10.5 不启用 Sentry

如果不需要 Sentry，只需将 `SENTRY_LARAVEL_DSN` 留空即可：

```env
SENTRY_LARAVEL_DSN=
```

同时建议将日志栈改为纯文件：

```env
LOG_STACK=daily
```

---

## 11. Nginx 反向代理配置

### 11.1 裸机部署 Nginx 配置

创建 `/etc/nginx/sites-available/jxc_system`：

```nginx
server {
    listen 80;
    server_name jxc.example.com;      # 替换为实际域名
    root /var/www/jxc_system/public;
    index index.php;

    charset utf-8;

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip 压缩
    gzip on;
    gzip_types text/plain text/css application/json application/javascript
               text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 1024;

    # 静态资源缓存（Vite 构建产物带 hash，可长期缓存）
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Laravel 路由
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM 处理
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # 禁止访问隐藏文件（.env, .git 等）
    location ~ /\. {
        deny all;
    }

    # 上传限制
    client_max_body_size 50m;

    # 日志
    access_log /var/log/nginx/jxc_access.log;
    error_log /var/log/nginx/jxc_error.log;
}
```

```bash
# 启用站点
sudo ln -s /etc/nginx/sites-available/jxc_system /etc/nginx/sites-enabled/

# 检查配置语法
sudo nginx -t

# 重载 Nginx
sudo systemctl reload nginx
```

---

## 12. HTTPS / SSL 证书

### 12.1 Let's Encrypt（免费证书，推荐）

```bash
# 安装 Certbot
sudo apt install -y certbot python3-certbot-nginx

# 自动申请并配置 SSL
sudo certbot --nginx -d jxc.example.com

# 证书自动续期（Certbot 已自动添加定时任务）
sudo certbot renew --dry-run    # 测试续期
```

### 12.2 HTTPS 配置完成后

更新 `.env`：

```env
APP_URL=https://jxc.example.com
SESSION_SECURE_COOKIE=true       # 仅通过 HTTPS 传输 Cookie
```

Certbot 会自动在 Nginx 配置中添加 301 跳转和 SSL 参数。

---

## 13. 安全加固清单

### 部署前必做

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` 已生成且保密
- [ ] `DB_PASSWORD` 使用强密码（16 位以上，含大小写字母+数字+特殊字符）
- [ ] MySQL 用户非 root，仅授权 `jxc_system` 数据库
- [ ] Redis 设置密码（`requirepass`）或仅监听 127.0.0.1

### 网络安全

- [ ] 防火墙仅开放 80/443 端口
- [ ] MySQL 端口（3306）不对外开放
- [ ] Redis 端口（6379）不对外开放
- [ ] SSH 使用密钥登录，禁用密码登录

### 应用安全

- [ ] `.env` 文件权限设为 600（`chmod 600 .env`）
- [ ] `storage/` 和 `bootstrap/cache/` 权限为 775
- [ ] Nginx 禁止访问 `.env`、`.git` 等隐藏文件
- [ ] HTTPS 已启用，Cookie 设为 Secure
- [ ] CORS 策略已正确配置
- [ ] 登录限流已启用（10 次/分钟 + 账号锁定 5 次/15 分钟）
- [ ] API 限流已启用（60 次/分钟）

### 定期维护

- [ ] 定期更新系统和 PHP 补丁
- [ ] 定期审查审计日志
- [ ] 定期清理过期的 API Token
- [ ] 定期轮换 Redis 密码和数据库密码

---

## 14. 监控与健康检查

### 14.1 健康检查端点

系统提供两个健康检查端点：

| 端点 | 认证 | 用途 |
|------|------|------|
| `GET /api/v1/health` | 无需认证 | 基础存活检查（LB 心跳、K8s liveness probe） |
| `GET /api/v1/health/deep` | 需要 Bearer Token | 深度诊断（MySQL、Redis、Cache、Queue 状态 + 延迟） |

**基础检查示例：**

```bash
curl http://localhost:8080/api/v1/health
```

```json
{
  "success": true,
  "message": "ok",
  "timestamp": "2026-02-11T10:00:00+08:00"
}
```

**深度检查示例：**

```bash
curl -H "Authorization: Bearer <your-token>" http://localhost:8080/api/v1/health/deep
```

```json
{
  "success": true,
  "message": "所有服务正常",
  "data": {
    "checks": {
      "database": { "status": "ok", "latency_ms": 1.23, "driver": "mysql" },
      "redis": { "status": "ok", "latency_ms": 0.45, "response": true },
      "cache": { "status": "ok", "latency_ms": 0.89, "driver": "redis" },
      "queue": { "status": "ok", "driver": "redis", "connection": "default" }
    },
    "app": {
      "name": "进销存系统",
      "env": "production",
      "debug": false,
      "php_version": "8.2.x",
      "laravel_version": "12.x"
    }
  }
}
```

### 14.2 Kubernetes / Docker 健康探测

```yaml
# K8s Deployment 示例
livenessProbe:
  httpGet:
    path: /api/v1/health
    port: 80
  initialDelaySeconds: 30
  periodSeconds: 10
  timeoutSeconds: 5

readinessProbe:
  httpGet:
    path: /api/v1/health
    port: 80
  initialDelaySeconds: 10
  periodSeconds: 5
```

### 14.3 日志监控

```bash
# 查看实时日志（开发环境）
php artisan pail

# 查看最新日志文件（生产环境）
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# 搜索错误日志
grep -i "error\|exception" storage/logs/laravel-$(date +%Y-%m-%d).log

# 查看队列 Worker 日志
tail -f storage/logs/queue-worker.log

# 查看失败的队列任务
php artisan queue:failed
```

### 14.4 部署状态检查

**裸机 / 常规部署：**

```bash
./deploy.sh status
```

**Docker 部署**：还可执行 `docker compose ps` 查看 `app` / `queue` / `mysql` / `redis` 四个容器状态及健康情况。

输出示例：

```
========================================
  进销存系统 - 部署状态
========================================

Git 分支:     main
最后提交:     abc1234 fix: 修复库存计算
提交时间:     2026-02-11 10:00:00 +0800

Laravel 版本: Laravel Framework 12.x
PHP 版本:     PHP 8.2.x
环境:         production

维护模式:     正常运行

磁盘使用:
  storage: 128M

========================================
```

---

## 15. 备份与恢复

系统提供内置备份脚本与 Artisan 命令，**完整说明与恢复步骤见 [docs/backup-restore.md](docs/backup-restore.md)**。此处仅作要点摘要。

### 15.1 备份方式

| 方式 | 说明 |
|------|------|
| **Artisan** | `php artisan backup:run`：执行 MySQL 全库导出（gzip），并按保留天数清理旧备份；可选 `--keep=N` 指定保留天数。 |
| **脚本** | 容器内执行 `scripts/backup.sh`（依赖 `DB_*` 环境变量）；宿主机可执行 `docker compose exec app php artisan backup:run`。 |
| **默认路径** | 备份文件存放在 `storage/app/backups/`，命名格式 `mysql_YYYYMMDD_HHMMSS.sql.gz`。 |
| **保留策略** | 默认保留最近 7 天（`BACKUP_KEEP_DAYS`），超期自动删除。 |

### 15.2 定时备份

- **Laravel Scheduler**：已在 `routes/console.php` 中注册每日凌晨 2 点执行 `backup:run`（仅 `production` / `staging`）。  
- **Docker**：若使用当前 Compose 且未在 app 容器内跑 Scheduler，需在宿主机添加 Crontab 每分钟执行 `schedule:run`（见 [8.4 容器内进程说明](#84-容器内进程说明)），定时备份才会生效。

### 15.3 恢复

从 `mysql_*.sql.gz` 恢复数据库的步骤（含 Docker 下操作）见 **[docs/backup-restore.md - 恢复步骤](docs/backup-restore.md)**。

---

## 16. 更新与回滚

### 16.1 常规更新

使用部署脚本：

```bash
./deploy.sh update
```

该脚本自动执行：
1. 开启维护模式 (`php artisan down`)
2. 拉取最新代码 (`git pull`)
3. 安装 PHP 依赖 (`composer install --no-dev`)
4. 构建前端 (`npm ci && npm run build`)
5. 运行数据库迁移 (`php artisan migrate --force`)
6. 重建配置缓存 (`php artisan config:cache` 等)
7. 重启队列 Worker (`php artisan queue:restart`)
8. 关闭维护模式 (`php artisan up`)

### 16.2 手动更新

```bash
# 进入维护模式
php artisan down --retry=60

# 拉取代码
git pull origin main

# 安装依赖
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build

# 数据库迁移
php artisan migrate --force

# 重建缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 重启队列 Worker
php artisan queue:restart

# 如果使用了 OPcache（validate_timestamps=0），需重启 PHP-FPM
sudo systemctl restart php8.2-fpm

# 退出维护模式
php artisan up
```

### 16.3 回滚

```bash
./deploy.sh rollback
```

或手动：

```bash
php artisan down --retry=60

# 回滚数据库迁移（最近一批）
php artisan migrate:rollback --force

# 回滚代码
git reset --hard HEAD~1

# 重建
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
```

### 16.4 Docker 环境更新

```bash
# 拉取最新代码
git pull origin main

# 重新构建并启动（app + queue + mysql + redis）
docker compose up -d --build

# 进入容器运行迁移（如需要）
docker compose exec app php artisan migrate --force

# 通知队列 Worker 重启以加载新代码
docker compose exec app php artisan queue:restart
# 或直接重启 queue 容器
docker compose restart queue
```

---

## 17. 性能调优

### 17.1 Laravel 缓存优化（生产必做）

```bash
# 配置缓存（避免每次请求解析 .env 和 config）
php artisan config:cache

# 路由缓存（避免每次请求重新注册路由）
php artisan route:cache

# 视图缓存（预编译 Blade 模板）
php artisan view:cache

# 事件缓存
php artisan event:cache
```

> **注意**：每次修改 `.env` 或配置文件后，必须重新运行 `php artisan config:cache`。

### 17.2 Redis 缓存命中率监控

```bash
redis-cli info stats | grep "keyspace_hits\|keyspace_misses"

# 计算命中率：hits / (hits + misses) × 100%
# 健康值：> 90%
```

### 17.3 数据库查询优化

```bash
# 查看慢查询日志
# MySQL：
SHOW VARIABLES LIKE 'slow_query_log%';

# 查看当前进程
SHOW PROCESSLIST;

# 查看 InnoDB 状态
SHOW ENGINE INNODB STATUS;
```

### 17.4 OPcache 状态检查

```bash
php -r "print_r(opcache_get_status(false));"
```

关键指标：
- `opcache_statistics.hit_rate` — 命中率，应 > 99%
- `memory_usage.used_memory` — 内存使用
- `opcache_statistics.oom_restarts` — OOM 重启次数，应为 0

---

## 18. 故障排查

### 18.1 常见问题

| 问题 | 原因 | 解决方案 |
|------|------|----------|
| 500 Server Error | .env 配置错误或缺少 APP_KEY | 检查 `storage/logs/laravel-*.log`；运行 `php artisan key:generate` |
| Redis 连接失败 | Redis 未启动或地址错误 | `redis-cli ping`；检查 `.env` 中 REDIS_HOST/PORT |
| 权限拒绝 (403) | 文件权限不正确 | `sudo chown -R www-data:www-data storage bootstrap/cache` |
| 队列任务不执行 | Worker 未启动 | 检查 Supervisor 状态；运行 `php artisan queue:listen` 测试 |
| 前端空白页 | Vite 构建产物缺失 | 运行 `npm run build`；检查 `public/build/` 目录 |
| 登录失败 | 账号被锁定（连续 5 次失败） | 等待 15 分钟或清除 Redis：`redis-cli del "login_throttle:*"` |
| 数据库迁移失败 | 表已存在或字段冲突 | 检查迁移状态：`php artisan migrate:status` |
| 缓存数据不更新 | 旧缓存未清除 | `php artisan cache:clear && php artisan config:cache` |

### 18.2 日志文件位置

| 日志 | 路径 |
|------|------|
| 应用日志 | `storage/logs/laravel-YYYY-MM-DD.log` |
| 队列 Worker 日志 | `storage/logs/queue-worker.log` |
| Nginx 访问日志 | `/var/log/nginx/jxc_access.log` |
| Nginx 错误日志 | `/var/log/nginx/jxc_error.log` |
| PHP-FPM 慢日志 | `/var/log/php-fpm-slow.log` |
| MySQL 慢查询日志 | `/var/log/mysql/slow.log` |
| Supervisor 日志 | `/var/log/supervisord.log` |

### 18.3 有用的调试命令

```bash
# 查看 Laravel 版本和环境
php artisan about

# 查看所有注册的路由
php artisan route:list

# 检查数据库连接
php artisan db:show

# 查看迁移状态
php artisan migrate:status

# 检查配置缓存
php artisan config:show database

# 清除所有缓存（调试用，生产慎用）
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 检查 Redis 连接
php artisan tinker
>>> Illuminate\Support\Facades\Redis::ping()

# 手动运行队列任务（调试）
php artisan queue:work --once --queue=default,audit
```

---

## 附录 A：完整 .env 生产模板

```env
# ═══════════════════════════════════════════════════════
#  进销存系统 — 生产环境 .env 模板
#  复制为 .env 并修改所有标记为 【必改】 的配置项
# ═══════════════════════════════════════════════════════

# ── 应用 ──────────────────────────────────────────────
APP_NAME=进销存系统
APP_ENV=production                              # 【必改】生产环境
APP_KEY=                                         # 运行 php artisan key:generate 自动生成
APP_DEBUG=false                                  # 【必改】生产环境必须 false
APP_URL=https://jxc.example.com                  # 【必改】实际域名

APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN
APP_FAKER_LOCALE=zh_CN

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

# ── 日志 ──────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=daily,sentry                           # daily 文件日志 + Sentry 上报
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning                                # 生产建议 warning

# ── 数据库 ────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1                                # 【必改】数据库地址
DB_PORT=3306
DB_DATABASE=jxc_system                           # 【必改】数据库名
DB_USERNAME=jxc_user                             # 【必改】数据库用户名
DB_PASSWORD=your_strong_db_password              # 【必改】强密码

# ── 缓存 / 会话 / 队列（全部使用 Redis）──────────────
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis

CACHE_STORE=redis
CACHE_PREFIX=jxc

# ── Redis ─────────────────────────────────────────────
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1                             # 【必改】Redis 地址
REDIS_PASSWORD=your_redis_password               # 【必改】Redis 密码
REDIS_PORT=6379

# ── Sentry 错误监控 ──────────────────────────────────
SENTRY_LARAVEL_DSN=https://xxx@xxx.ingest.sentry.io/xxx  # 【必改】Sentry DSN
SENTRY_TRACES_SAMPLE_RATE=0.2

# ── 邮件（按需配置）─────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
```

---

## 附录 B：部署脚本 deploy.sh 用法

项目根目录下的 `deploy.sh` 提供一键部署能力：

```bash
# 赋予执行权限（首次）
chmod +x deploy.sh
```

| 命令 | 说明 |
|------|------|
| `./deploy.sh init` | 首次部署初始化（安装依赖、生成密钥、迁移、缓存、构建前端） |
| `./deploy.sh update` | 常规更新（维护模式 → 拉代码 → 依赖 → 构建 → 迁移 → 缓存 → 退出维护） |
| `./deploy.sh rollback` | 回滚到上一版本（迁移回滚 + Git reset） |
| `./deploy.sh status` | 查看部署状态（Git 信息、版本、维护模式、磁盘占用） |
| `./deploy.sh docker` | Docker 构建并启动所有容器 |

---

## 附录 C：API 路由速查表

### 公开接口（无需认证）

| 方法 | 路径 | 说明 | 限流 |
|------|------|------|------|
| GET | `/api/v1/health` | 基础存活检查 | 无 |
| POST | `/api/v1/auth/login` | 用户登录 | 10 次/分钟 (IP) |
| POST | `/api/v1/tenant/register` | 企业注册 | 5 次/小时 (IP) |

### 已认证接口（需 Bearer Token）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/auth/me` | 获取当前用户信息 |
| POST | `/api/v1/auth/logout` | 退出登录 |
| GET | `/api/v1/health/deep` | 深度健康检查 |
| GET | `/api/v1/tenant/current` | 获取当前企业信息 |
| PUT | `/api/v1/tenant/current` | 更新企业信息 |

### 业务资源接口（需认证 + 企业有效 + RBAC 权限）

所有业务资源均支持标准 RESTful 操作（index / show / store / update / destroy）：

| 资源 | 路径前缀 | 附加操作 |
|------|----------|----------|
| 商品 | `/api/v1/products` | — |
| 商品分类 | `/api/v1/product-categories` | — |
| 供应商 | `/api/v1/suppliers` | — |
| 客户 | `/api/v1/customers` | — |
| 采购单 | `/api/v1/purchase-orders` | `PUT /{id}/receive`（收货）、`PUT /{id}/cancel`（取消） |
| 销售单 | `/api/v1/sales-orders` | `PUT /{id}/deliver`（发货）、`PUT /{id}/cancel`（取消） |
| 仓库 | `/api/v1/warehouses` | — |
| 应付账款 | `/api/v1/accounts-payable` | `PUT /{id}/pay`（付款） |
| 应收账款 | `/api/v1/accounts-receivable` | `PUT /{id}/collect`（收款） |
| 财务流水 | `/api/v1/financial-transactions` | `PUT /{id}/void`（作废/冲销） |
| 库存调整 | `/api/v1/inventory-adjustments` | — |
| 库存流水 | `/api/v1/inventory-transactions` | — |
| 换货记录 | `/api/v1/exchange-records` | `PUT /{id}/complete`（完成换货） |
| 库存盘点 | `/api/v1/inventory-counts` | `GET /{id}/items`、`PUT /{id}/items`、`POST /{id}/complete` |
| 销售退货 | `/api/v1/sales-returns` | `PUT /{id}/process`（处理）、`PUT /{id}/refund`（退款） |
| 门店 | `/api/v1/stores` | — |
| 业务员 | `/api/v1/business-agents` | — |
| 角色 | `/api/v1/roles` | `GET /{id}/permissions`、`PUT /{id}/permissions` |
| 部门 | `/api/v1/departments` | — |
| 计量单位 | `/api/v1/units` | — |
| 用户 | `/api/v1/users` | — |
| 权限 | `/api/v1/permissions` | 仅 `index` |
| 审计日志 | `/api/v1/audit-logs` | 仅 `index` / `show` |

### 报表接口（额外限流 10 次/分钟）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/reports/overview` | 经营概览 |
| GET | `/api/v1/reports/sales` | 销售报表 |
| GET | `/api/v1/reports/purchase` | 采购报表 |
| GET | `/api/v1/reports/inventory` | 库存报表 |
| GET | `/api/v1/reports/finance` | 财务报表 |
| GET | `/api/v1/reports/export` | 报表导出 |
