<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OperationalExceptionHistoryRecord extends Model
{
    protected $table = 'operational_exception_history';
    protected $primaryKey = 'exception_history_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
