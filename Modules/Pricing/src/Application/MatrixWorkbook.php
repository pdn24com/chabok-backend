<?php

declare(strict_types=1);

namespace Modules\Pricing\Application;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Pricing\Domain\FreightMatrices;
/** Bounded XLSX draft preview. Never evaluates formulas or extracts archive paths. */

final class MatrixWorkbook
{
    public function __construct(
        private FreightMatrices $matrices,
        private Contracts\MatrixWorkbookStorage $workbooks,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function preview(string $encoded, array $matrix): array
    {
        $rows = $this->workbooks->rows($encoded);
        $header = array_shift($rows);
        if (!$header || count($rows) === 0 || !str_starts_with(trim($header[0] ?? ''), 'از') || !str_starts_with(trim($header[1] ?? ''), 'تا')) {
            $this->reject('شیت اول باید ساختار فایل نمونه را داشته باشد: از، تا، گام و ستون‌های زون.');
        }
        // Also accept the previously distributed two-boundary sample.
        $offset = str_starts_with(trim($header[2] ?? ''), 'گام') ? 3 : 2;
        if (count($header) - $offset !== count($matrix['zone_ids'])) {
            $this->reject('تعداد ستون‌های زون فایل با ماتریس انتخاب‌شده برابر نیست. نمونهٔ همین ماتریس را دانلود کنید.');
        }
        $bands = [];
        $linear = [];
        foreach ($rows as $index => $row) {
            $from = $this->number($row[0] ?? '', $index);
            $end = trim($row[1] ?? '');
            $to = in_array($end, ['', '∞', 'بی نهایت'], true) ? null : $this->number($end, $index);
            $step = $offset === 3 && trim($row[2] ?? '') !== '' ? $this->number($row[2], $index) : null;
            $cells = [];
            foreach ($matrix['zone_ids'] as $ci => $zone) {
                $raw = trim($row[$offset + $ci] ?? '');
                $state = $raw === '' ? 'EMPTY' : (in_array($raw, ['بدون پوشش', 'UNCOVERED'], true) ? 'UNCOVERED' : 'RATE');
                $amount = $state === 'RATE' ? $this->number($raw, $index) : null;
                if ($amount !== null && ($amount <= 0 || floor($amount) !== $amount || $amount > 9007199254740991)) {
                    $this->reject('نرخ ردیف ' . ($index + 2) . ' باید عدد صحیح مثبت باشد.');
                }
                $cells[] = [
                    'id' => $this->identifiers->uuid(),
                    'zone_id' => $zone,
                    'state' => $state,
                    'amount' => $amount === null ? null : (int) $amount,
                ];
            }
            $band = ['id' => $this->identifiers->uuid(), 'from' => $from, 'to' => $to, 'cells' => $cells];
            if ($step !== null) {
                $linear[] = [...$band, 'step_kg' => $step];
            } else {
                if ($linear || $to === null) {
                    $this->reject('بازهٔ ثابت باید پایان داشته باشد و قبل از بازه‌های خطی قرار بگیرد.');
                }
                $bands[] = $band;
            }
        }
        $next = [...$matrix, 'bands' => $bands, 'linear_bands' => $linear, 'linear_tail' => null];
        $errors = $this->matrices->validate([$next], array_values(array_unique([...$matrix['zone_ids'], ...!empty($matrix['origin_zone_id']) ? [$matrix['origin_zone_id']] : []])), empty($matrix['origin_zone_id']) ? 'HIGHER_ZONE_RANK' : 'DIRECTIONAL');
        if ($errors) {
            $this->reject('بازه‌ها یا گام‌های فایل معتبر نیستند؛ شروع هر بازهٔ خطی باید پایان قبلی باشد.');
        }
        return [
            'matrix' => $next,
            'band_count' => count($bands),
            'linear_band_count' => count($linear),
            'zone_count' => count($matrix['zone_ids']),
        ];
    }

    public function sample(array $titles): array
    {
        return $this->workbooks->sample($titles);
    }

    private function number(string $raw, int $row): float
    {
        $value = strtr(trim($raw), array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
        $value = str_replace(['٬', ',', '٫'], ['', '', '.'], $value);
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $value) || !is_finite((float) $value)) {
            $this->reject('عدد نامعتبر در ردیف ' . ($row + 2));
        }
        return (float) $value;
    }

    private function reject(string $message): never
    {
        throw new ApiException(ApiErrorCode::ValidationError, 422, $message);
    }
}
