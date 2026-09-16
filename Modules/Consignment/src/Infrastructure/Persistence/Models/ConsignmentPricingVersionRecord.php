<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ConsignmentPricingVersionRecord extends Model
{
    protected $table = 'consignment_pricing_versions';
    protected $primaryKey = 'pricing_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
