<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ResolvesProject;
use App\Models\Issue;
use App\Models\Project;
use App\Services\EventRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Overview extends Component
{
    use ResolvesProject;

    #[Url]
    public string $range = '24h';

    public int $chartVersion = 0;

    public function mount(Project $project): void
    {
        $this->authorizeProject($project);
    }

    public function updatedRange(): void
    {
        $this->chartVersion++;
    }

    public function render()
    {
        $repo = EventRepository::make();
        $error = null;
        $summary = ['errors' => 0, 'logs' => 0, 'console' => 0, 'users' => 0, 'issues' => 0, 'transactions' => 0, 'p95' => 0, 'p50' => 0, 'failed' => 0];
        $series = ['labels' => [], 'series' => []];
        $txSeries = ['labels' => [], 'series' => ['count' => [], 'p95' => []]];
        $vitals = null;
        $sparklines = [];

        $topIssues = Issue::where('project_id', $this->project->id)->open()->orderByDesc('last_seen')->limit(6)->get();

        try {
            $summary = $repo->projectSummary($this->project, $this->range);
            $series = $repo->eventSeries($this->project, $this->range);
            $txSeries = $repo->transactionSeries($this->project, $this->range);
            $vitals = $repo->webVitals($this->project, $this->range);
            $sparklines = $repo->issueSparklines($this->project, $topIssues->pluck('id')->all(), $this->range);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $eventChart = [
            'type' => 'bar',
            'stacked' => true,
            'legend' => true,
            'labels' => $series['labels'],
            'series' => collect($series['series'])->map(fn ($d, $k) => ['key' => $k, 'label' => ucfirst($k), 'data' => $d])->values()->all(),
        ];
        $txChart = [
            'type' => 'line',
            'labels' => $txSeries['labels'],
            'unit' => ' ms',
            'series' => [
                ['key' => 'transaction', 'label' => 'p95', 'data' => $txSeries['series']['p95']],
                ['key' => 'log', 'label' => 'p50', 'data' => $txSeries['series']['p50']],
            ],
            'legend' => true,
        ];

        return view('livewire.projects.overview', [
            'summary' => $summary,
            'eventChart' => $eventChart,
            'txChart' => $txChart,
            'vitals' => $vitals,
            'topIssues' => $topIssues,
            'sparklines' => $sparklines,
            'error' => $error,
            'openCount' => Issue::where('project_id', $this->project->id)->open()->count(),
        ])->layoutData($this->layoutFor('Project overview'));
    }
}
