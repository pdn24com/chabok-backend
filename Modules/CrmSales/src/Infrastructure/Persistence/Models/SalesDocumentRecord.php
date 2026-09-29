<?php

declare(strict_types=1);

namespace Modules\CrmSales\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;
use Modules\CrmSales\Domain\Enums\SalesDocumentType;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** The identity of a sales document, kept apart from the content versions that hang off it. */
final class SalesDocumentRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_sales_documents';

    protected $guarded = ['*'];

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentVersionRecord::class, 'current_version_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerRecord::class, 'customer_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(OpportunityRecord::class, 'opportunity_id');
    }

    /** Every revision the document has been through, oldest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(SalesDocumentVersionRecord::class, 'document_id')->orderBy('version_no');
    }

    protected function casts(): array
    {
        return ['document_type' => SalesDocumentType::class, 'created_at' => 'immutable_datetime'];
    }
}
