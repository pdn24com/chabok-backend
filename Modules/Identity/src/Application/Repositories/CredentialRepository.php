<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Repositories;

interface CredentialRepository
{
    public function findForUser(string $userId, bool $lock = false): ?\stdClass;

    public function upsert(array $attributes): void;
}
