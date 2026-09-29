<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class OperationalExceptionCaseRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'operational_exception_cases';

    protected $guarded = ['*'];

    public function history(): HasMany
    {
        return $this->hasMany(OperationalExceptionHistoryRecord::class, 'exception_case_id', 'id')->whereHas('actor')->orderBy('created_at');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'submitted_by', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'reviewed_by', 'id');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'submission_sequence' => 'integer'];
    }
}
