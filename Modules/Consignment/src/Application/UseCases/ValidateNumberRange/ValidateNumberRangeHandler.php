<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ValidateNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateNumberRangeHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\NumberRangeAccess $numberRangeAccess,
        private \Modules\Consignment\Domain\ConsignmentNumberRangeDefinition $definition,
        private \Modules\Consignment\Application\Services\NumberRangeOverlap $numberRangeOverlap,
    )
    {
    }

    public function handle(ValidateNumberRangeCommand $command): ValidateNumberRangeResult
    {
        return new ValidateNumberRangeResult($this->execute($command->actor, $command->input));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input): array
    {
        $this->numberRangeAccess->access($actor, 'consignment.number_range.manage');
        $preview = $this->definition->validate($input);
        $overlap = $this->numberRangeOverlap->overlaps($preview);
        return [...$preview, 'overlaps_existing_range' => $overlap, 'validation_result' => $overlap ? 'OVERLAP' : 'VALID'];
    }
}
