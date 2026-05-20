<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $title;
    public string $messageContent;
    public ?string $imageUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $title, string $messageContent, ?string $imageUrl = null)
    {
        $this->user = $user;
        $this->title = $title;
        $this->messageContent = $messageContent;
        $this->imageUrl = $imageUrl;
        $this->queue = 'emails';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title . ' - KomiBook',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign',
            with: [
                'user' => $this->user,
                'title' => $this->title,
                'messageContent' => $this->messageContent,
                'imageUrl' => $this->imageUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
