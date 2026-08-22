<?php
namespace App\Platform\Services;

use App\Platform\Models\PlatformAuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function log(string $action, ?Model $target = null, array $metadata = [], ?int $actorId = null): PlatformAuditLog
    {
        return PlatformAuditLog::create([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target ? $target->getKey() : null,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }
}
