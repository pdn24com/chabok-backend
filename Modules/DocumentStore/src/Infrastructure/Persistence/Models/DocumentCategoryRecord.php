<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** One node of the document category tree. The parent belongs to the same tenant and never cycles. */
final class DocumentCategoryRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'document_categories';

    protected $guarded = ['*'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }
}
