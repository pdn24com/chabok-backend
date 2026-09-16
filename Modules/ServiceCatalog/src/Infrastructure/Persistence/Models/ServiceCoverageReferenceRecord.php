<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ServiceCoverageReferenceRecord extends Model
{
    protected $table = 'service_coverage_references';
    protected $primaryKey = 'coverage_reference_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
