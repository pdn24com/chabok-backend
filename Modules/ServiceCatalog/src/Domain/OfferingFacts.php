<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain;

/** Reads the existing dotted fact paths from a JSON eligibility context. */

final class OfferingFacts
{
    public static function value(mixed $context, string|array $path): mixed
    {
        $segments = is_array($path) ? $path : explode('.', $path);
        foreach ($segments as $index => $segment) {
            unset($segments[$index]);
            if ($segment === '*') {
                if (!is_iterable($context)) {
                    return null;
                }
                $values = [];
                foreach ($context as $item) {
                    $values[] = self::value($item, $segments);
                }
                if (!in_array('*', $segments, true)) {
                    return $values;
                }
                return array_merge([], ...array_values(array_filter($values, 'is_array')));
            }
            $segment = match ($segment) {
                '\*' => '*',
                '\{first}' => '{first}',
                '\{last}' => '{last}',
                '{first}' => array_key_first((array) $context),
                '{last}' => array_key_last((array) $context),
                default => $segment,
            };
            if (is_array($context) && array_key_exists($segment, $context)) {
                $context = $context[$segment];
            } elseif (is_object($context) && isset($context->{$segment})) {
                $context = $context->{$segment};
            } else {
                return null;
            }
        }
        return $context;
    }
}
