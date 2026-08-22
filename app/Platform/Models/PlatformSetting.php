<?php
namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $guarded = [];
    protected $casts = ['value' => 'json'];
}
