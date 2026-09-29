<?php

declare(strict_types=1);

namespace Modules\CrmSales\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CrmSales\Domain\Enums\SalesDocumentStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** One content revision of a sales document; an issued revision is frozen and superseded, never edited. */
final class SalesDocumentVersionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_sales_document_versions';

    protected $guarded = ['*'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocumentRecord::class, 'document_id');
    }

    protected function casts(): array
    {
        return [
            'status' => SalesDocumentStatus::class,
            'total' => 'integer',
            'customer_snapshot' => 'array',
            'expires_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
