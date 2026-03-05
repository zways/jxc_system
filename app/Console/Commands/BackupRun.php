<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * 执行数据备份（调用 scripts/backup.sh）
 *
 * 备份内容：MySQL 全库（gzip），保留天数由 BACKUP_KEEP_DAYS 控制。
 * 可被 schedule 每日调用，也可手动执行：php artisan backup:run
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run
                            {--keep= : 保留最近 N 天的备份（覆盖 BACKUP_KEEP_DAYS）}';

    protected $description = '执行 MySQL 与存储备份，并清理过期备份';

    public function handle(): int
    {
        $script = base_path('scripts/backup.sh');
        if (!is_file($script) || !is_readable($script)) {
            $this->error('备份脚本不存在: ' . $script);
            return self::FAILURE;
        }

        $conn = config('database.connections.' . config('database.default'));
        $env = array_merge(
            [
                'BACKUP_ROOT' => base_path(),
                'BACKUP_DIR' => storage_path('app/backups'),
                'DB_HOST' => $conn['host'] ?? '127.0.0.1',
                'DB_PORT' => (string) ($conn['port'] ?? 3306),
                'DB_DATABASE' => $conn['database'] ?? '',
                'DB_USERNAME' => $conn['username'] ?? 'root',
                'DB_PASSWORD' => $conn['password'] ?? '',
            ],
            $_ENV
        );
        if ($this->option('keep') !== null) {
            $env['BACKUP_KEEP_DAYS'] = (string) $this->option('keep');
        }

        $this->info('正在执行备份...');
        $result = Process::path(base_path())
            ->env($env)
            ->timeout(600)
            ->run(['sh', $script]);

        if ($result->successful()) {
            $this->info($result->output());
            return self::SUCCESS;
        }

        $this->error('备份执行失败');
        $this->error($result->errorOutput());
        return self::FAILURE;
    }
}
