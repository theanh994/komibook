<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatSession extends Model
{
    use HasFactory;

    public const TARGET_PLATFORM = 'platform';

    public const TARGET_VENDOR = 'vendor';

    public const MODE_AI = 'ai';

    public const MODE_HUMAN = 'human';

    public const STATUS_OPEN = 'open';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'vendor_id',
        'assigned_user_id',
        'support_ticket_id',
        'guest_token_hash',
        'conversation_key',
        'target_type',
        'responder_mode',
        'status',
        'subject',
        'category',
        'lock_version',
        'external_ai_consent_version',
        'external_ai_consent_scope',
        'external_ai_consented_at',
        'external_ai_consent_revoked_at',
        'last_message_at',
        'assigned_at',
        'resolved_at',
    ];

    protected $hidden = ['guest_token_hash'];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
            'external_ai_consent_scope' => 'array',
            'external_ai_consented_at' => 'datetime',
            'external_ai_consent_revoked_at' => 'datetime',
            'last_message_at' => 'datetime',
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);
    }

    /** @param list<string> $scope */
    public function hasActiveExternalAiConsent(string $policyVersion, array $scope, ?User $owner = null): bool
    {
        return $this->user_id !== null
            && $owner !== null
            && $owner->id === $this->user_id
            && $owner->role === 'customer'
            && $this->external_ai_consented_at !== null
            && $this->external_ai_consent_revoked_at === null
            && $this->external_ai_consent_version === $policyVersion
            && $this->external_ai_consent_scope === $scope;
    }
}
