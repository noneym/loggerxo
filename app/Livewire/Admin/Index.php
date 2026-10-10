<?php

namespace App\Livewire\Admin;

use App\Ingest\IngestQueue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\ClickHouse\ClickHouseClient;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Index extends Component
{
    public function toggleSuperAdmin(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }
        $user = User::findOrFail($userId);
        $user->forceFill(['is_super_admin' => ! $user->is_super_admin])->save();
    }

    public function requeueDead(): void
    {
        $queue = app(IngestQueue::class);
        $redis = \Illuminate\Support\Facades\Redis::connection();
        $items = $redis->lrange($queue->deadKey(), 0, -1);
        if ($items) {
            $redis->del($queue->deadKey());
            $queue->requeue(array_map(function ($raw) {
                $d = json_decode($raw, true);
                unset($d['_attempts']);

                return json_encode($d);
            }, $items));
        }
        session()->flash('status', count($items).' dead-letter items requeued.');
    }

    public function render()
    {
        $ch = app(ClickHouseClient::class);
        $queue = app(IngestQueue::class);
        $stats = ['events_24h' => null, 'transactions_24h' => null, 'events_total' => null, 'disk' => null];
        $chOk = false;
        try {
            $chOk = $ch->ping();
            if ($chOk) {
                $stats['events_24h'] = (int) $ch->scalar('SELECT count() FROM '.$ch->table('events').' WHERE timestamp >= now64(3) - INTERVAL 1 DAY', [], 0);
                $stats['transactions_24h'] = (int) $ch->scalar('SELECT count() FROM '.$ch->table('transactions').' WHERE timestamp >= now64(3) - INTERVAL 1 DAY', [], 0);
                $stats['events_total'] = (int) $ch->scalar('SELECT count() FROM '.$ch->table('events'), [], 0);
                $stats['disk'] = $ch->select("SELECT table, formatReadableSize(sum(bytes_on_disk)) AS size, sum(rows) AS rows FROM system.parts WHERE active AND database = {db:String} AND table IN ({t1:String}, {t2:String}) GROUP BY table", ['db' => $ch->database(), 't1' => $ch->table('events'), 't2' => $ch->table('transactions')]);
            }
        } catch (\Throwable) {
        }
        $queueSize = null;
        $deadSize = null;
        try {
            $queueSize = $queue->size();
            $deadSize = $queue->deadSize();
        } catch (\Throwable) {
        }

        return view('livewire.admin.index', [
            'users' => User::withCount('organizations')->latest()->limit(50)->get(),
            'organizations' => Organization::withCount(['projects', 'users'])->latest()->limit(50)->get(),
            'counts' => ['users' => User::count(), 'organizations' => Organization::count(), 'projects' => Project::count()],
            'stats' => $stats,
            'chOk' => $chOk,
            'queueSize' => $queueSize,
            'deadSize' => $deadSize,
        ])->layoutData(['title' => 'Admin']);
    }
}
