<?php

declare(strict_types=1);

namespace Modules\User\Domain;

use Symfony\Component\String\UnicodeString;

final class IdentifierNormalizer
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

    /**
     * @return array{username: ?string, mobile: ?string, email: ?string}
     */
    public function all(?string $username, ?string $mobile, ?string $email): array
    {
        return [
            'username' => $this->username($username),
            'mobile' => $this->mobile($mobile),
            'email' => $this->email($email),
        ];
    }

    private function unicode(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (new UnicodeString($value))->trim()->lower()->normalize()->toString();
    }
}
