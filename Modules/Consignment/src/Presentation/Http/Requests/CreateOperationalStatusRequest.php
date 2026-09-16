<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

final class CreateOperationalStatusRequest extends OperationalStatusRequest
{
    protected function updating(): bool
    {
        return false;
    }
}
