<?php

declare(strict_types=1);

namespace Modules\CrmSales\Domain\Support;

/**
 * A fingerprint of what was issued, so a stored revision can be proved unchanged later. Only the content
 * an operator agreed to is hashed: two revisions that say the same thing carry the same hash on purpose.
 * The column is 64 characters wide, so the digest is stored bare and the algorithm is implied, not
 * repeated in the value.
 */
final class SalesDocumentContentHash
{
    /** @param array<string, mixed> $customerSnapshot */
    public static function of(
        string $documentNo,
        string $documentType,
        string $currency,
        int $total,
        ?string $terms,
        string $expiresAt,
        array $customerSnapshot,
    ): string {
        return hash('sha256', (string) json_encode([
            'document_no' => $documentNo,
            'document_type' => $documentType,
            'currency' => $currency,
            'total' => $total,
            'terms' => $terms,
            'expires_at' => $expiresAt,
            'customer_snapshot' => $customerSnapshot,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
