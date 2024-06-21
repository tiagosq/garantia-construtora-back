<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class PasswordResetToken extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected User $user,
        protected string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  '['.env('APP_NAME').'] Pedido de alteração de senha',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'Mail.PasswordResetToken',
            with: [
                'fullname' => $this->user->fullname,
                'token' => $this->token,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
