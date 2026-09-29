<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

final readonly class RouteLegDto
{
    public function __construct(public int $order, public string $originNodeId, public string $destinationNodeId) {}

    public static function fromValidated(array $input): self
    {
        return new self((int) $input['leg_order'], $input['origin_node_id'], $input['destination_node_id']);
    }
}
