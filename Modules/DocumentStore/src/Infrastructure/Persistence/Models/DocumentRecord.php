<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/**
 * The metadata of one stored document. The bytes of the file and its versions are a separate decision
 * and no column here stands in for them.
 */
final class DocumentRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'documents';

    protected $guarded = ['*'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategoryRecord::class, 'category_id');
    }

    /** In the order they were made, so a document reads the same way on every request. */
    public function links(): HasMany
    {
        return $this->hasMany(DocumentLinkRecord::class, 'document_id')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'expires_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
