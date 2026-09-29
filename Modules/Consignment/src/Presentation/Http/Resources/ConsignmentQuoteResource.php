<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;
use Modules\Consignment\Application\Serialization\ConsignmentQuoteDocument;

/** @mixin ConsignmentQuoteBundleDto */
final class ConsignmentQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $options = [];
        foreach ($this->options as $option) {
            $document = ConsignmentQuoteDocument::option($option);
            unset($document['_resolved_input_fingerprint']);
            $options[] = $document;
        }

        return [
            'quote_id' => $this->quoteId, 'quote_version' => $this->quoteVersion,
            'purpose' => $this->purpose, 'expires_at' => $this->expiresAt, 'options' => $options,
        ];
    }
}
