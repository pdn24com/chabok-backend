<?php

declare(strict_types=1);

namespace Modules\Identity\Domain;

final class OpaqueToken
{
    public static function generate(int $bytes = 48): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
