<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use LogicException;
use Modules\Foundation\Domain\Exceptions\ApiException;

/** A request-local batch; errors are raised only if the offering actually uses that group. */
final readonly class CommitmentDestinationsDto
{
    /** @param array<string, CommitmentDestinationDto|ApiException> $groups */
    public function __construct(private array $groups) {}

    public function forGroup(string $groupId): CommitmentDestinationDto
    {
        $destination = $this->groups[$groupId] ?? throw new LogicException('Destination group was not loaded.');
        if ($destination instanceof ApiException) {
            throw $destination;
        }

        return $destination;
    }
}
