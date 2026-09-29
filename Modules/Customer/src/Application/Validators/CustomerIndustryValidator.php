<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Validators;

use Modules\CrmCatalog\Application\Repositories\IndustryRepositoryInterface;
use Modules\Customer\Application\Contracts\CustomerIndustryValidatorInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CustomerIndustryValidator implements CustomerIndustryValidatorInterface
{
    public function __construct(private IndustryRepositoryInterface $industryRepository) {}

    public function validate(string $hqId, array $items): void
    {
        $seen = [];
        $primaries = 0;
        foreach ($items as $index => $item) {
            $path = 'items.'.$index;
            // The unique index over (tenant, customer, industry) is not there under sqlite, so the set is checked here.
            if (isset($seen[$item->industryId])) {
                throw $this->invalid($path.'.industry_id', 'customer.industry_is_duplicated');
            }
            $seen[$item->industryId] = true;
            if ($item->isPrimary && ++$primaries > 1) {
                throw $this->invalid($path.'.is_primary', 'customer.only_one_primary_industry_is_allowed');
            }
            if (! $this->industryRepository->activeExistsInTenant($hqId, $item->industryId)) {
                throw $this->invalid($path.'.industry_id', 'customer.select_active_industry_from_reference_data');
            }
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
