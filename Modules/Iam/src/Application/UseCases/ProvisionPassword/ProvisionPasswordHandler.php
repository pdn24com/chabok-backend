<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ProvisionPassword;

use Modules\Iam\Application\Contracts\CredentialWriterInterface;
use Modules\Iam\Domain\Policies\PasswordPolicy;

final readonly class ProvisionPasswordHandler
{
    public function __construct(private PasswordPolicy $passwordPolicy, private CredentialWriterInterface $credentialWriter) {}

    public function handle(ProvisionPasswordCommand $command): ProvisionPasswordResult
    {
        $userId = $command->userId;
        $password = $command->password;
        $this->passwordPolicy->assertValid($password);
        $this->credentialWriter->upsertPassword($userId, $password);

        return new ProvisionPasswordResult;
    }
}
