<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\DeliveryTaskStatus;

final readonly class DeliveryTaskFiltersDto
{
    public function __construct(public ?DeliveryTaskStatus $status = null, public ?string $search = null) {}

    public static function fromValidated(array $input): self
    {
        return new self(($input['status'] ?? '') === '' ? null : DeliveryTaskStatus::from($input['status']), $input['search'] ?? null);
    }
}
