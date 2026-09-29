<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Modules\ServiceCatalog\Domain\Enums\CommitmentScopeType;

final readonly class ScheduleScopeDto
{
    public function __construct(public CommitmentScopeType $type, public ?string $nodeId = null) {}

    public static function fromValidated(array $input): self
    {
        $type = CommitmentScopeType::from($input['scope_type']);

        return new self($type, $type === CommitmentScopeType::Node ? $input['node_id'] : null);
    }
}
