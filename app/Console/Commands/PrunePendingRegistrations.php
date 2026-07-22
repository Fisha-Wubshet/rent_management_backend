<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

class PrunePendingRegistrations extends Command
{
    protected $signature = 'registrations:prune
        {--unconfirmed-days=7 : Delete UNCONFIRMED rows older than N days}
        {--rejected-days=30 : Delete REJECTED rows older than N days}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Prune stale pending registrations (unconfirmed + rejected). Approved rows are kept forever.';

    public function handle(): int
    {
        $ud = (int) $this->option('unconfirmed-days');
        $rd = (int) $this->option('rejected-days');
        $dry = (bool) $this->option('dry-run');

        $uCutoff = now()->subDays($ud);
        $rCutoff = now()->subDays($rd);

        $uCount = PendingRegistration::where('status', PendingRegistration::STATUS_UNCONFIRMED)
            ->where('created_at', '<', $uCutoff)->count();
        $rCount = PendingRegistration::where('status', PendingRegistration::STATUS_REJECTED)
            ->where('reviewed_at', '<', $rCutoff)->count();

        if ($uCount === 0 && $rCount === 0) {
            $this->info("Nothing to prune (0 UNCONFIRMED >{$ud}d, 0 REJECTED >{$rd}d).");
            return self::SUCCESS;
        }

        if ($dry) {
            $this->warn("[dry-run] Would delete {$uCount} unconfirmed + {$rCount} rejected rows.");
            return self::SUCCESS;
        }

        $uDeleted = PendingRegistration::where('status', PendingRegistration::STATUS_UNCONFIRMED)
            ->where('created_at', '<', $uCutoff)->delete();
        $rDeleted = PendingRegistration::where('status', PendingRegistration::STATUS_REJECTED)
            ->where('reviewed_at', '<', $rCutoff)->delete();

        $this->info("Pruned {$uDeleted} unconfirmed + {$rDeleted} rejected pending-registration rows.");
        return self::SUCCESS;
    }
}
