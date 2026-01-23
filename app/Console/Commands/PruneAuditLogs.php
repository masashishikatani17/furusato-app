<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=365 : Keep logs for N days (default 365)}';
    protected $description = 'Prune audit_logs older than N days';

    public function handle(): int
    {
        $days = (int)$this->option('days');
        if ($days <= 0) {
            $this->error('days must be positive.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = DB::table('audit_logs')->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} audit_logs older than {$days} days (cutoff={$cutoff}).");
        return self::SUCCESS;
    }
}
