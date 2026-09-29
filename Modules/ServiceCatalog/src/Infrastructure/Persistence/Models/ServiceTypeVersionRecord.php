<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogVersionRecordInterface;

final class ServiceTypeVersionRecord extends Model implements CatalogVersionRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_type_versions';

    protected $guarded = ['*'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ServiceTypeRecord::class, 'service_type_id', 'id');
    }

    protected function casts(): array
    {
        return ['labels' => 'array', 'definition' => 'array'];
    }
}
