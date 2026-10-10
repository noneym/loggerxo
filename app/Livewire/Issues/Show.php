<?php

namespace App\Livewire\Issues;

use App\Livewire\Concerns\ResolvesProject;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\Project;
use App\Services\EventRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use ResolvesProject;

    #[Locked]
    public Issue $issue;

    #[Url(as: 'event')]
    public ?string $eventId = null;

    #[Url]
    public string $tab = 'event';

    #[Url]
    public string $range = '24h';

    public string $comment = '';

    public ?string $eventsCursor = null;

    public int $chartVersion = 0;

    public function mount(Project $project, Issue $issue): void
    {
        $this->authorizeProject($project);
        abort_unless($issue->project_id === $project->id, 404);
        $this->issue = $issue;
    }

    public function updatedRange(): void
    {
        $this->chartVersion++;
    }

    public function resolve(): void
    {
        $this->issue->resolve(auth()->user());
        $this->log('status', 'marked this issue as resolved');
    }

    public function reopen(): void
    {
        $this->issue->reopen();
        $this->log('status', 'reopened this issue');
    }

    public function ignore(string $duration = 'forever'): void
    {
        $until = match ($duration) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addWeek(),
            default => null,
        };
        $this->issue->ignore($until);
        $this->log('status', 'ignored this issue'.($until ? ' until '.$until->toDayDateTimeString() : ''));
    }

    public function assign(?int $userId): void
    {
        $member = $userId ? $this->project->organization->users()->whereKey($userId)->first() : null;
        $this->issue->forceFill(['assigned_to_id' => $member?->id])->save();
        $this->log('assign', $member ? 'assigned to '.$member->name : 'removed the assignee');
    }

    public function delete()
    {
        abort_unless(auth()->user()->canManageProject($this->project), 403);
        $this->issue->delete();

        return redirect()->route('issues.index', $this->project)->with('status', 'Issue deleted.');
    }

    public function addComment(): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:5000']]);
        IssueComment::create(['issue_id' => $this->issue->id, 'user_id' => auth()->id(), 'kind' => 'comment', 'body' => $this->comment]);
        $this->comment = '';
    }

    public function showEvent(?string $eventId): void
    {
        $this->eventId = $eventId;
        $this->tab = 'event';
    }

    public function loadMoreEvents(string $cursor): void
    {
        $this->eventsCursor = $cursor;
    }

    protected function log(string $kind, string $body): void
    {
        IssueComment::create(['issue_id' => $this->issue->id, 'user_id' => auth()->id(), 'kind' => $kind, 'body' => $body]);
        $this->issue->refresh();
    }

    public function render()
    {
        $repo = EventRepository::make();
        $event = null;
        $neighbours = ['older' => null, 'newer' => null];
        $events = ['rows' => [], 'has_more' => false, 'next' => null];
        $facets = ['facets' => [], 'users' => 0];
        $chart = ['labels' => [], 'series' => []];
        $error = null;

        try {
            $event = $this->eventId ? $repo->getEvent($this->project, $this->eventId) : null;
            $event ??= $repo->latestIssueEvent($this->project, $this->issue->id);
            if ($event) {
                $neighbours = $repo->issueEventNeighbours($this->project, $this->issue->id, $event);
            }
            if ($this->tab === 'events') {
                $events = $repo->listEvents($this->project, ['issue_id' => $this->issue->id, 'before' => $this->eventsCursor], '30d', 50);
            }
            if ($this->tab === 'facets') {
                $facets = $repo->issueFacets($this->project, $this->issue->id, $this->range);
            }
            $series = $repo->issueSeries($this->project, $this->issue->id, $this->range);
            $chart = ['type' => 'bar', 'labels' => $series['labels'], 'series' => [['key' => $this->issue->type, 'label' => 'Events', 'data' => $series['series']['events']]]];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('livewire.issues.show', [
            'event' => $event,
            'neighbours' => $neighbours,
            'events' => $events,
            'facets' => $facets,
            'chart' => $chart,
            'error' => $error,
            'members' => $this->project->organization->users()->orderBy('name')->get(),
            'comments' => $this->issue->comments()->with('user')->get(),
        ])->layoutData($this->layoutFor('Issue '.$this->issue->shortId()));
    }
}
