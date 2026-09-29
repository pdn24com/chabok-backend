<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Application\Services\CustomerProfileGaps;
use Modules\Customer\Application\UseCases\GetCustomerDetail\GetCustomerDetailResult;

/** @mixin GetCustomerDetailResult */
final class CustomerDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer' => $this->summary($request),
            'default_address' => $this->defaultAddress(),
            'primary_industry' => $this->primaryIndustry($request),
            'open_tasks' => CustomerTaskResource::collection($this->openTasks)->resolve($request),
            'opportunities' => CustomerOpportunityResource::collection($this->openOpportunities)->resolve($request),
            'missing' => CustomerProfileGaps::of($this->customer),
        ];
    }

    private function summary(Request $request): array
    {
        $customer = $this->customer;

        return [
            ...(new CustomerIdentityResource($customer))->resolve($request),
            'converted_at' => $customer->converted_at?->toISOString(),
            // Both counts describe the very lists below, which are never truncated.
            'open_opportunities_counts' => $this->openOpportunities->count(),
            'open_tasks_counts' => $this->openTasks->count(),
        ];
    }

    /** Abroad there is no canonical city, so the typed city name stands in for the reference one. */
    private function defaultAddress(): ?array
    {
        $address = $this->customer->defaultAddress;

        return $address === null ? null : ['city' => $address->city?->name_fa ?? $address->foreign_city];
    }

    private function primaryIndustry(Request $request): ?array
    {
        $industry = $this->customer->primaryIndustry?->industry;

        return $industry === null ? null : (new CustomerIndustryResource($industry))->resolve($request);
    }
}
