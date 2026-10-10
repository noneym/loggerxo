<?php

namespace App\Console\Commands;

use App\Models\Issue;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Services\ClickHouse\ClickHouseClient;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'loggerxo:prune';

    protected $description = 'Delete events older than each project\'s retention and clean up stale issues';

    public function handle(): int
    {
        $ch = ClickHouseClient::fromConfig();
        $events = $ch->table('events');
        $transactions = $ch->table('transactions');

        Project::query()->orderBy('id')->chunk(100, function ($projects) use ($ch, $events, $transactions) {
            foreach ($projects as $project) {
                $days = max(1, (int) $project->retention_days);
                $cutoff = now('UTC')->subDays($days);
                try {
                    $ch->execute("DELETE FROM {$events} WHERE project_id = {pid:UInt64} AND timestamp < {cutoff:DateTime64(3)}", ['pid' => $project->id, 'cutoff' => $cutoff]);
                    $ch->execute("DELETE FROM {$transactions} WHERE project_id = {pid:UInt64} AND timestamp < {cutoff:DateTime64(3)}", ['pid' => $project->id, 'cutoff' => $cutoff]);
                } catch (\Throwable $e) {
                    $this->error("Project {$project->id}: ".$e->getMessage());

                    continue;
                }
                $deleted = Issue::where('project_id', $project->id)->where('last_seen', '<', $cutoff)->delete();
                $this->line(sprintf('Project %d (%s): retention %dd, %d stale issues removed', $project->id, $project->name, $days, $deleted));
            }
        });

        OrganizationInvitation::where('expires_at', '<', now()->subDays(30))->delete();

        return self::SUCCESS;
    }
}
