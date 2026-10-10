<?php

namespace App\Mail;

use App\Models\AlertRule;
use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IssueAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Issue $issue,
        public ?AlertRule $rule,
        public string $reason,
        public array $flags = [],
    ) {}

    public function envelope(): Envelope
    {
        $project = $this->issue->project?->name ?? 'project';

        return new Envelope(
            subject: sprintf('[%s] %s: %s', $project, $this->reason, mb_substr($this->issue->title, 0, 120)),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.issue-alert',
            with: [
                'issue' => $this->issue,
                'project' => $this->issue->project,
                'reason' => $this->reason,
                'rule' => $this->rule,
                'environment' => $this->flags['environment'] ?? ($this->issue->environments[0] ?? 'production'),
                'url' => $this->issue->id ? $this->issue->url() : rtrim(config('app.url'), '/'),
            ],
        );
    }
}
