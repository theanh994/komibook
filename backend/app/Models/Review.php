<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'purchase_order_id',
        'verified_purchase',
        'rating',
        'comment',
        'moderation_status',
        'edited_at',
        'moderated_at',
        'moderated_by',
        'moderation_reason',
        'active_key',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_purchase' => 'boolean',
            'edited_at' => 'datetime',
            'moderated_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }
}
