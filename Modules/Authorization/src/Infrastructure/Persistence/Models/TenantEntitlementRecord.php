<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class TenantEntitlementRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'tenant_module_entitlements';

    protected $guarded = ['*'];
}
