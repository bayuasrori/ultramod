<?php

namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAppPermission extends Model
{
    public $timestamps = false;

    protected $table = 'platform_app_permissions';

    protected $fillable = ['app_id', 'name'];
}
