<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class OperationalExceptionHistoryRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'operational_exception_history';

    protected $guarded = ['*'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'actor_id', 'id');
    }

    protected function casts(): array
    {
        return ['manifest_version' => 'integer', 'exception_version' => 'integer'];
    }
}
