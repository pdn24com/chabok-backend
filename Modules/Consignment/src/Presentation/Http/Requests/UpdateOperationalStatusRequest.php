<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

final class UpdateOperationalStatusRequest extends OperationalStatusRequest
{
    protected function updating(): bool
    {
        return true;
    }
}
