<?php
declare(strict_types=1);
namespace Tests\Unit;

use Modules\Pricing\Application\MatrixWorkbook;
use Modules\Pricing\Domain\FreightMatrices;
use Modules\Foundation\Domain\ApiException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class MatrixWorkbookTest extends TestCase
{
    public function test_generated_workbook_is_rtl_and_imports_fixed_and_linear_rows(): void
    {
        $workbooks=new MatrixWorkbook(new FreightMatrices);
        $sample=$workbooks->sample(['الف','ب']);
        $path=tempnam(sys_get_temp_dir(),'xlsx-test-');
        try {
            file_put_contents($path,base64_decode($sample['content_base64']));
            $zip=new ZipArchive; $zip->open($path);
            foreach ([1,2] as $sheet) $this->assertStringContainsString('rightToLeft="1"',$zip->getFromName("xl/worksheets/sheet{$sheet}.xml"));
            $zip->close();
            $matrix=['id'=>'m','service_offering_version_id'=>null,'service_option_version_id'=>null,'origin_zone_id'=>null,'zone_ids'=>['a','b']];
            $result=$workbooks->preview($sample['content_base64'],$matrix);
            $this->assertSame(1,$result['band_count']); $this->assertSame(3,$result['linear_band_count']);
            $this->assertSame(100000,$result['matrix']['bands'][0]['cells'][0]['amount']);
            $this->assertSame(0.5,$result['matrix']['linear_bands'][1]['step_kg']);
            // The actual committed sample must be readable too, not just our generated format.
            $static=dirname(__DIR__,3).'/frontend/public/downloads/tariff-matrix-sample.xlsx';
            if (is_file($static)) $this->assertSame(3,$workbooks->preview(base64_encode(file_get_contents($static)),$matrix)['linear_band_count']);
            $zip->open($path); $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->addFromString('xl/worksheets/sheet1.xml',str_replace('<v>100000</v>','<f>1+1</f><v>2</v>',$xml)); $zip->close();
            $this->expectException(ApiException::class);
            $workbooks->preview(base64_encode(file_get_contents($path)),$matrix);
        } finally { unlink($path); }
    }
}
