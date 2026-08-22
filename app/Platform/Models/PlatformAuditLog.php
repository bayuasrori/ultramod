<?php
namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    protected $guarded = [];
    protected $casts = ['metadata' => 'json'];

    public function target()
    {
        return $this->morphTo();
    }
}
