<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Http\Request;
use Modules\Foundation\Infrastructure\Adapters\RandomTokenGenerator;
use Modules\Pricing\Application\Contracts\MatrixWorkbookStorageInterface;
use Modules\Pricing\Application\Dto\MatrixDefinitionDto;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewDto;
use Modules\Pricing\Application\Services\MatrixWorkbookPreviewService;
use Modules\Pricing\Domain\Exceptions\InvalidMatrixWorkbook;
use Modules\Pricing\Domain\Validators\FreightMatrixValidator;
use Modules\Pricing\Infrastructure\Workbooks\XlsxMatrixWorkbookStorage;
use Modules\Pricing\Presentation\Http\Resources\MatrixWorkbookPreviewResource;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class MatrixWorkbookTest extends TestCase
{
    public function test_fractional_rial_near_the_client_integer_limit_is_rejected(): void
    {
        $storage = $this->createStub(MatrixWorkbookStorageInterface::class);
        $storage->method('rows')->willReturn([
            ['از', 'تا', 'زون'],
            ['0', '1', '9007199254740990.1'],
        ]);
        $workbooks = new MatrixWorkbookPreviewService(new FreightMatrixValidator, $storage, new RandomTokenGenerator);

        $this->expectException(InvalidMatrixWorkbook::class);
        $workbooks->preview(new MatrixWorkbookPreviewDto('encoded', new MatrixDefinitionDto('103573160', null, null, null, ['88335165'])));
    }

    public function test_localized_integer_rials_are_parsed_exactly(): void
    {
        $storage = $this->createStub(MatrixWorkbookStorageInterface::class);
        $storage->method('rows')->willReturn([
            ['از', 'تا', 'زون'],
            ['۰', '۱', '۹٬۰۰۷٬۱۹۹٬۲۵۴٬۷۴۰٬۹۹۱'],
        ]);
        $workbooks = new MatrixWorkbookPreviewService(new FreightMatrixValidator, $storage, new RandomTokenGenerator);
        $preview = $workbooks->preview(new MatrixWorkbookPreviewDto('encoded', new MatrixDefinitionDto('103573160', null, null, null, ['88335165'])));

        self::assertSame(9007199254740991, $preview->bands[0]->cells[0]->amount);
    }

    public function test_generated_workbook_is_rtl_and_imports_fixed_and_linear_rows(): void
    {
        $workbooks = new MatrixWorkbookPreviewService(new FreightMatrixValidator, new XlsxMatrixWorkbookStorage, new RandomTokenGenerator);
        $sample = $workbooks->sample(['الف', 'ب']);
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        try {
            file_put_contents($path, base64_decode($sample->contentBase64));
            $zip = new ZipArchive;
            $zip->open($path);
            foreach ([1, 2] as $sheet) {
                $this->assertStringContainsString('rightToLeft="1"', $zip->getFromName("xl/worksheets/sheet{$sheet}.xml"));
            }
            $zip->close();
            $matrix = new MatrixDefinitionDto('103573160', null, null, null, ['212432914', '65158786']);
            $result = (new MatrixWorkbookPreviewResource($workbooks->preview(new MatrixWorkbookPreviewDto($sample->contentBase64, $matrix))))->toArray(new Request);
            $this->assertSame(1, $result['band_count']);
            $this->assertSame(3, $result['linear_band_count']);
            $this->assertSame(100000, $result['matrix']['bands'][0]['cells'][0]['amount']);
            $this->assertSame(0.5, $result['matrix']['linear_bands'][1]['step_kg']);
            // The actual committed sample must be readable too, not just our generated format.
            $static = dirname(__DIR__, 3).'/frontend/public/downloads/tariff-matrix-sample.xlsx';
            if (is_file($static)) {
                $this->assertSame(3, count($workbooks->preview(new MatrixWorkbookPreviewDto(base64_encode(file_get_contents($static)), $matrix))->linearBands));
            }
            $zip->open($path);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('<v>100000</v>', '<f>1+1</f><v>2</v>', $xml));
            $zip->close();
            $this->expectException(InvalidMatrixWorkbook::class);
            $workbooks->preview(new MatrixWorkbookPreviewDto(base64_encode(file_get_contents($path)), $matrix));
        } finally {
            unlink($path);
        }
    }
}
