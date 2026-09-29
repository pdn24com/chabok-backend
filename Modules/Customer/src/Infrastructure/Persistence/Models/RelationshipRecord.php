<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class RelationshipRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_relationships';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
