<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ValidateNumberRange;

use Modules\Consignment\Application\Contracts\ConsignmentNumberRangeDefinitionInterface;
use Modules\Consignment\Application\Contracts\NumberRangeAccessInterface;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;

final readonly class ValidateNumberRangeHandler
{
    public function __construct(
        private NumberRangeAccessInterface $numberRangeAccess,
        private ConsignmentNumberRangeDefinitionInterface $consignmentNumberRangeDefinition,
        private NumberRangeRepositoryInterface $numberRangeRepository,
    ) {}

    public function handle(ValidateNumberRangeCommand $command): ValidateNumberRangeResult
    {
        $actor = $command->actor;
        $input = $command->input;
        $this->numberRangeAccess->access($actor, 'consignment.number_range.manage');
        $preview = $this->consignmentNumberRangeDefinition->validate($input);
        $overlap = $this->numberRangeRepository->overlaps($preview);

        return new ValidateNumberRangeResult($preview, $overlap);
    }
}
