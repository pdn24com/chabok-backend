<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class StrictPayload
{
    /**
     * @param list<string> $allowed
     */

    public static function assertOnly(Request $request, array $allowed): void
    {
        $unknown = array_values(array_diff(array_keys($request->all()), $allowed));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, ['This field is not allowed.']));
        }
    }
    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $allowed
     */

    public static function assertItemsOnly(array $items, array $allowed, string $field): void
    {
        foreach ($items as $index => $item) {
            $unknown = array_values(array_diff(array_keys($item), $allowed));
            if ($unknown !== []) {
                throw ValidationException::withMessages(["{$field}.{$index}" => ['Unknown fields: ' . implode(', ', $unknown) . '.']]);
            }
        }
    }
}
