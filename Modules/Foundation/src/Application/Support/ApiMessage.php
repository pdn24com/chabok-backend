<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;

/**
 * Resolves the api.* keys that domain exceptions carry instead of prose.
 *
 * Domain code never calls this: exceptions store a key and the Presentation
 * layer resolves it against the negotiated locale. A missing translator (unit
 * tests, console bootstrapping) degrades to the key rather than throwing.
 */
final class ApiMessage
{
    public const FALLBACK_LOCALE = 'en';

    /** Keys look like "<module>.<reason>"; real prose never does. */
    private const KEY_PATTERN = '/^[a-z]+\.[a-z0-9_]+$/';

    /** @param array<string, scalar> $params */
    public static function inEnglish(string $key, array $params = []): string
    {
        return self::translate($key, $params, self::FALLBACK_LOCALE);
    }

    /** @param array<string, scalar> $params */
    public static function translate(string $key, array $params = [], ?string $locale = null): string
    {
        $translator = self::translator();
        if ($translator === null) {
            return $key;
        }
        $message = $translator->get('api.'.$key, $params, $locale);

        // The translator echoes the key back when it is missing; surface the bare
        // key instead of the "api." prefixed miss, so the gap is obvious.
        return is_string($message) && $message !== 'api.'.$key ? $message : $key;
    }

    /**
     * Resolves values that are translation keys and leaves literal prose alone.
     *
     * @param  array<string, list<string>>  $fieldErrors
     * @return array<string, list<string>>
     */
    public static function translateFieldErrors(array $fieldErrors, ?string $locale = null): array
    {
        return array_map(
            static fn (array $messages): array => array_map(
                static fn (string $message): string => preg_match(self::KEY_PATTERN, $message) === 1
                    ? self::translate($message, [], $locale)
                    : $message,
                $messages,
            ),
            $fieldErrors,
        );
    }

    private static function translator(): ?Translator
    {
        if (! Container::getInstance()->bound('translator')) {
            return null;
        }
        $translator = Container::getInstance()->make('translator');

        return $translator instanceof Translator ? $translator : null;
    }
}
