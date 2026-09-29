<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class NumberAllocationRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'consignment_number_allocations';

    protected $guarded = ['*'];
}
