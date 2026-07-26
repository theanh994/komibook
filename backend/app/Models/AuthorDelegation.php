<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthorDelegation extends Model
{
    use HasFactory;

    protected $fillable = ['grantor_author_id', 'delegate_user_id', 'book_id', 'permissions', 'status', 'starts_at', 'expires_at', 'accepted_at', 'revoked_at', 'reason', 'operation_key'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isActiveFor(string $permission): bool
    {
        return $this->status === 'accepted'
            && in_array($permission, $this->permissions ?? [], true)
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
