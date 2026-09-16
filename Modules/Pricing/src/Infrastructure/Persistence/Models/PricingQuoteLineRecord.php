<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingQuoteLineRecord extends Model
{
    protected $table = 'pricing_quote_lines';
    protected $primaryKey = 'quote_line_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = ['*'];
}
