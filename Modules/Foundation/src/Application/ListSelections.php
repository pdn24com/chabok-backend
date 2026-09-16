<?php

declare(strict_types=1);

namespace Modules\Foundation\Application;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ListSelections
{
    /** Normalize the documented CSV query format; a scalar is a one-item selection. */
    public static function normalize(Request $request, array $fields): void
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (! $request->query->has($field)) continue;
            $value = $request->query($field);
            if ($value === null || $value === '') {
                $normalized[$field] = [];
                continue;
            }
            if (! is_string($value) || strlen($value) > 1850) {
                throw ValidationException::withMessages([$field => ['Invalid selection.']]);
            }
            $values = explode(',', $value);
            if (count($values) > 50 || in_array('', $values, true)) {
                throw ValidationException::withMessages([$field => ['Select up to 50 values.']]);
            }
            $normalized[$field] = array_values(array_unique($values));
        }
        $request->merge($normalized);
    }
}
