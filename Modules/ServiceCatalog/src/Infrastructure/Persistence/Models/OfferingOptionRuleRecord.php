<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class OfferingOptionRuleRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_offering_option_rules';

    protected $guarded = ['*'];

    public function optionVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceOptionVersionRecord::class, 'service_option_version_id', 'id');
    }

    protected function casts(): array
    {
        return ['condition' => 'array'];
    }
}
