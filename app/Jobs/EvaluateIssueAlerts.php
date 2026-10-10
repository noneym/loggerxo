<?php

namespace App\Jobs;

use App\Alerts\AlertDispatcher;
use App\Models\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateIssueAlerts implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @param  array{new: bool, regressed: bool, count: int, level: string, environment: string}  $flags
     */
    public function __construct(public int $issueId, public array $flags) {}

    public function handle(AlertDispatcher $dispatcher): void
    {
        $issue = Issue::with('project')->find($this->issueId);
        if (! $issue || ! $issue->project) {
            return;
        }
        $dispatcher->evaluate($issue, $this->flags);
    }
}
