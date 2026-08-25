<?php

namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One executed (or attempted) upgrade step. The table doubles as the audit
 * trail shown in the UI and as the idempotency ledger the upgrader consults
 * before running a step again.
 */
class PlatformAppUpgrade extends Model
{
    public const PHASE_PRE = 'pre';

    public const PHASE_MIGRATIONS = 'migrations';

    public const PHASE_POST = 'post';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STEP_MIGRATIONS = 'migrations';

    protected $table = 'platform_app_upgrades';

    protected $fillable = [
        'app_id', 'from_version', 'to_version', 'step', 'phase',
        'status', 'duration_ms', 'output',
    ];
}
