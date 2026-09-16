<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

final readonly class ConsignmentQuoteProjection
{
    public function publicBundle(array $bundle): array
    {
        return [
            'quote_id' => $bundle['quote_id'],
            'quote_version' => $bundle['quote_version'],
            'purpose' => $bundle['purpose'],
            'expires_at' => $bundle['expires_at'],
            'options' => array_map(static function (array $option): array {
                unset($option['_resolved_input_fingerprint']);
                return $option;
            }, $bundle['options']),
        ];
    }
}
