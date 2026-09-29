<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class AssignmentRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'user_role_assignments';

    protected $guarded = ['*'];

    public function scopeArea(): BelongsTo
    {
        return $this->belongsTo(AreaRecord::class, 'scope_id', 'id');
    }

    public function scopeNode(): BelongsTo
    {
        return $this->belongsTo(NodeRecord::class, 'scope_id', 'id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleRecord::class, 'role_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'user_id', 'id');
    }
}
