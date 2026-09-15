<?php
declare(strict_types=1);
namespace Modules\ServiceCatalog\Application;
/** Resolves an operational anchor only against the policy frozen at issuance. */
final class FrozenCommitmentCompletion {
 public function pickupCompleted(array $snapshot, string $completedAt): ?array {
  if(empty($snapshot['policy']) || empty($snapshot['delivery']['awaiting_operation'])) return null;
  return (new CommitmentClock())->resolve($snapshot['effective_delivery_policy'],$snapshot['windows_snapshot']??[],['pickup_completed_at'=>$completedAt,'acceptance_at'=>$snapshot['accepted_at']??$completedAt,'delivery_window_code'=>$snapshot['requested_delivery_window_code']??null],$snapshot['timezone'],(bool)$snapshot['policy']['include_holidays'],true,'DELIVERY');
 }
}
