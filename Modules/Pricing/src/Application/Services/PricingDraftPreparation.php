<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingDraftPreparation
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Pricing\Application\TariffMatrixCompiler $matrixCompiler,
        private \Modules\Pricing\Application\ServiceTariffDependencies $serviceTariffs,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
    )
    {
    }

    public function prepareTariffDraft(AuthenticatedPrincipal $actor, array $input): array
    {
        $kind = $input['tariff_kind'] ?? 'FREIGHT';
        if (!in_array($kind, ['FREIGHT', 'SERVICE'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'نوع تعرفه معتبر نیست.');
        }
        if ($kind === 'FREIGHT' && empty($input['zone_set_version_id'])) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'گروه زون را انتخاب کنید.');
        }
        if ($kind === 'FREIGHT') {
            $input['matrix_basis'] = 'BILLABLE_WEIGHT';
            $input['service_charge_type_id'] = null;
        }
        $input['zone_set_version_id'] = $input['zone_set_version_id'] ?? null;
        $zones = $input['zone_set_version_id'] ? $this->pricingReader->zoneVersion($actor, $input['zone_set_version_id'])['zones'] : [];
        $prepared = $this->matrixCompiler->prepare($input, $zones, $actor->hqId);
        $this->serviceTariffs->resolve($input['service_tariff_family_ids'] ?? [], $actor->hqId, $input['zone_set_version_id'], CarbonImmutable::parse($input['valid_from'] ?? 'now')->max(CarbonImmutable::instance($this->clock->now())), $prepared['rules']);
        return $prepared;
    }

    public function assertTariffReferences(AuthenticatedPrincipal $actor, array $input): void
    {
        $zoneSetVersionId = (string) ($input['zone_set_version_id'] ?? '');
        $visible = $this->pricing->zoneVersionVisible($actor->hqId, $zoneSetVersionId);
        if (!$visible && !(($input['tariff_kind'] ?? 'FREIGHT') === 'SERVICE' && $zoneSetVersionId === '')) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $zoneIds = array_map(fn($id) => (string) $id, $this->pricing->zoneIds($zoneSetVersionId));
        foreach ((array) ($input['rules'] ?? []) as $rule) {
            foreach (['origin_zone_id', 'destination_zone_id'] as $field) {
                $zoneId = $rule[$field] ?? null;
                if ($zoneId !== null && $zoneId !== '' && !in_array((string) $zoneId, $zoneIds, true)) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'A tariff rule references a zone outside the selected Zone Set version.', details: ['field' => "rules.{$field}"]);
                }
            }
        }
    }
}
