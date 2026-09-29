<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Workbooks;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Modules\Pricing\Application\Contracts\MatrixWorkbookStorageInterface;
use Modules\Pricing\Application\Dto\WorkbookFileDto;
use Modules\Pricing\Domain\Exceptions\InvalidMatrixWorkbook;
use ZipArchive;

/** Bounded archive IO; never evaluates formulas or extracts archive paths. */
final class XlsxMatrixWorkbookStorage implements MatrixWorkbookStorageInterface
{
    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

    private const MAX_EXPANDED_BYTES = 20 * 1024 * 1024;

    private const MAX_ARCHIVE_ENTRIES = 1000;

    private const MAX_ROWS_WITH_HEADER = 501;

    private const MAX_COLUMNS_WITH_BOUNDARIES = 103;

    public function rows(string $encoded): array
    {
        if (strlen($encoded) > 4 * (int) ceil(self::MAX_FILE_BYTES / 3)) {
            $this->reject('pricing.workbook_file_too_large');
        }
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) > self::MAX_FILE_BYTES) {
            $this->reject('pricing.workbook_file_too_large');
        }
        $path = tempnam(sys_get_temp_dir(), 'pricing-xlsx-');
        try {
            file_put_contents($path, $bytes);
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                $this->reject('pricing.workbook_must_be_xlsx');
            }
            try {
                $total = 0;
                if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                    $this->reject('pricing.workbook_entry_too_large');
                }
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $total += $stat['size'];
                    if ($total > self::MAX_EXPANDED_BYTES || preg_match('~vbaProject|externalLinks|(^|/)\.\.(/|$)~i', $stat['name'])) {
                        $this->reject('pricing.workbook_structure_unsupported');
                    }
                    if (str_ends_with($stat['name'], '.rels')) {
                        $relationships = $this->xml((string) $zip->getFromIndex($i));
                        foreach ($relationships->query('//*[local-name()="Relationship"]') as $relationship) {
                            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0) {
                                $this->reject('pricing.workbook_external_links_not_allowed');
                            }
                        }
                    }
                }
                $strings = [];
                if (($shared = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                    $xml = $this->xml($shared);
                    foreach ($xml->query('//*[local-name()="si"]') as $si) {
                        $strings[] = $si->textContent;
                    }
                }
                $workbook = $this->xml((string) $zip->getFromName('xl/workbook.xml'));
                $sheet = $workbook->query('//*[local-name()="sheet"]')->item(0);
                $relationId = $sheet?->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                $rels = $this->xml((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
                $target = null;
                foreach ($rels->query('//*[local-name()="Relationship"]') as $rel) {
                    if ($rel->getAttribute('Id') === $relationId) {
                        $target = $rel->getAttribute('Target');
                    }
                }
                if (! $target || str_contains($target, '..')) {
                    $this->reject('pricing.workbook_first_sheet_unreadable');
                }
                $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                $xml = $this->xml((string) $zip->getFromName($sheetPath));
                if ($xml->query('//*[local-name()="f"]')->length) {
                    $this->reject('pricing.workbook_formulas_not_allowed');
                }
                $rows = [];
                foreach ($xml->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                    $values = [];
                    foreach ($row->childNodes as $cell) {
                        if (! $cell instanceof DOMElement || $cell->localName !== 'c') {
                            continue;
                        }
                        preg_match('/^([A-Z]+)[0-9]+$/', $cell->getAttribute('r'), $ref);
                        $column = 0;
                        foreach (str_split($ref[1] ?? '') as $letter) {
                            $column = $column * 26 + ord($letter) - 64;
                        }
                        if ($column < 1 || $column > self::MAX_COLUMNS_WITH_BOUNDARIES) {
                            $this->reject('pricing.workbook_max_zone_columns');
                        }
                        $type = $cell->getAttribute('t');
                        $raw = $xml->query('./*[local-name()="v"]', $cell)->item(0)?->textContent ?? '';
                        $values[$column - 1] = $type === 's' ? $strings[(int) $raw] ?? '' : ($type === 'inlineStr' ? $xml->query('./*[local-name()="is"]', $cell)->item(0)?->textContent ?? '' : $raw);
                    }
                    if (array_filter($values, static fn ($v) => trim($v) !== '')) {
                        $rows[] = $values;
                    }
                    if (count($rows) > self::MAX_ROWS_WITH_HEADER) {
                        $this->reject('pricing.workbook_max_ranges');
                    }
                }
            } finally {
                $zip->close();
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return $rows;
    }

    public function sample(array $titles): WorkbookFileDto
    {
        $rows = [
            ['از', 'تا', 'گام (خالی برای ثابت)', ...$titles],
            [0, 10, '', ...array_fill(0, count($titles), 100000)],
            [10, 50, 1, ...array_fill(0, count($titles), 10000)],
            [50, 100, 0.5, ...array_fill(0, count($titles), 15000)],
            [100, '', 1, ...array_fill(0, count($titles), 20000)],
        ];
        $notes = [
            ['راهنمای ماتریس تعرفه'],
            [
                'مبلغ‌ها ریال و حدود و گام مطابق مبنای انتخاب‌شده در تعرفه هستند.',
            ],
            [
                'ستون‌های زون به ترتیب عنوان‌های فایل با ماتریس انتخاب‌شده تطبیق دارند.',
            ],
            ['برای بازه ثابت گام را خالی بگذارید؛ مبلغ، نرخ کل بازه است.'],
            [
                'برای بازه خطی گام را وارد کنید؛ مبلغ هر زون افزایش به ازای هر پله است.',
            ],
            [
                'مبنای هر بازه خطی مبلغ انتهای بازه قبلی است؛ بخش پله یک پله حساب می‌شود.',
            ],
            ['پایان خالی فقط برای آخرین بازه خطی به معنی بی‌نهایت است.'],
            [
                'خانه خالی یعنی نرخ واردنشده؛ عبارت بدون پوشش تصمیم صریح است.',
            ],
            [
                'ردیف اول و ترتیب ستون‌ها را حفظ کنید. فرمول پشتیبانی نمی‌شود.',
            ],
            ['فایل را با فرمت xlsx ذخیره و در ورود از Excel انتخاب کنید.'],
            [
                'پیش‌نمایش همه بازه‌های همین ماتریس را جایگزین می‌کند؛ سپس پیش‌نویس را ذخیره کنید.',
            ],
        ];
        $path = tempnam(sys_get_temp_dir(), 'pricing-template-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="نمونه ماتریس" sheetId="1" r:id="rId1"/><sheet name="راهنما" sheetId="2" r:id="rId2"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.0###"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF17354B"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf numFmtId="3" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"><alignment horizontal="right" readingOrder="2"/></xf><xf fontId="1" fillId="2" borderId="0" numFmtId="0" applyFill="1" applyFont="1"><alignment horizontal="right" readingOrder="2"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"><alignment horizontal="right" readingOrder="2"/></xf></cellXfs></styleSheet>');
            foreach ([$rows, $notes] as $si => $data) {
                $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews><cols><col min="1" max="'.count($data[0]).'" width="'.($si === 1 ? 110 : 25).'" customWidth="1"/></cols><sheetData>';
                foreach ($data as $ri => $row) {
                    $xml .= '<row r="'.($ri + 1).'" ht="28" customHeight="1">';
                    foreach ($row as $ci => $value) {
                        $n = $ci + 1;
                        $col = '';
                        while ($n > 0) {
                            $n--;
                            $col = chr(65 + $n % 26).$col;
                            $n = intdiv($n, 26);
                        }
                        $ref = $col.($ri + 1);
                        $style = $ri === 0 ? 1 : (is_numeric($value) && floor((float) $value) !== (float) $value ? 2 : 0);
                        $xml .= is_numeric($value) && $value !== '' ? '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>' : '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1, 'UTF-8').'</t></is></c>';
                    }
                    $xml .= '</row>';
                }
                $zip->addFromString('xl/worksheets/sheet'.($si + 1).'.xml', $xml.'</sheetData></worksheet>');
            }
            $zip->close();

            return new WorkbookFileDto('tariff-matrix-sample.xlsx', base64_encode(file_get_contents($path)));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function xml(string $source): DOMXPath
    {
        if (! $source || stripos($source, '<!DOCTYPE') !== false || stripos($source, '<!ENTITY') !== false) {
            $this->reject('pricing.workbook_xml_structure_invalid');
        }
        $doc = new DOMDocument;
        if (! @$doc->loadXML($source, LIBXML_NONET)) {
            $this->reject('pricing.workbook_excel_structure_invalid');
        }

        return new DOMXPath($doc);
    }

    private function reject(string $messageKey): never
    {
        throw new InvalidMatrixWorkbook($messageKey);
    }
}
