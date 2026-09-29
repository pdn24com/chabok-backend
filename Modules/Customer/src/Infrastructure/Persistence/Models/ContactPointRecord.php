<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class ContactPointRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_contact_points';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verified_manually_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
