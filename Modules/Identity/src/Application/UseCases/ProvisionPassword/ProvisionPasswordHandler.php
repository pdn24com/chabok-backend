<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ProvisionPassword;

use Modules\Identity\Application\Services\CredentialWriter;
use Modules\Identity\Domain\PasswordPolicy;

final readonly class ProvisionPasswordHandler
{
    public function __construct(private PasswordPolicy $passwordPolicy, private CredentialWriter $credentialWriter)
    {
    }

    public function handle(ProvisionPasswordCommand $command): ProvisionPasswordResult
    {
        $this->execute($command->userId, $command->password);
        return new ProvisionPasswordResult();
    }

    private function execute(string $userId, string $password): void
    {
        $this->passwordPolicy->assertValid($password);
        $this->credentialWriter->upsertPassword($userId, $password);
    }
}
