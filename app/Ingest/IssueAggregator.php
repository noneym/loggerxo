<?php

namespace App\Ingest;

use App\Models\Issue;
use App\Support\Levels;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Groups normalized events by fingerprint and upserts the issue rows in MariaDB.
 */
class IssueAggregator
{
    /**
     * @param  array<int, array>  $issueInputs  the 'issue' part of normalized events, each with project_id
     * @return array<string, array{issue: Issue, new: bool, regressed: bool, count: int, level: string, environment: string}>
     *                                                                                                                           keyed by "project_id:fingerprint"
     */
    public function aggregate(array $issueInputs): array
    {
        $groups = [];
        foreach ($issueInputs as $input) {
            $key = $input['project_id'].':'.$input['fingerprint'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'project_id' => $input['project_id'],
                    'fingerprint' => $input['fingerprint'],
                    'type' => $input['type'],
                    'level' => $input['level'],
                    'platform' => $input['platform'],
                    'title' => $input['title'],
                    'culprit' => $input['culprit'],
                    'first' => $input['timestamp'],
                    'last' => $input['timestamp'],
                    'last_event_id' => $input['event_id'],
                    'environments' => [$input['environment'] => true],
                    'metadata' => $input['metadata'],
                    'count' => 0,
                ];
            }
            $g = &$groups[$key];
            $g['count']++;
            if ($input['timestamp']->gte($g['last'])) {
                $g['last'] = $input['timestamp'];
                $g['last_event_id'] = $input['event_id'];
                $g['title'] = $input['title'];
                $g['culprit'] = $input['culprit'];
                $g['metadata'] = $input['metadata'];
            }
            if ($input['timestamp']->lt($g['first'])) {
                $g['first'] = $input['timestamp'];
            }
            if (Levels::rank($input['level']) > Levels::rank($g['level'])) {
                $g['level'] = $input['level'];
            }
            $g['environments'][$input['environment']] = true;
            unset($g);
        }

        $results = [];
        $byProject = [];
        foreach ($groups as $key => $g) {
            $byProject[$g['project_id']][] = $g['fingerprint'];
        }

        $existing = [];
        foreach ($byProject as $projectId => $fingerprints) {
            Issue::where('project_id', $projectId)
                ->whereIn('fingerprint', $fingerprints)
                ->get()
                ->each(function (Issue $issue) use (&$existing) {
                    $existing[$issue->project_id.':'.$issue->fingerprint] = $issue;
                });
        }

        foreach ($groups as $key => $g) {
            $issue = $existing[$key] ?? null;
            $isNew = false;
            $regressed = false;

            if (! $issue) {
                try {
                    $issue = Issue::create([
                        'project_id' => $g['project_id'],
                        'fingerprint' => $g['fingerprint'],
                        'type' => $g['type'],
                        'level' => $g['level'],
                        'platform' => $g['platform'],
                        'title' => $g['title'],
                        'culprit' => $g['culprit'],
                        'status' => Issue::STATUS_OPEN,
                        'first_seen' => $g['first'],
                        'last_seen' => $g['last'],
                        'events_count' => $g['count'],
                        'last_event_id' => $g['last_event_id'],
                        'environments' => array_keys($g['environments']),
                        'metadata' => $g['metadata'],
                    ]);
                    $isNew = true;
                } catch (QueryException $e) {
                    // Another worker created it in the meantime: fall through to the update path.
                    $issue = Issue::where('project_id', $g['project_id'])->where('fingerprint', $g['fingerprint'])->first();
                    if (! $issue) {
                        throw $e;
                    }
                }
            }

            if (! $isNew) {
                $update = [
                    'events_count' => DB::raw('events_count + '.(int) $g['count']),
                    'updated_at' => now(),
                ];
                $last = CarbonImmutable::instance($g['last']);
                if ($last->gte($issue->last_seen)) {
                    $update['last_seen'] = $last;
                    $update['last_event_id'] = $g['last_event_id'];
                    $update['title'] = $g['title'];
                    $update['culprit'] = $g['culprit'];
                    $update['metadata'] = json_encode(array_merge($issue->metadata ?? [], $g['metadata']));
                }
                if (CarbonImmutable::instance($g['first'])->lt($issue->first_seen)) {
                    $update['first_seen'] = $g['first'];
                }
                if (Levels::rank($g['level']) > Levels::rank($issue->level)) {
                    $update['level'] = $g['level'];
                }
                $envs = array_values(array_unique(array_merge($issue->environments ?? [], array_keys($g['environments']))));
                if ($envs !== ($issue->environments ?? [])) {
                    $update['environments'] = json_encode($envs);
                }
                if ($issue->status === Issue::STATUS_RESOLVED) {
                    $update['status'] = Issue::STATUS_OPEN;
                    $update['is_regressed'] = true;
                    $update['resolved_at'] = null;
                    $regressed = true;
                } elseif ($issue->status === Issue::STATUS_IGNORED && $issue->ignored_until && $issue->ignored_until->isPast()) {
                    $update['status'] = Issue::STATUS_OPEN;
                    $update['ignored_until'] = null;
                }
                Issue::whereKey($issue->id)->update($update);
                $issue->refresh();
            }

            $results[$key] = [
                'issue' => $issue,
                'new' => $isNew,
                'regressed' => $regressed,
                'count' => $g['count'],
                'level' => $g['level'],
                'environment' => array_key_last($g['environments']) ?? 'production',
            ];
        }

        return $results;
    }
}
