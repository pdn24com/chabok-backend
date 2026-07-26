<?php

declare(strict_types=1);

namespace Modules\Outbox\Application;

final class OutboxEventSchemaRegistry
{
    /** @param array<string, mixed> $payload */
    public function assertValid(string $eventType, int $version, array $payload): void
    {
        if ($version !== 1) {
            throw new \InvalidArgumentException('Unsupported outbox event version.');
        }
        $schema = $this->schemas()[$eventType] ?? null;
        if ($schema === null) {
            throw new \InvalidArgumentException("Unregistered outbox event schema: {$eventType}.");
        }
        $required = array_keys($schema);
        $actual = array_keys($payload);
        sort($required);
        sort($actual);
        if ($required !== $actual) {
            throw new \InvalidArgumentException("Outbox payload shape does not match {$eventType} v{$version}.");
        }
        foreach ($schema as $field => $type) {
            $valid = match ($type) {
                'string' => is_string($payload[$field]) && $payload[$field] !== '',
                'status' => is_string($payload[$field])
                    && in_array($payload[$field], ['INVITED', 'ACTIVE', 'SUSPENDED', 'DEACTIVATED'], true),
                'creation_mode' => is_string($payload[$field])
                    && in_array($payload[$field], ['DIRECT_ACTIVE', 'SMS_INVITATION', 'EMAIL_INVITATION'], true),
                'channel' => is_string($payload[$field])
                    && in_array($payload[$field], ['SMS', 'EMAIL'], true),
                'purpose' => is_string($payload[$field])
                    && in_array($payload[$field], ['ACTIVATION', 'PASSWORD_RESET'], true),
                default => false,
            };
            if (! $valid) {
                throw new \InvalidArgumentException("Invalid {$field} in {$eventType} v{$version}.");
            }
        }
    }

    /** @return array<string, array<string, string>> */
    private function schemas(): array
    {
        return [
            'iam.user.created' => [
                'user_id' => 'string',
                'status' => 'status',
                'creation_mode' => 'creation_mode',
            ],
            'iam.user.status_changed' => [
                'user_id' => 'string',
                'from' => 'status',
                'to' => 'status',
            ],
            'identity.otp.delivery.requested' => [
                'challenge_id' => 'string',
                'purpose' => 'purpose',
                'delivery_ciphertext' => 'string',
                'destination_fingerprint' => 'string',
            ],
            'identity.invitation.delivery.requested' => [
                'invitation_id' => 'string',
                'channel' => 'channel',
                'recipient_fingerprint' => 'string',
                'delivery_ciphertext' => 'string',
            ],
            'iam.role.updated' => ['role_id' => 'string'],
            'iam.role.cloned' => ['role_id' => 'string', 'source_role_id' => 'string'],
            'iam.role.permissions_replaced' => ['role_id' => 'string'],
            'iam.user.assignments_changed' => ['user_id' => 'string'],
            'consignment.created' => [
                'consignment_id' => 'string',
                'version' => 'string',
                'status' => 'string',
                'pricing_version_id' => 'string',
            ],
            'consignment.updated' => [
                'consignment_id' => 'string',
                'version' => 'string',
                'status' => 'string',
                'pricing_version_id' => 'string',
            ],
            'consignment.pricing.accepted' => [
                'consignment_id' => 'string',
                'version' => 'string',
                'pricing_version_id' => 'string',
            ],
            'manifest.created' => [
                'manifest_id' => 'string',
                'manifest_status' => 'string',
                'version' => 'string',
            ],
            'manifest.closed' => [
                'manifest_id' => 'string',
                'manifest_status' => 'string',
                'succeeded_count' => 'string',
            ],
        ];
    }
}
