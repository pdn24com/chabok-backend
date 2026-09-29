<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\IndustryRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class CustomerIndustryRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_customer_industry';

    protected $guarded = ['*'];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(IndustryRecord::class, 'industry_id');
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'created_at' => 'immutable_datetime'];
    }
}
