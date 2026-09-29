<?php

declare(strict_types=1);

namespace Modules\CrmSales\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** The identity file of a contract signed with a customer; its documents live in the archive, linked by type. */
final class ContractRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_contracts';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
