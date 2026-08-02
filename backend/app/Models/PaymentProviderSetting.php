<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProviderSetting extends Model
{
    protected $fillable = ['provider', 'enabled_by_admin', 'mode', 'updated_by', 'reason'];

    protected $casts = ['enabled_by_admin' => 'boolean'];
}
