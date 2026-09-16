<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Cookie;

final class RefreshCookieFactory
{
    public function create(string $opaqueToken, ?DateTimeImmutable $expiresAt = null): Cookie
    {
        $expiresAt ??= new DateTimeImmutable('+' . (int) config('chabok.refresh_cookie.ttl_seconds') . ' seconds');
        return Cookie::create(name: (string) config('chabok.refresh_cookie.name'), value: $opaqueToken, expire: $expiresAt, path: (string) config('chabok.refresh_cookie.path'), domain: null, secure: true, httpOnly: true, raw: false, sameSite: Cookie::SAMESITE_LAX);
    }

    public function clear(): Cookie
    {
        return Cookie::create(name: (string) config('chabok.refresh_cookie.name'), value: '', expire: new DateTimeImmutable('1970-01-01T00:00:00+00:00'), path: (string) config('chabok.refresh_cookie.path'), domain: null, secure: true, httpOnly: true, raw: false, sameSite: Cookie::SAMESITE_LAX);
    }
}
