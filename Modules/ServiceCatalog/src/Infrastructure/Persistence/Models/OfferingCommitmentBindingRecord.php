<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OfferingCommitmentBindingRecord extends Model
{
    protected $table = 'service_offering_commitment_bindings';
    protected $primaryKey = 'offering_commitment_binding_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
