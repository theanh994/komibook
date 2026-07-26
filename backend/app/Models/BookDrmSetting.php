<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookDrmSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'social_drm',
        'hard_drm',
        'copy_limit_percent',
        'allow_printing',
        'license_type',
    ];

    protected $hidden = ['copyright_number', 'copyright_owner'];

    protected function casts(): array
    {
        return [
            'social_drm' => 'boolean',
            'hard_drm' => 'boolean',
            'allow_printing' => 'boolean',
            'copy_limit_percent' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
