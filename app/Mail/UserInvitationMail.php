<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Accept your RF IS account invitation');
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Hello '.e($this->user->name).',</p>'
                .'<p>You have been invited to RF IS. Use the secure link below to set your password and activate your account.</p>'
                .'<p><a href="'.e($this->acceptUrl).'">Accept invitation</a></p>'
                .'<p>If you were not expecting this invitation, you can ignore this email.</p>'
        );
    }
}
