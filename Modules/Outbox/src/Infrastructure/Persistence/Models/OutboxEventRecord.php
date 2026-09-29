<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Outbox\Domain\Enums\PublicationState;

final class OutboxEventRecord extends Model
{
    use HasNumericIdentity;

    public const UPDATED_AT = null;

    protected $table = 'outbox_events';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'event_version' => 'integer', 'publication_state' => PublicationState::class];
    }
}
