<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ServiceOfferingVersionRecord extends Model
{
    protected $table = 'service_offering_versions';
    protected $primaryKey = 'service_offering_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
