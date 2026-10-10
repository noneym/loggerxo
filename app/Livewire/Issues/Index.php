<?php

namespace App\Livewire\Issues;

use App\Livewire\Concerns\ResolvesProject;
use App\Models\Issue;
use App\Models\Project;
use App\Services\EventRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use ResolvesProject, WithPagination;

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $type = '';

    #[Url]
    public string $level = '';

    #[Url]
    public string $environment = '';

    #[Url]
    public string $q = '';

    #[Url]
    public string $sort = 'last_seen';

    #[Url]
    public string $range = '24h';

    public array $selected = [];

    public bool $selectAll = false;

    public function mount(Project $project): void
    {
        $this->authorizeProject($project);
    }

    public function updating($name): void
    {
        if (in_array($name, ['status', 'type', 'level', 'environment', 'q', 'sort'], true)) {
            $this->resetPage();
            $this->selected = [];
            $this->selectAll = false;
        }
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value ? $this->query()->paginate(25)->pluck('id')->map(fn ($id) => (string) $id)->all() : [];
    }

    public function bulk(string $action): void
    {
        $ids = array_map('intval', $this->selected);
        if ($ids === []) {
            return;
        }
        $issues = Issue::where('project_id', $this->project->id)->whereIn('id', $ids)->get();
        foreach ($issues as $issue) {
            match ($action) {
                'resolve' => $issue->resolve(auth()->user()),
                'ignore' => $issue->ignore(),
                'reopen' => $issue->reopen(),
                'delete' => auth()->user()->canManageProject($this->project) ? $issue->delete() : null,
                default => null,
            };
        }
        $this->selected = [];
        $this->selectAll = false;
        session()->flash('status', count($issues).' '.str($action)->plural(count($issues))->replace('resolve', 'resolved')->replace('ignore', 'ignored')->replace('reopen', 'reopened')->replace('delete', 'deleted').' – '.count($issues).' '.str('issue')->plural(count($issues)).' updated');
    }

    protected function query()
    {
        $query = Issue::where('project_id', $this->project->id);
        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }
        if ($this->type !== '') {
            $query->where('type', $this->type);
        }
        if ($this->level !== '') {
            $query->where('level', $this->level);
        }
        if ($this->environment !== '') {
            $query->whereJsonContains('environments', $this->environment);
        }
        if (trim($this->q) !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->q)).'%';
            $query->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('culprit', 'like', $term)->orWhere('fingerprint', 'like', strtolower(trim($this->q)).'%'));
        }
        match ($this->sort) {
            'first_seen' => $query->orderByDesc('first_seen'),
            'events' => $query->orderByDesc('events_count'),
            'level' => $query->orderByRaw("FIELD(level, 'emergency','alert','critical','error','warning','notice','info','debug')")->orderByDesc('last_seen'),
            default => $query->orderByDesc('last_seen'),
        };

        return $query;
    }

    public function render()
    {
        $issues = $this->query()->with('assignee')->paginate(25);
        $sparklines = [];
        $error = null;
        try {
            $sparklines = EventRepository::make()->issueSparklines($this->project, $issues->pluck('id')->all(), $this->range);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $counts = Issue::where('project_id', $this->project->id)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $environments = collect(Issue::where('project_id', $this->project->id)->whereNotNull('environments')->limit(500)->pluck('environments'))->flatten()->unique()->sort()->values();

        return view('livewire.issues.index', [
            'issues' => $issues,
            'sparklines' => $sparklines,
            'counts' => $counts,
            'environments' => $environments,
            'error' => $error,
        ])->layoutData($this->layoutFor('Issues'));
    }
}
