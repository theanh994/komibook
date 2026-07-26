<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationEmailOtp extends Notification
{
    use Queueable;

    public function __construct(private readonly string $otp)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mã xác thực đăng ký KomiBook')
            ->greeting('Xin chào!')
            ->line('Mã OTP để hoàn tất đăng ký tài khoản KomiBook của bạn là:')
            ->line($this->otp)
            ->line('Mã gồm 8 chữ số và có hiệu lực trong 5 phút.')
            ->line('Nếu bạn không yêu cầu đăng ký, hãy bỏ qua email này.');
    }
}
