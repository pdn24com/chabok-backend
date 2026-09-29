<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language from Accept-Language, defaulting to Persian.
 *
 * Only languages the API actually ships are honoured; anything else falls back
 * to the default rather than leaving Laravel on its config locale.
 */
final class NegotiateLocale
{
    public const DEFAULT_LOCALE = 'fa';

    /** @var list<string> */
    public const SUPPORTED_LOCALES = ['fa', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve((string) $request->headers->get('Accept-Language', ''));
        App::setLocale($locale);
        $request->attributes->set('locale', $locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        $response->headers->set('Vary', trim($response->headers->get('Vary', '').', Accept-Language', ', '));

        return $response;
    }

    /** Highest-quality supported language in the header, else the default. */
    public static function resolve(string $acceptLanguage): string
    {
        $best = null;
        $bestQuality = -1.0;
        foreach (explode(',', $acceptLanguage) as $part) {
            $segments = explode(';', trim($part));
            $tag = strtolower(trim($segments[0]));
            if ($tag === '') {
                continue;
            }
            $quality = 1.0;
            foreach (array_slice($segments, 1) as $parameter) {
                if (str_starts_with(trim($parameter), 'q=')) {
                    $quality = (float) substr(trim($parameter), 2);
                }
            }
            $primary = explode('-', $tag)[0];
            $candidate = $primary === 'fa' || $tag === '*' ? self::DEFAULT_LOCALE : $primary;
            if (in_array($candidate, self::SUPPORTED_LOCALES, true) && $quality > $bestQuality) {
                $best = $candidate;
                $bestQuality = $quality;
            }
        }

        return $bestQuality > 0 && $best !== null ? $best : self::DEFAULT_LOCALE;
    }
}
