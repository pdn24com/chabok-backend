<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

final class CreateNodeRequest extends NodeInputRequest
{
    protected function creating(): bool
    {
        return true;
    }
}
