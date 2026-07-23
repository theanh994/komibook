<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_ticket_id',
        'sender_id',
        'message',
        'attachment',
    ];

    protected $hidden = [
        'attachment',
    ];

    protected $appends = [
        'has_attachment',
        'attachment_url',
    ];

    public function getHasAttachmentAttribute(): bool
    {
        return ! empty($this->attributes['attachment']);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->attributes['attachment'])) {
            return null;
        }

        return "/api/support/tickets/{$this->support_ticket_id}/messages/{$this->id}/attachment";
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
