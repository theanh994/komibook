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
     * Ghi đè phương thức khởi tạo.
     */
    public function __construct($token)
    {
        parent::__construct($token);
        
        // Thiết lập hàng đợi trong constructor để tránh lỗi "Incompatible definition"
        $this->queue = 'emails';
        $this->tries = 3;
    }

    /**
     * Thời gian chờ giữa các lần thử.
     */
    public function backoff()
    {
        return 30;
    }
}
