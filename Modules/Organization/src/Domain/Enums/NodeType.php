<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Enums;

enum NodeType: string
{
    case BRANCH = 'BRANCH';
    case HUB = 'HUB';
    case GATEWAY = 'GATEWAY';
    case AGENT = 'AGENT';
}
