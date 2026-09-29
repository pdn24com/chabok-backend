<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogIdentityRecordInterface;

final class ShippingMethodRecord extends Model implements CatalogIdentityRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'shipping_methods';

    protected $guarded = ['*'];

    public function versions(): HasMany
    {
        return $this->hasMany(ShippingMethodVersionRecord::class, 'shipping_method_id', 'id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ShippingMethodVersionRecord::class, 'shipping_method_id', 'id')->ofMany('version_number', 'max');
    }
}
