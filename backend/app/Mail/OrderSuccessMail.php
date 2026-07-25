<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class OrderSuccessMail extends Mailable
{
    use SerializesModels;

    public Order $order;

    public ?string $operationKey;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order, ?string $operationKey = null)
    {
        $this->order = $order;
        $this->operationKey = $operationKey;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác nhận đơn hàng #'.$this->order->order_code.' thành công - KomiBook',
        );
    }

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        if ($this->operationKey) {
            $hash = md5($this->operationKey);

            return new Headers(
                messageId: "{$hash}@komibook.local",
            );
        }

        return new Headers;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order_success',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
