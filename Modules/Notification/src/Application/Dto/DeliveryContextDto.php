<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Dto;

use Modules\Notification\Domain\Enums\DeliveryChannel;

final readonly class DeliveryContextDto
{
    public function __construct(
        public DeliveryChannel $channel,
        public string $recipientFingerprint,
        public string $templateCode,
    ) {}
}
