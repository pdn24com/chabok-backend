<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ManifestNumberSequenceRecord extends Model
{
    protected $table = 'manifest_number_sequences';
    protected $primaryKey = 'sequence_key';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
