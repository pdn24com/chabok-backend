<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CatalogItemRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_catalog_items';

    protected $guarded = ['*'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategoryRecord::class, 'category_id');
    }

    public function buyerPersona(): BelongsTo
    {
        return $this->belongsTo(CatalogPersonaRecord::class, 'buyer_persona_id');
    }

    public function salesModel(): BelongsTo
    {
        return $this->belongsTo(CatalogSalesModelRecord::class, 'sales_model_id');
    }

    /** The industries the item is offered to, oldest link first. */
    public function industryLinks(): HasMany
    {
        return $this->hasMany(CatalogItemIndustryRecord::class, 'catalog_item_id')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'kind' => CatalogItemKind::class,
            'status' => CatalogItemStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
