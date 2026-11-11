<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordSiapOsn extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = trim(explode(',', (string) config('services.google.frontend_url'))[0]);
        $link = rtrim($frontendUrl, '/').'/reset-password?'.http_build_query([
            'email' => $notifiable->getEmailForPasswordReset(),
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi SIAP OSN')
            ->greeting('Halo!')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi SIAP OSN.')
            ->action('Buat Kata Sandi Baru', $link)
            ->line('Tautan berlaku selama '.config('auth.passwords.users.expire').' menit.')
            ->line('Jika bukan Anda yang meminta, abaikan email ini.');
    }
}
