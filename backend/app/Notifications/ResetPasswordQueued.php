<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

class ResetPasswordQueued extends ResetPassword implements ShouldQueue
{
    // Sử dụng lại Trait này để có đầy đủ các thuộc tính $connection, $queue, v.v.
    use Queueable;

    /**
     * Số lần thử lại tối đa.
     * @var int
     */
    public $tries = 3;

    /**
     * Ghi đè phương thức khởi tạo.
     */
    public function __construct($token)
    {
        parent::__construct($token);
        
        // Thiết lập hàng đợi trong constructor
        $this->queue = 'emails';
    }

    /**
     * Xây dựng URL cho liên kết đặt lại mật khẩu.
     * Vì đây là SPA, chúng ta cần trỏ link về Frontend thay vì Backend API.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function resetUrl($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'https://komibook.id.vn');
        return $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    }

    /**
     * Thời gian chờ giữa các lần thử.
     */
    public function backoff()
    {
        return 30;
    }
}
