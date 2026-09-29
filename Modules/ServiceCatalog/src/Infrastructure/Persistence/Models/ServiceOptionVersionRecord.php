<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogVersionRecordInterface;

final class ServiceOptionVersionRecord extends Model implements CatalogVersionRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_option_versions';

    protected $guarded = ['*'];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ServiceOptionRecord::class, 'service_option_id', 'id');
    }

    protected function casts(): array
    {
        return ['labels' => 'array', 'definition' => 'array'];
    }
}
