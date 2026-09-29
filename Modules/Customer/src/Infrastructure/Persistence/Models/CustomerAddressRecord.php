<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;

final class CustomerAddressRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_customer_address';

    protected $guarded = ['*'];

    /** The address stores the ISO code, so the reference row is reached through the code itself. */
    public function country(): BelongsTo
    {
        return $this->belongsTo(CountryRecord::class, 'country_code', 'country_code');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(CityRecord::class, 'city_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(ProvinceRecord::class, 'province_id');
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            // The stored precision is the contract; a float would round the pin the operator dropped.
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
