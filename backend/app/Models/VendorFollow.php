<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorFollow extends Model
{
    protected $fillable = ['user_id', 'vendor_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
