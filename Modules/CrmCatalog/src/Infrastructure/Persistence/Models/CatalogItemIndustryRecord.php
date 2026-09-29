<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CatalogItemIndustryRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_catalog_item_industry';

    protected $guarded = ['*'];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(IndustryRecord::class, 'industry_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
