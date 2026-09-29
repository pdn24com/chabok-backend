<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifest;

use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;

final readonly class GetManifestHandler
{
    public function __construct(
        private ManifestReaderInterface $manifestReader,
        private ManifestAccessGuardInterface $manifestAccessGuard,
    ) {}

    public function handle(GetManifestCommand $command): ManifestDetailDto
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;

        return $this->manifestReader->detail($actor, $nodeId, $id, $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view'));
    }
}
