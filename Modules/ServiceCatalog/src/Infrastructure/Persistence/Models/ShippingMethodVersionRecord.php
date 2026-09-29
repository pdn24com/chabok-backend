<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogVersionRecordInterface;

final class ShippingMethodVersionRecord extends Model implements CatalogVersionRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'shipping_method_versions';

    protected $guarded = ['*'];

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethodRecord::class, 'shipping_method_id', 'id');
    }

    protected function casts(): array
    {
        return ['labels' => 'array', 'definition' => 'array'];
    }
}
