<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code)
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
        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('비밀번호 재설정 인증코드')
            ->greeting('비밀번호 재설정 요청')
            ->line('아래 인증코드를 입력하여 비밀번호 재설정을 진행하세요.')
            ->line('인증코드: '.$this->code)
            ->line("인증코드는 {$expireMinutes}분 동안 유효합니다.")
            ->line('직접 요청하지 않았다면 이 메일을 무시하세요.');
    }
}
