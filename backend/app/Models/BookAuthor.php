<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookAuthor extends Model
{
    use HasFactory;

    protected $fillable = ['book_id', 'author_id', 'invited_by', 'role', 'status', 'accepted_at', 'revoked_at', 'reason', 'operation_key'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
