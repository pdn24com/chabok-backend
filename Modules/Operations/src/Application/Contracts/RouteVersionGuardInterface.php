<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Application\Dto\RouteVersionDto;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

interface RouteVersionGuardInterface
{
    public function validateContent(string $hq, RouteVersionDto $content): void;

    public function expected(RouteDefinitionVersionRecord $row, int $expected): void;

    public function validatedChanges(string $hq, RouteDefinitionVersionRecord $row, string $user): array;

    public function simpleChanges(RouteDefinitionVersionRecord $row, string $from, string $to, array $extra = []): array;

    public function archiveChanges(RouteDefinitionVersionRecord $row): array;

    public function publishedChanges(string $hq, RouteDefinitionVersionRecord $row, string $user): array;
}
