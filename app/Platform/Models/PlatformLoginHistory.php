<?php

namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformLoginHistory extends Model
{
    protected $table = 'platform_login_history';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'email', 'ip_address', 'user_agent', 'successful', 'login_at', 'logout_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
    ];
}
