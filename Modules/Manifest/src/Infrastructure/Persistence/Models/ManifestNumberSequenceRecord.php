<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class ManifestNumberSequenceRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'manifest_number_sequences';

    protected $guarded = ['*'];
}
