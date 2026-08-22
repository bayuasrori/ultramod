<?php
namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformFile extends Model
{
    protected $guarded = [];

    public function attachment()
    {
        return $this->morphTo();
    }
}
