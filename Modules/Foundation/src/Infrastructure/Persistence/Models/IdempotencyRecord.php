<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Domain\Enums\IdempotencyState;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class IdempotencyRecord extends Model
{
    use HasNumericIdentity;

    protected $table = 'idempotency_records';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['state' => IdempotencyState::class, 'response_status' => 'integer'];
    }
}
