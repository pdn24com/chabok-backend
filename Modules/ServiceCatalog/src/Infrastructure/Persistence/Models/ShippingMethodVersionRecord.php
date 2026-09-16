<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ShippingMethodVersionRecord extends Model
{
    protected $table = 'shipping_method_versions';
    protected $primaryKey = 'shipping_method_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
