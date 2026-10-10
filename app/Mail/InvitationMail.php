<?php

namespace App\Mail;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrganizationInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You are invited to '.$this->invitation->organization->name.' on Loggerxo');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation', with: [
            'invitation' => $this->invitation,
            'organization' => $this->invitation->organization,
            'inviter' => $this->invitation->inviter,
            'url' => $this->invitation->acceptUrl(),
        ]);
    }
}
