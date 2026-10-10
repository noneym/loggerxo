<?php

namespace App\Livewire\Projects;

use App\Alerts\AlertSender;
use App\Livewire\Concerns\ResolvesProject;
use App\Models\AlertChannel;
use App\Models\AlertNotification;
use App\Models\AlertRule;
use App\Models\Project;
use App\Support\Levels;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Settings extends Component
{
    use ResolvesProject;

    public string $tab = 'general';

    // general
    public string $name = '';

    public string $platform = 'laravel';

    public int $retention_days = 30;

    public bool $is_active = true;

    public string $allowed_origins = '';

    public ?int $rate_limit_per_minute = null;

    // channel form
    public ?int $channelId = null;

    public string $channelType = 'email';

    public string $channelName = '';

    public string $channelEmails = '';

    public string $channelChatId = '';

    public string $channelBotToken = '';

    public bool $showChannelForm = false;

    // rule form
    public ?int $ruleId = null;

    public string $ruleName = '';

    public string $ruleTrigger = 'new_issue';

    public array $ruleChannels = [];

    public int $ruleCooldown = 30;

    public int $ruleThreshold = 10;

    public int $ruleWindow = 5;

    public string $ruleMinLevel = 'critical';

    public string $ruleEnvironments = '';

    public array $ruleTypes = [];

    public bool $showRuleForm = false;

    public function mount(Project $project, string $tab = 'general'): void
    {
        $this->authorizeProject($project, manage: true);
        $this->tab = in_array($tab, ['general', 'setup', 'alerts', 'danger'], true) ? $tab : 'general';
        $this->name = $project->name;
        $this->platform = $project->platform;
        $this->retention_days = $project->retention_days;
        $this->is_active = $project->is_active;
        $this->allowed_origins = implode("\n", (array) $project->allowed_origins);
        $this->rate_limit_per_minute = $project->setting('rate_limit_per_minute');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->dispatch('tab-changed');
    }

    /* ----------------------------------------------------------- general */

    public function saveGeneral(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'platform' => ['required', 'in:'.implode(',', array_keys(Project::PLATFORMS))],
            'retention_days' => ['required', 'integer', 'min:1', 'max:'.config('loggerxo.max_retention_days')],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
        $origins = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $this->allowed_origins) ?: [])));
        $settings = $this->project->settings ?? [];
        $settings['rate_limit_per_minute'] = $this->rate_limit_per_minute ?: null;
        $this->project->update([
            'name' => $this->name,
            'platform' => $this->platform,
            'retention_days' => $this->retention_days,
            'is_active' => $this->is_active,
            'allowed_origins' => $origins,
            'settings' => $settings,
        ]);
        session()->flash('status', 'Project settings saved.');
    }

    public function regenerateKey(): void
    {
        $this->project->update(['public_key' => Project::generateKey()]);
        session()->flash('status', 'New key generated. Update your SDK configuration, the old key stops working within 5 minutes.');
    }

    public function deleteProject()
    {
        abort_unless(auth()->user()->canManageProject($this->project), 403);
        $org = $this->project->organization;
        $this->project->delete();

        return redirect()->route('app.dashboard')->with('status', 'Project deleted. Its events are purged by the nightly retention job.');
    }

    /* ---------------------------------------------------------- channels */

    public function newChannel(string $type = 'email'): void
    {
        $this->reset(['channelId', 'channelName', 'channelEmails', 'channelChatId', 'channelBotToken']);
        $this->channelType = $type;
        $this->channelName = $type === 'email' ? 'Team e-mail' : 'Telegram';
        $this->showChannelForm = true;
    }

    public function editChannel(int $id): void
    {
        $channel = $this->project->alertChannels()->findOrFail($id);
        $this->channelId = $channel->id;
        $this->channelType = $channel->type;
        $this->channelName = $channel->name;
        $this->channelEmails = implode("\n", (array) data_get($channel->config, 'emails', []));
        $this->channelChatId = (string) data_get($channel->config, 'chat_id', '');
        $this->channelBotToken = (string) data_get($channel->config, 'bot_token', '');
        $this->showChannelForm = true;
    }

    public function saveChannel(): void
    {
        $this->validate([
            'channelType' => ['required', 'in:email,telegram'],
            'channelName' => ['required', 'string', 'max:120'],
        ]);
        if ($this->channelType === 'email') {
            $emails = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $this->channelEmails) ?: []))));
            $invalid = array_filter($emails, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));
            if ($emails === [] || $invalid !== []) {
                $this->addError('channelEmails', $invalid ? 'Invalid address: '.implode(', ', $invalid) : 'Add at least one e-mail address.');

                return;
            }
            $config = ['emails' => $emails];
        } else {
            if (trim($this->channelChatId) === '') {
                $this->addError('channelChatId', 'Chat ID is required.');

                return;
            }
            if (trim($this->channelBotToken) === '' && ! config('loggerxo.telegram_bot_token')) {
                $this->addError('channelBotToken', 'No global bot token is configured, so a bot token is required here.');

                return;
            }
            $config = ['chat_id' => trim($this->channelChatId), 'bot_token' => trim($this->channelBotToken) ?: null];
        }

        AlertChannel::updateOrCreate(
            ['id' => $this->channelId, 'project_id' => $this->project->id],
            ['type' => $this->channelType, 'name' => $this->channelName, 'config' => $config, 'is_active' => true]
        );
        $this->showChannelForm = false;
        session()->flash('status', 'Channel saved.');
    }

    public function toggleChannel(int $id): void
    {
        $channel = $this->project->alertChannels()->findOrFail($id);
        $channel->update(['is_active' => ! $channel->is_active]);
    }

    public function deleteChannel(int $id): void
    {
        $this->project->alertChannels()->findOrFail($id)->delete();
        foreach ($this->project->alertRules as $rule) {
            $ids = array_values(array_filter((array) $rule->channel_ids, fn ($c) => (int) $c !== $id));
            if ($ids !== $rule->channel_ids) {
                $rule->update(['channel_ids' => $ids]);
            }
        }
    }

    public function testChannel(int $id): void
    {
        $channel = $this->project->alertChannels()->findOrFail($id);
        try {
            app(AlertSender::class)->test($channel);
            $channel->forceFill(['last_used_at' => now(), 'last_error' => null])->save();
            session()->flash('status', 'Test alert sent through "'.$channel->name.'".');
        } catch (\Throwable $e) {
            $channel->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
            session()->flash('error', 'Test failed: '.$e->getMessage());
        }
    }

    /* ------------------------------------------------------------- rules */

    public function newRule(): void
    {
        $this->reset(['ruleId', 'ruleName', 'ruleTrigger', 'ruleChannels', 'ruleCooldown', 'ruleThreshold', 'ruleWindow', 'ruleMinLevel', 'ruleEnvironments', 'ruleTypes']);
        $this->ruleName = 'Notify on new issues';
        $this->ruleChannels = $this->project->alertChannels()->where('is_active', true)->pluck('id')->map(fn ($i) => (string) $i)->all();
        $this->showRuleForm = true;
    }

    public function editRule(int $id): void
    {
        $rule = $this->project->alertRules()->findOrFail($id);
        $this->ruleId = $rule->id;
        $this->ruleName = $rule->name;
        $this->ruleTrigger = $rule->trigger;
        $this->ruleChannels = array_map('strval', (array) $rule->channel_ids);
        $this->ruleCooldown = $rule->cooldown_minutes;
        $this->ruleThreshold = (int) $rule->condition('threshold', 10);
        $this->ruleWindow = (int) $rule->condition('window_minutes', 5);
        $this->ruleMinLevel = (string) $rule->condition('min_level', 'critical');
        $this->ruleEnvironments = implode(', ', (array) $rule->condition('environments', []));
        $this->ruleTypes = (array) $rule->condition('types', []);
        $this->showRuleForm = true;
    }

    public function saveRule(): void
    {
        $this->validate([
            'ruleName' => ['required', 'string', 'max:120'],
            'ruleTrigger' => ['required', 'in:'.implode(',', array_keys(AlertRule::TRIGGERS))],
            'ruleChannels' => ['required', 'array', 'min:1'],
            'ruleCooldown' => ['required', 'integer', 'min:0', 'max:10080'],
            'ruleThreshold' => ['required', 'integer', 'min:1'],
            'ruleWindow' => ['required', 'integer', 'min:1', 'max:1440'],
            'ruleMinLevel' => ['required', 'in:'.implode(',', Levels::all())],
        ], ['ruleChannels.required' => 'Pick at least one channel.']);

        $validChannels = $this->project->alertChannels()->whereIn('id', array_map('intval', $this->ruleChannels))->pluck('id')->all();
        AlertRule::updateOrCreate(
            ['id' => $this->ruleId, 'project_id' => $this->project->id],
            [
                'name' => $this->ruleName,
                'trigger' => $this->ruleTrigger,
                'channel_ids' => $validChannels,
                'cooldown_minutes' => $this->ruleCooldown,
                'is_active' => true,
                'conditions' => [
                    'threshold' => $this->ruleThreshold,
                    'window_minutes' => $this->ruleWindow,
                    'min_level' => $this->ruleMinLevel,
                    'environments' => array_values(array_filter(array_map('trim', explode(',', $this->ruleEnvironments)))),
                    'types' => array_values($this->ruleTypes),
                ],
            ]
        );
        Cache::forget('lx:has-rules:'.$this->project->id);
        $this->showRuleForm = false;
        session()->flash('status', 'Alert rule saved.');
    }

    public function toggleRule(int $id): void
    {
        $rule = $this->project->alertRules()->findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
        Cache::forget('lx:has-rules:'.$this->project->id);
    }

    public function deleteRule(int $id): void
    {
        $this->project->alertRules()->findOrFail($id)->delete();
        Cache::forget('lx:has-rules:'.$this->project->id);
    }

    public function render()
    {
        $this->project->refresh();

        return view('livewire.projects.settings', [
            'platforms' => Project::PLATFORMS,
            'channels' => $this->project->alertChannels()->orderBy('id')->get(),
            'rules' => $this->project->alertRules()->orderBy('id')->get(),
            'notifications' => AlertNotification::where('project_id', $this->project->id)->with(['issue', 'channel'])->latest()->limit(20)->get(),
            'levels' => Levels::all(),
            'triggers' => AlertRule::TRIGGERS,
            'appUrl' => rtrim(config('app.url'), '/'),
        ])->layoutData($this->layoutFor('Settings'));
    }
}
