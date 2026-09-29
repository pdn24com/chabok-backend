<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmSales\Infrastructure\Persistence\Models\ContractRecord;

/**
 * One contract of a customer, with the archive documents attached to it. The files come from the
 * document archive's links, so the caller hands them in beside the record.
 *
 * @mixin ContractRecord
 */
final class ContractResource extends JsonResource
{
    /**
     * @param  list<array{document_id: string, title: string}>  $documents
     */
    public function __construct(ContractRecord $resource, private readonly array $documents = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'contract_id' => $this->contract_id,
            'customer_id' => $this->customer_id,
            'reference_no' => $this->reference_no,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'amount' => $this->amount,
            'commitments' => $this->commitments,
            'status' => $this->status,
            'opportunity_id' => $this->opportunity_id,
            'proforma_version_id' => $this->proforma_version_id,
            'documents' => $this->documents,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
