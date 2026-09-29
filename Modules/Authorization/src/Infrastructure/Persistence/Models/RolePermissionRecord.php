<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class RolePermissionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'role_permissions';

    protected $guarded = ['*'];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(PermissionRecord::class, 'permission_id', 'id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleRecord::class, 'role_id', 'id');
    }
}
