#!/bin/bash
# ============================================================
#  进销存系统 - 生产环境部署脚本
#
#  用法：
#    首次部署：  ./deploy.sh init
#    常规更新：  ./deploy.sh update
#    回滚：      ./deploy.sh rollback
#    状态检查：  ./deploy.sh status
# ============================================================

set -euo pipefail

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# 项目目录
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

# ─── 初始化部署 ──────────────────────────────────────────
deploy_init() {
    log_info "首次部署初始化..."

    # 检查 .env
    if [ ! -f .env ]; then
        log_warn ".env 文件不存在，从 .env.example 复制..."
        cp .env.example .env
        log_warn "请编辑 .env 配置后重新运行此脚本"
        exit 1
    fi

    # 安装 PHP 依赖
    log_info "安装 Composer 依赖..."
    composer install --no-dev --optimize-autoloader --no-interaction

    # 生成 APP_KEY（如果没有）
    if grep -q "APP_KEY=$" .env 2>/dev/null; then
        log_info "生成应用密钥..."
        php artisan key:generate --force
    fi

    # 安装前端依赖并构建
    log_info "安装 Node.js 依赖..."
    npm ci --no-audit --no-fund

    log_info "构建前端..."
    npm run build

    # 数据库迁移
    log_info "运行数据库迁移..."
    php artisan migrate --force

    # 缓存
    log_info "缓存配置..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link 2>/dev/null || true

    log_info "初始化完成！"
}

# ─── 常规更新 ─────────────────────────────────────────────
deploy_update() {
    log_info "开始更新..."

    # 开启维护模式
    log_info "开启维护模式..."
    php artisan down --retry=60 || true

    # 拉取最新代码
    log_info "拉取最新代码..."
    git pull origin main

    # 安装依赖
    log_info "安装 Composer 依赖..."
    composer install --no-dev --optimize-autoloader --no-interaction

    # 前端构建
    log_info "构建前端..."
    npm ci --no-audit --no-fund
    npm run build

    # 数据库迁移
    log_info "运行数据库迁移..."
    php artisan migrate --force

    # 清除并重建缓存
    log_info "重建缓存..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache 2>/dev/null || true

    # 重启队列
    log_info "重启队列工作进程..."
    php artisan queue:restart

    # 关闭维护模式
    log_info "关闭维护模式..."
    php artisan up

    log_info "更新完成！"
}

# ─── 回滚 ────────────────────────────────────────────────
deploy_rollback() {
    log_warn "开始回滚..."

    php artisan down --retry=60 || true

    log_info "回滚数据库迁移（最近一批）..."
    php artisan migrate:rollback --force

    log_info "回滚 Git 提交..."
    git reset --hard HEAD~1

    composer install --no-dev --optimize-autoloader --no-interaction
    npm ci --no-audit --no-fund
    npm run build

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    php artisan up

    log_info "回滚完成！"
}

# ─── 状态检查 ─────────────────────────────────────────────
deploy_status() {
    echo "========================================"
    echo "  进销存系统 - 部署状态"
    echo "========================================"
    echo ""

    # Git 信息
    echo "Git 分支:     $(git branch --show-current 2>/dev/null || echo 'N/A')"
    echo "最后提交:     $(git log --oneline -1 2>/dev/null || echo 'N/A')"
    echo "提交时间:     $(git log -1 --format='%ci' 2>/dev/null || echo 'N/A')"
    echo ""

    # Laravel 状态
    echo "Laravel 版本: $(php artisan --version 2>/dev/null || echo 'N/A')"
    echo "PHP 版本:     $(php -v | head -1)"
    echo "环境:         $(grep 'APP_ENV' .env 2>/dev/null | cut -d= -f2 || echo 'N/A')"
    echo ""

    # 维护模式
    if php artisan is-down 2>/dev/null; then
        echo -e "维护模式:     ${YELLOW}已开启${NC}"
    else
        echo -e "维护模式:     ${GREEN}正常运行${NC}"
    fi

    echo ""

    # 磁盘使用
    echo "磁盘使用:"
    du -sh storage/ 2>/dev/null || echo "  storage: N/A"
    echo ""

    echo "========================================"
}

# ─── Docker 部署 ─────────────────────────────────────────
deploy_docker() {
    log_info "Docker 部署..."

    if [ ! -f .env ]; then
        log_warn "请先创建 .env 文件"
        exit 1
    fi

    docker compose build --no-cache
    docker compose up -d

    log_info "等待服务启动..."
    sleep 10

    docker compose ps

    log_info "Docker 部署完成！"
}

# ─── 主入口 ──────────────────────────────────────────────
case "${1:-help}" in
    init)
        deploy_init
        ;;
    update)
        deploy_update
        ;;
    rollback)
        deploy_rollback
        ;;
    status)
        deploy_status
        ;;
    docker)
        deploy_docker
        ;;
    *)
        echo "用法: $0 {init|update|rollback|status|docker}"
        echo ""
        echo "  init      首次部署初始化"
        echo "  update    常规代码更新"
        echo "  rollback  回滚到上一版本"
        echo "  status    查看部署状态"
        echo "  docker    Docker 容器部署"
        exit 1
        ;;
esac
