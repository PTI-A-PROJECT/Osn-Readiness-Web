<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = (string) config('services.password_reset.frontend_url');
        $frontendUrl = trim(explode(',', $frontendUrl)[0]);
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $resetUrl = rtrim($frontendUrl, '/') . '/reset-password?' . $query;

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi SIAP OSN')
            ->greeting('Halo!')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun SIAP OSN Anda.')
            ->action('Atur Ulang Kata Sandi', $resetUrl)
            ->line('Tautan ini berlaku selama ' . (int) config('auth.passwords.users.expire') . ' menit.')
            ->line('Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.')
            ->salutation('Salam, Tim SIAP OSN');
    }
}
