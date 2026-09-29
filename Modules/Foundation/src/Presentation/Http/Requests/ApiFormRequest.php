<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

abstract class ApiFormRequest extends FormRequest
{
    public function validated($key = null, $default = null)
    {
        // JSON numbers and decimal strings share the application's string ID contracts.
        $values = $this->normalizeIdentifiers(parent::validated());

        return $key === null ? $values : Arr::get($values, $key, $default);
    }

    private function normalizeIdentifiers(array $values, bool $identifierList = false): array
    {
        foreach ($values as $key => $value) {
            $name = (string) $key;
            $identifier = $identifierList || $name === 'id' || str_ends_with($name, '_id');
            if (is_array($value)) {
                $values[$key] = $this->normalizeIdentifiers($value, $identifier || str_ends_with($name, '_ids'));
            } elseif ($identifier && is_int($value)) {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }
}
