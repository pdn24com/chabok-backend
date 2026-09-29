<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Pricing\Application\Dto\WorkbookFileDto;
use Modules\Pricing\Domain\Exceptions\InvalidMatrixWorkbook;
use Modules\Pricing\Presentation\Http\Resources\WorkbookFileResource;
use Tests\TestCase;

final class WorkbookResponseTest extends TestCase
{
    public function test_workbook_parse_errors_keep_the_public_validation_envelope(): void
    {
        Route::get('/api/v1/refactor-workbook-error', fn () => throw new InvalidMatrixWorkbook('pricing.workbook_must_be_xlsx'));
        $this->getJson('/api/v1/refactor-workbook-error')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'فایل معتبر با پسوند xlsx انتخاب کنید.');
    }

    public function test_download_resource_preserves_its_original_field_names(): void
    {
        $file = new WorkbookFileDto('sample.xlsx', 'Y29udGVudA==');
        self::assertSame(['filename' => 'sample.xlsx', 'content_base64' => 'Y29udGVudA=='],
            (new WorkbookFileResource($file))->resolve(new Request));
    }
}
