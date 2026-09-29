<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

/**
 * The owner of a customer record, as every customer payload names them.
 *
 * @mixin UserRecord
 */
final class CustomerAssigneeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['user_id' => $this->user_id, 'display_name' => $this->display_name];
    }
}
