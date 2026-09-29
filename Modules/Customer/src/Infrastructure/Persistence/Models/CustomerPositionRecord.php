<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CustomerPositionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_positions';

    protected $guarded = ['*'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(CustomerDepartmentRecord::class, 'department_id');
    }

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
