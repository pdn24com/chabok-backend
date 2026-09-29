<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class TariffServiceAttachmentRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'tariff_service_attachments';

    protected $guarded = ['*'];

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(TariffVersionRecord::class, 'tariff_version_id', 'id');
    }
}
