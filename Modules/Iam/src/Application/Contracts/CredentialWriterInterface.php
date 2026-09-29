<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

interface CredentialWriterInterface
{
    public function upsertPassword(string $userId, string $password): void;
}
