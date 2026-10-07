<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The mobile app's password reset: a short code typed into the app instead of
 * the web's link, so the reset never leaves the phone.
 */
class PasswordResetCode extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected int $minutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Lectura password reset code')
            ->greeting("Hello {$notifiable->name},")
            ->line('Enter this code in the Lectura Go app to choose a new password:')
            ->line("**{$this->code}**")
            ->line("It expires in {$this->minutes} minutes. If you did not ask to reset your password, you can ignore this email.");
    }
}
