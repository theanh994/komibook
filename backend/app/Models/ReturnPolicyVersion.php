<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnPolicyVersion extends Model
{
    protected $fillable = [
        'policy_key', 'version', 'applies_to', 'is_returnable',
        'return_window_days', 'terms', 'active_from', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'is_returnable' => 'boolean',
            'return_window_days' => 'integer',
            'active_from' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
