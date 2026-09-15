<?php
declare(strict_types=1);
namespace Modules\ServiceCatalog\Application\Contracts;
interface CommitmentZoneResolver {
 public function groups(string $hqId): array;
 /** Tenant-scoped current published group and its zone codes. */
 public function group(string $hqId, string $groupId, bool $locking = false): array;
 /** Returns current group/version and matched zone, or null zone for the default rule. */
 public function destination(string $hqId, string $groupId, array $address): array;
}
