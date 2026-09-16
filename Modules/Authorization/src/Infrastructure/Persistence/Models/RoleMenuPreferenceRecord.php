<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class RoleMenuPreferenceRecord extends Model
{
    protected $table = 'role_menu_preferences';
    protected $primaryKey = 'role_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
