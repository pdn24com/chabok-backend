<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class AuditEventRecord extends Model
{
    use HasNumericIdentity;

    public const UPDATED_AT = null;

    protected $table = 'audit_events';

    protected $guarded = ['*'];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'initiator_id', 'id');
    }

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array'];
    }
}
