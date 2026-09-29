<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum EligibilityOperator: string
{
    case Equal = 'EQ';
    case NotEqual = 'NEQ';
    case In = 'IN';
    case NotIn = 'NOT_IN';
    case Minimum = 'MIN';
    case Maximum = 'MAX';
    case Between = 'BETWEEN';
    case Exists = 'EXISTS';
    case NotExists = 'NOT_EXISTS';
}
