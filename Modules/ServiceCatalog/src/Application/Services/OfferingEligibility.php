<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;

final readonly class OfferingEligibility
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\ServiceCatalog\Domain\OfferingConditions $offeringConditions,
        private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function evaluate(array $row, array $context): array
    {
        $reasons = [];
        $missing = [];
        $coverage = array_map(fn($row) => $this->catalogReader->decode((array) $row), $this->catalog->coverageReferences((string) $row['service_offering_version_id']));
        foreach (['ORIGIN' => 'sender', 'DESTINATION' => 'receiver'] as $direction => $party) {
            $references = array_values(array_filter($coverage, fn($reference) => in_array($reference['direction'], [$direction, 'BOTH'], true)));
            if ($references !== [] && !array_filter($references, fn($reference) => $this->offeringConditions->coverageMatches($reference, (array) ($context[$party] ?? [])))) {
                $reasons[] = 'SERVICE_COVERAGE_UNSUPPORTED';
            }
        }
        foreach (array_map(fn($row) => $this->catalogReader->decode((array) $row), $this->catalog->eligibilityRules((string) $row['service_offering_version_id'])) as $rule) {
            $actual = \Modules\ServiceCatalog\Domain\OfferingFacts::value($context, (string) $rule['fact_key']);
            $expected = $rule['expected_value'];
            if ($actual === null) {
                $missing[] = $rule['fact_key'];
                continue;
            }
            $passes = match ($rule['operator']) {
                'EQ' => $actual == $expected,
                'NEQ' => $actual != $expected,
                'IN' => in_array($actual, (array) $expected, true),
                'NOT_IN' => !in_array($actual, (array) $expected, true),
                'MIN' => (float) $actual >= (float) $expected,
                'MAX' => (float) $actual <= (float) $expected,
                'BETWEEN' => (float) $actual >= (float) ($expected[0] ?? 0) && (float) $actual <= (float) ($expected[1] ?? 0),
                'EXISTS' => $actual !== null,
                'NOT_EXISTS' => $actual === null,
                default => false,
            };
            if (!$passes) {
                $reasons[] = $rule['reason_code'];
            }
        }
        $selected = array_map(fn($id) => $this->currentCatalog->resolve('options', (string) $id, $row['hq_id'])['service_option_id'], array_values((array) ($context['selected_option_version_ids'] ?? [])));
        $optionRules = array_map(fn($row) => $this->catalogReader->decode((array) $row), $this->catalog->optionRules((string) $row['service_offering_version_id']));
        foreach ($optionRules as &$rule) {
            $rule['service_option_version_id'] = $this->catalog->optionIdentity($rule['service_option_version_id']);
        }
        unset($rule);
        $knownOptionVersions = array_column($optionRules, 'service_option_version_id');
        if (array_diff($selected, $knownOptionVersions) !== []) {
            $reasons[] = 'SERVICE_OPTION_NOT_ALLOWED';
        }
        foreach ($optionRules as $rule) {
            $has = in_array($rule['service_option_version_id'], $selected, true);
            if ($rule['compatibility'] === 'REQUIRED' && !$has) {
                $reasons[] = 'SERVICE_OPTION_REQUIRED';
            }
            if ($rule['compatibility'] === 'FORBIDDEN' && $has) {
                $reasons[] = 'SERVICE_OPTION_FORBIDDEN';
            }
            if ($rule['compatibility'] === 'CONDITIONAL' && $has && !$this->offeringConditions->conditionPasses((array) $rule['condition'], $context)) {
                $reasons[] = 'SERVICE_OPTION_CONDITION_NOT_MET';
            }
        }
        $outcome = $reasons !== [] ? 'INELIGIBLE' : ($missing !== [] ? 'UNKNOWN' : 'ELIGIBLE');
        return [
            'outcome' => $outcome,
            'reason_codes' => array_values(array_unique($reasons)),
            'missing_facts' => array_values(array_unique($missing)),
            'evidence' => ['evaluated_at' => CarbonImmutable::instance($this->clock->now())->utc()->toISOString()],
        ];
    }
}
