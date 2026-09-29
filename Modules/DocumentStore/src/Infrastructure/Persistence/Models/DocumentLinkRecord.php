<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/**
 * What one document is attached to. The pair of type and ID is polymorphic, so which table the ID is
 * read against is decided by the type and by the registry in RecordSchema, never guessed.
 */
final class DocumentLinkRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'document_links';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'resource_type' => DocumentResourceType::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
