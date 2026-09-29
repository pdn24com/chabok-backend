<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

final class UpdateNodeRequest extends NodeInputRequest
{
    protected function creating(): bool
    {
        return false;
    }
}
