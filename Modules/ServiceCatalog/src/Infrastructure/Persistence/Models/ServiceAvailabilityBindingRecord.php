<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class ServiceAvailabilityBindingRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_availability_bindings';

    protected $guarded = ['*'];
}
