<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ApproveManifestException;

final readonly class ApproveManifestExceptionResult
{
    public function __construct(public array $data)
    {
    }
}
