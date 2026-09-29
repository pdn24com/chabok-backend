<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Domain\Exceptions\InvalidMatrixWorkbook;
use Modules\Pricing\Infrastructure\Workbooks\XlsxMatrixWorkbookStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class MatrixWorkbookSecurityTest extends TestCase
{
    public static function hostileParts(): iterable
    {
        yield 'single quoted external link' => ['xl/_rels/sheet.xml.rels', "<Relationships><Relationship Id='r' TargetMode='External' Target='https://example.invalid/' /></Relationships>"];
        yield 'spaced external link' => ['xl/_rels/sheet.xml.rels', '<Relationships><Relationship Id="r" TargetMode = "External" Target="https://example.invalid/" /></Relationships>'];
        yield 'entity declaration' => ['xl/sharedStrings.xml', '<!DOCTYPE x [<!ENTITY file SYSTEM "file:///etc/passwd">]><x>&file;</x>'];
        yield 'path traversal' => ['../escape.xml', '<x/>'];
        yield 'macro' => ['xl/vbaProject.bin', 'macro'];
        yield 'formula' => ['xl/worksheets/sheet1.xml', '<worksheet><sheetData><row><c r="A1"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>'];
        yield 'too many columns' => ['xl/worksheets/sheet1.xml', '<worksheet><sheetData><row><c r="CZ1"><v>2</v></c></row></sheetData></worksheet>'];
    }

    #[DataProvider('hostileParts')]
    public function test_unsafe_workbooks_are_rejected(string $entry, string $content): void
    {
        $storage = new XlsxMatrixWorkbookStorage;
        $sample = $storage->sample(['Zone']);
        $path = tempnam(sys_get_temp_dir(), 'workbook-security-');
        try {
            file_put_contents($path, base64_decode($sample->contentBase64));
            $zip = new ZipArchive;
            self::assertTrue($zip->open($path));
            $zip->addFromString($entry, $content);
            $zip->close();
            $this->expectException(InvalidMatrixWorkbook::class);
            $storage->rows(base64_encode(file_get_contents($path)));
        } finally {
            unlink($path);
        }
    }

    public function test_encoded_file_size_is_bounded_before_decoding(): void
    {
        $this->expectException(InvalidMatrixWorkbook::class);
        (new XlsxMatrixWorkbookStorage)->rows(str_repeat('A', 7 * 1024 * 1024));
    }
}
