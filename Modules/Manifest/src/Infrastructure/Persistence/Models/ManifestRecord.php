<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ManifestRecord extends Model
{
    protected $table = 'manifests';
    protected $primaryKey = 'manifest_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
