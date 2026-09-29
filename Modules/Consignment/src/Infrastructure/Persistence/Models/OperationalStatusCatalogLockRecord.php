<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class OperationalStatusCatalogLockRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'operational_status_catalog_lock';

    protected $guarded = ['*'];
}
