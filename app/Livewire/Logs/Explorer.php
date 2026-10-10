<?php

namespace App\Livewire\Logs;

use App\Livewire\Concerns\ResolvesProject;
use App\Models\Project;
use App\Services\EventRepository;
use App\Support\Levels;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Explorer extends Component
{
    use ResolvesProject;

    #[Url]
    public array $types = ['log', 'console', 'error'];

    #[Url]
    public array $levels = [];

    #[Url]
    public string $environment = '';

    #[Url]
    public string $logger = '';

    #[Url]
    public string $q = '';

    #[Url]
    public string $range = '24h';

    #[Url]
    public bool $live = false;

    public ?string $cursor = null;

    public array $loaded = [];

    public ?string $expanded = null;

    public ?array $expandedEvent = null;

    public int $chartVersion = 0;

    public function mount(Project $project): void
    {
        $this->authorizeProject($project);
    }

    public function updating($name): void
    {
        if (in_array($name, ['types', 'levels', 'environment', 'logger', 'q', 'range'], true)) {
            $this->cursor = null;
            $this->loaded = [];
            $this->expanded = null;
            $this->chartVersion++;
        }
    }

    public function toggleType(string $type): void
    {
        $this->updating('types');
        $this->types = in_array($type, $this->types, true) ? array_values(array_diff($this->types, [$type])) : array_values(array_merge($this->types, [$type]));
    }

    public function toggleLevel(string $level): void
    {
        $this->updating('levels');
        $this->levels = in_array($level, $this->levels, true) ? array_values(array_diff($this->levels, [$level])) : array_values(array_merge($this->levels, [$level]));
    }

    public function loadMore(string $cursor): void
    {
        $this->cursor = $cursor;
    }

    public function expand(?string $eventId): void
    {
        if ($eventId === null || $this->expanded === $eventId) {
            $this->expanded = null;
            $this->expandedEvent = null;

            return;
        }
        $this->expanded = $eventId;
        try {
            $this->expandedEvent = EventRepository::make()->getEvent($this->project, $eventId);
        } catch (\Throwable) {
            $this->expandedEvent = null;
        }
    }

    public function toggleLive(): void
    {
        $this->live = ! $this->live;
        $this->cursor = null;
        $this->loaded = [];
    }

    protected function filters(): array
    {
        return [
            'types' => $this->types ?: ['log', 'console', 'error'],
            'levels' => $this->levels,
            'environment' => $this->environment,
            'logger' => $this->logger,
            'search' => $this->q,
            'before' => $this->cursor,
        ];
    }

    public function render()
    {
        $repo = EventRepository::make();
        $error = null;
        $result = ['rows' => [], 'has_more' => false, 'next' => null];
        $series = ['labels' => [], 'series' => []];
        $environments = [];
        $loggers = [];
        try {
            $result = $repo->listEvents($this->project, $this->filters(), $this->range, 100);
            if ($this->cursor) {
                $this->loaded = array_merge($this->loaded, $result['rows']);
                $rows = $this->loaded;
            } else {
                $this->loaded = $result['rows'];
                $rows = $result['rows'];
            }
            $series = $repo->eventSeries($this->project, $this->range, array_diff_key($this->filters(), ['before' => 1]));
            $environments = $repo->distinct($this->project, 'environment');
            $loggers = $repo->distinct($this->project, 'logger');
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $rows = [];
        }

        $chart = [
            'type' => 'bar',
            'stacked' => true,
            'labels' => $series['labels'],
            'series' => collect($series['series'])->map(fn ($d, $k) => ['key' => $k, 'label' => ucfirst($k), 'data' => $d])->values()->all(),
        ];

        return view('livewire.logs.explorer', [
            'rows' => $rows,
            'hasMore' => $result['has_more'],
            'next' => $result['next'],
            'chart' => $chart,
            'environments' => $environments,
            'loggers' => $loggers,
            'allLevels' => Levels::all(),
            'error' => $error,
        ])->layoutData($this->layoutFor('Logs & console'));
    }
}
