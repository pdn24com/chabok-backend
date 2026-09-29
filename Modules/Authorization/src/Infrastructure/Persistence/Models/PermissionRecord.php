<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class PermissionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'permissions';

    protected $guarded = ['*'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleRecord::class, 'role_permissions', 'permission_id', 'role_id', 'id', 'id');
    }
}
