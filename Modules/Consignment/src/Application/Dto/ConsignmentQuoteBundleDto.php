<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class ConsignmentQuoteBundleDto
{
    public function __construct(public string $quoteId, public int $quoteVersion, public string $hqId, public string $nodeId,
        public string $purpose, public string $inputFingerprint, public ?string $consignmentId, public ?int $expectedVersion,
        public string $providerCalculatedAt, public string $expiresAt, public array $options) {}
}
