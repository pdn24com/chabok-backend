<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

use Modules\Foundation\Application\Dto\OutboxEventDto;
use Modules\Foundation\Application\Dto\PublicationReceiptDto;

/**
 * Port owned by Foundation, implemented by the Notification module.
 *
 * @see Modules/Notification/src/Infrastructure/Publishing/NotificationOutboxEventPublisher.php (bound in NotificationServiceProvider)
 */
interface OutboxEventPublisherInterface
{
    public function publish(OutboxEventDto $event): PublicationReceiptDto;
}
