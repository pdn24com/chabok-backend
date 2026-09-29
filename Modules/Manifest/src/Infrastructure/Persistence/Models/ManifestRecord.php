<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Enums\ManifestType;

final class ManifestRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'manifests';

    protected $guarded = ['*'];

    public function parcels(): HasMany
    {
        return $this->hasMany(ManifestParcelRecord::class, 'manifest_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'state' => ManifestState::class,
            'manifest_type' => ManifestType::class,
            'operational_context_type' => ManifestContextType::class,
        ];
    }
}
