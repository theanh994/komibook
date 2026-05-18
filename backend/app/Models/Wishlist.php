<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
    ];

    /**
     * Người dùng sở hữu mục yêu thích này.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cuốn sách trong danh sách yêu thích.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
