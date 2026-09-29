<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class HqTenantRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'hq_tenants';

    protected $guarded = ['*'];
}
