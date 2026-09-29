<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class CustomerRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_customers';

    protected $guarded = ['*'];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'assignee_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddressRecord::class, 'customer_id')->where('is_default', true);
    }

    public function defaultMobile(): HasOne
    {
        return $this->hasOne(ContactPointRecord::class, 'customer_id')->where(['type' => 'MOBILE', 'is_default' => true]);
    }

    public function primaryIndustry(): HasOne
    {
        return $this->hasOne(CustomerIndustryRecord::class, 'customer_id')->where('is_primary', true);
    }

    protected function casts(): array
    {
        return [
            'kind' => CustomerKind::class,
            'phase' => CustomerPhase::class,
            'converted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
