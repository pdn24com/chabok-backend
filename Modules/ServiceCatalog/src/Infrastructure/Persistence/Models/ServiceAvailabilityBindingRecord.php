<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ServiceAvailabilityBindingRecord extends Model
{
    protected $table = 'service_availability_bindings';
    protected $primaryKey = 'availability_binding_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
