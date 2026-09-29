<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Consignment\Domain\Enums\ConsignmentStatusGroup;
use Modules\Consignment\Domain\Enums\OperationalStatusTone;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class StatusRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'operational_statuses';

    protected $guarded = ['*'];

    public function scopeVisibleTo(Builder $query, ?string $hqId): Builder
    {
        return $query->where(fn ($scope) => $scope->whereNull('hq_id')->when($hqId !== null, fn ($scope) => $scope->orWhere('hq_id', $hqId)));
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean', 'is_active' => 'boolean', 'is_terminal' => 'boolean', 'manifest_enabled' => 'boolean',
            'version' => 'integer', 'sort_order' => 'integer', 'tone' => OperationalStatusTone::class, 'status_group' => ConsignmentStatusGroup::class,
        ];
    }
}
