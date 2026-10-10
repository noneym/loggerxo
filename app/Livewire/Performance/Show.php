<?php

namespace App\Livewire\Performance;

use App\Livewire\Concerns\ResolvesProject;
use App\Models\Project;
use App\Services\EventRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use ResolvesProject;

    #[Url]
    public string $name = '';

    #[Url]
    public string $range = '24h';

    #[Url]
    public string $sort = 'slowest';

    #[Url(as: 'sample')]
    public ?string $sampleId = null;

    public int $chartVersion = 0;

    public function mount(Project $project): void
    {
        $this->authorizeProject($project);
        abort_if($this->name === '', 404);
    }

    public function updated($field): void
    {
        if ($field === 'range') {
            $this->chartVersion++;
        }
    }

    public function render()
    {
        $repo = EventRepository::make();
        $error = null;
        $stats = ['stats' => [], 'measurements' => []];
        $series = ['labels' => [], 'series' => ['count' => [], 'p50' => [], 'p95' => [], 'failed' => []]];
        $samples = [];
        $sample = null;
        try {
            $stats = $repo->transactionStats($this->project, $this->range, $this->name);
            $series = $repo->transactionSeries($this->project, $this->range, $this->name);
            $samples = $repo->transactionSamples($this->project, $this->range, $this->name, $this->sort);
            $sample = $this->sampleId ? $repo->getTransaction($this->project, $this->sampleId) : null;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $chart = [
            'type' => 'line',
            'labels' => $series['labels'],
            'legend' => true,
            'y2' => true,
            'series' => [
                ['key' => 'transaction', 'label' => 'p95 ms', 'data' => $series['series']['p95']],
                ['key' => 'log', 'label' => 'p50 ms', 'data' => $series['series']['p50']],
                ['key' => 'console', 'label' => 'requests', 'data' => $series['series']['count'], 'axis' => 'y2'],
            ],
        ];

        return view('livewire.performance.show', [
            'stats' => $stats['stats'],
            'measurements' => $stats['measurements'],
            'chart' => $chart,
            'samples' => $samples,
            'sample' => $sample,
            'error' => $error,
        ])->layoutData($this->layoutFor($this->name));
    }
}
