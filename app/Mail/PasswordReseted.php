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

class PasswordReseted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected User $user,
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'charset' => 'UTF-8',
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: mb_convert_encoding('['.env('APP_NAME').'] Alteração de senha realizada!', 'utf8'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'Mail.PasswordReseted',
            with: [
                'fullname' => $this->user->fullname,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
