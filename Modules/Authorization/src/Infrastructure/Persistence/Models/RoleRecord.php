<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class RoleRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'roles';

    protected $guarded = ['*'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PermissionRecord::class, 'role_permissions', 'role_id', 'permission_id', 'id', 'id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssignmentRecord::class, 'role_id', 'id');
    }

    public function menuPreference(): HasOne
    {
        return $this->hasOne(RoleMenuPreferenceRecord::class, 'role_id', 'id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(RoleMenuItemRecord::class, 'role_id', 'id')->orderBy('menu_key');
    }
}
