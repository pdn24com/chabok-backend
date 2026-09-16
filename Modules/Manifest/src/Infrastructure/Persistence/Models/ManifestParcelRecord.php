<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ManifestParcelRecord extends Model
{
    protected $table = 'manifest_parcels';
    protected $primaryKey = 'manifest_parcel_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
