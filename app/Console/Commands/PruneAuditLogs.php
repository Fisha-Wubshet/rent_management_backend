<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
        {--days=90 : Delete operational rows older than this many days}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete operational audit-log rows older than N days. Critical rows are never touched.';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = DB::table('audit_logs')
            ->where('retention', 'operational')
            ->where('timestamp', '<', $cutoff);

        $count = (int) $query->count();

        if ($count === 0) {
            $this->info("Nothing to prune (0 operational rows older than {$days} days).");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[dry-run] Would delete {$count} operational rows older than {$cutoff->toIso8601String()}.");
            return self::SUCCESS;
        }

        // Delete in chunks so we don't hold a long transaction on big tables.
        $deleted = 0;
        do {
            $chunk = DB::table('audit_logs')
                ->where('retention', 'operational')
                ->where('timestamp', '<', $cutoff)
                ->limit(5000)
                ->delete();
            $deleted += $chunk;
        } while ($chunk > 0);

        $this->info("Pruned {$deleted} operational audit rows older than {$cutoff->toDateString()}.");
        return self::SUCCESS;
    }
}
