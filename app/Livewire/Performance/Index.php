<?php

namespace App\Livewire\Performance;

use App\Livewire\Concerns\ResolvesProject;
use App\Models\Project;
use App\Services\EventRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use ResolvesProject;

    #[Url]
    public string $range = '24h';

    #[Url]
    public string $op = '';

    #[Url]
    public string $environment = '';

    #[Url]
    public string $q = '';

    #[Url]
    public string $sort = 'p95';

    public int $chartVersion = 0;

    public function mount(Project $project): void
    {
        $this->authorizeProject($project);
    }

    public function updated($name): void
    {
        if (in_array($name, ['range', 'op', 'environment'], true)) {
            $this->chartVersion++;
        }
    }

    public function render()
    {
        $repo = EventRepository::make();
        $error = null;
        $groups = [];
        $series = ['labels' => [], 'series' => ['count' => [], 'p50' => [], 'p95' => [], 'failed' => []]];
        $summary = ['transactions' => 0, 'p95' => 0, 'p50' => 0, 'failed' => 0];
        $vitals = null;
        $slowQueries = [];
        try {
            $groups = $repo->transactionGroups($this->project, $this->range, ['op' => $this->op, 'environment' => $this->environment, 'search' => $this->q], $this->sort);
            $series = $repo->transactionSeries($this->project, $this->range);
            $summary = $repo->projectSummary($this->project, $this->range);
            $vitals = $repo->webVitals($this->project, $this->range);
            $slowQueries = $repo->slowQueries($this->project, $this->range);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $chart = [
            'type' => 'line',
            'labels' => $series['labels'],
            'unit' => '',
            'legend' => true,
            'y2' => true,
            'series' => [
                ['key' => 'transaction', 'label' => 'p95 ms', 'data' => $series['series']['p95']],
                ['key' => 'log', 'label' => 'p50 ms', 'data' => $series['series']['p50']],
                ['key' => 'console', 'label' => 'requests', 'data' => $series['series']['count'], 'axis' => 'y2'],
            ],
        ];

        return view('livewire.performance.index', [
            'groups' => $groups,
            'chart' => $chart,
            'summary' => $summary,
            'vitals' => $vitals,
            'slowQueries' => $slowQueries,
            'error' => $error,
        ])->layoutData($this->layoutFor('Performance'));
    }
}
