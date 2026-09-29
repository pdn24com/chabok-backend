<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogIdentityRecordInterface;

final class ServiceOptionRecord extends Model implements CatalogIdentityRecordInterface
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'service_options';

    protected $guarded = ['*'];

    public function versions(): HasMany
    {
        return $this->hasMany(ServiceOptionVersionRecord::class, 'service_option_id', 'id');
    }

    public function publishedVersions(): HasMany
    {
        return $this->hasMany(ServiceOptionVersionRecord::class, 'service_option_id', 'id')->where('status', VersionLifecycleStatus::Published->value)->orderByDesc('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ServiceOptionVersionRecord::class, 'service_option_id', 'id')->ofMany('version_number', 'max');
    }
}
