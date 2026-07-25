<?php

declare(strict_types=1);

namespace Modules\Foundation\Application;

final class SensitiveDataRedactor
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'current_password',
        'new_password',
        'temporary_password',
        'code',
        'otp',
        'token',
        'access_token',
        'refresh_token',
        'verification_token',
        'invitation_token',
        'authorization',
        'secret',
        'hash',
    ];

    /**
     * @param array<string|int, mixed> $value
     * @return array<string|int, mixed>
     */
    public static function context(array $value): array
    {
        $redacted = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }

            $redacted[$key] = is_array($item) ? self::context($item) : $item;
        }

        return $redacted;
    }

    public static function message(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer '.self::REDACTED, $message) ?? $message;

        return preg_replace(
            '/((?:password|otp|token|secret|authorization)\s*[=:]\s*)[^\s,;]+/i',
            '$1'.self::REDACTED,
            $message,
        ) ?? $message;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === $sensitive || str_ends_with($normalized, '_'.$sensitive)) {
                return true;
            }
        }

        return false;
    }
}
