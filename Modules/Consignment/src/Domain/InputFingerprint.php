<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain;

final class InputFingerprint
{
    /** @param array<string, mixed> $input */
    public static function of(array $input): string
    {
        return hash('sha256', json_encode(self::sort($input), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::sort(...), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sort($item);
        }

        return $value;
    }
}
