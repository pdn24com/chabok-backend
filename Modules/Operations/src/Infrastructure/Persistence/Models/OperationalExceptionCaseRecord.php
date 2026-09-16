<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OperationalExceptionCaseRecord extends Model
{
    protected $table = 'operational_exception_cases';
    protected $primaryKey = 'exception_case_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
