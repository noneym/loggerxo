<?php

namespace App\Livewire;

use App\Models\Issue;
use App\Services\EventRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    #[Url]
    public string $range = '24h';

    public int $chartVersion = 0;

    public function updatedRange(): void
    {
        $this->chartVersion++;
    }

    public function render()
    {
        $org = auth()->user()->resolveCurrentOrganization();
        $projects = $org->projects()->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', Issue::STATUS_OPEN)])->orderBy('name')->get();
        $ids = $projects->pluck('id')->all();

        $repo = EventRepository::make();
        $summary = [];
        $series = ['labels' => [], 'series' => []];
        $error = null;
        try {
            $summary = $repo->organizationSummary($ids, $this->range);
            $series = $repo->organizationSeries($ids, $this->range);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $recentIssues = Issue::whereIn('project_id', $ids)->with('project')->open()->orderByDesc('last_seen')->limit(8)->get();

        $chart = [
            'type' => 'bar',
            'stacked' => true,
            'labels' => $series['labels'],
            'series' => collect($series['series'])->map(fn ($data, $key) => ['key' => $key, 'label' => ucfirst($key), 'data' => $data])->values()->all(),
            'legend' => true,
        ];

        return view('livewire.dashboard', [
            'org' => $org,
            'projects' => $projects,
            'summary' => $summary,
            'chart' => $chart,
            'recentIssues' => $recentIssues,
            'error' => $error,
            'totals' => [
                'errors' => array_sum(array_column($summary, 'errors')),
                'events' => array_sum(array_column($summary, 'events')),
                'transactions' => array_sum(array_column($summary, 'transactions')),
                'open' => $projects->sum('open_issues_count'),
            ],
        ])->layoutData(['title' => 'Overview']);
    }
}
