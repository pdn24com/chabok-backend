<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class TariffServiceAttachmentRecord extends Model
{
    protected $table = 'tariff_service_attachments';
    protected $primaryKey = 'tariff_version_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
