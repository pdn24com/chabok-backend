<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CommitmentScheduleScopeRecord extends Model
{
    protected $table = 'commitment_schedule_scopes';
    protected $primaryKey = 'commitment_schedule_scope_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
