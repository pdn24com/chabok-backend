<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Services;

use Modules\Iam\Application\Contracts\IdentifierNormalizerInterface;
use Modules\Iam\Domain\ValueObjects\NormalizedIdentifiers;
use Symfony\Component\String\UnicodeString;

final class IdentifierNormalizer implements IdentifierNormalizerInterface
{
    public function username(?string $value): ?string
    {
        return $this->unicode($value);
    }

    public function email(?string $value): ?string
    {
        return $this->unicode($value);
    }

    public function mobile(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $normalized = preg_replace('/[^\d+]/u', '', trim($value)) ?? '';
        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }
        $normalized = preg_replace('/(?!^)\+/', '', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    public function identifiers(?string $username, ?string $mobile, ?string $email): NormalizedIdentifiers
    {
        return new NormalizedIdentifiers($this->username($username), $this->mobile($mobile), $this->email($email));
    }

    private function unicode(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (new UnicodeString($value))
            ->trim()
            ->lower()
            ->normalize()
            ->toString();
    }
}
