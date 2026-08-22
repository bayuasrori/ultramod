<?php

namespace PlatformApps\Customers\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'full_name', 'email', 'notes', 'vip',
    ];

    protected function casts(): array
    {
        return [
            'vip' => 'boolean',
        ];
    }
}
