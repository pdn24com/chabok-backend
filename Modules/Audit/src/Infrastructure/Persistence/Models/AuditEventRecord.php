<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditEventRecord extends Model
{
    protected $table = 'audit_events';
    protected $primaryKey = 'audit_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
