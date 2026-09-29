<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class ServiceEligibilityRuleRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_eligibility_rules';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['expected_value' => 'json'];
    }
}
