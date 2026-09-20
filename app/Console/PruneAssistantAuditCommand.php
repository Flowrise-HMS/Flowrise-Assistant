<?php

namespace Modules\AI\Console;

use Illuminate\Console\Command;
use Modules\AI\Models\AssistantAuditLog;

/**
 * Removes assistant audit rows older than ai-assistant.audit.retention_days.
 */
class PruneAssistantAuditCommand extends Command
{
    protected $signature = 'ai:prune-assistant-audit {--dry-run : Report how many rows would be deleted without deleting them}';

    protected $description = 'Delete FlowRise assistant audit log entries past the configured retention period';

    public function handle(): int
    {
        $retentionDays = max(1, (int) config('ai-assistant.audit.retention_days', 365));
        $cutoff = now()->subDays($retentionDays);

        $query = AssistantAuditLog::query()->where('created_at', '<', $cutoff);
        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} audit row(s) older than {$retentionDays} days would be deleted.");

            return self::SUCCESS;
        }

        $query->delete();
        $this->info("Deleted {$count} audit row(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
