<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CommitmentScheduleScopeRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'commitment_schedule_scopes';

    protected $guarded = ['*'];
}
