<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\Middleware\NegotiateLocale;
use Modules\Manifest\Domain\Exceptions\ManifestRuleViolation;
use Tests\TestCase;

final class BilingualApiMessageTest extends TestCase
{
    public function test_locale_negotiation(): void
    {
        $cases = [
            '' => 'fa', 'fa' => 'fa', 'fa-IR' => 'fa', 'en' => 'en', 'en-US' => 'en',
            'de' => 'fa', '*' => 'fa', 'en;q=0.4, fa;q=0.9' => 'fa', 'fa;q=0.2, en;q=0.8' => 'en',
        ];
        foreach ($cases as $header => $expected) {
            self::assertSame($expected, NegotiateLocale::resolve($header), "header: [{$header}]");
        }
    }

    public function test_error_body_is_persian_by_default_and_english_on_request(): void
    {
        foreach (['fa' => 'منبع مورد نظر پیدا نشد.', 'en' => 'Resource not found.'] as $locale => $expected) {
            $request = Request::create('/api/v1/probe');
            $request->attributes->set('locale', $locale);
            $body = json_decode(ApiResponder::error($request, ApiErrorCode::ResourceNotFound, 'common.resource_not_found', 404)->getContent(), true);
            self::assertSame($expected, $body['message']);
            self::assertSame($locale, $body['locale']);
            self::assertSame('RESOURCE_NOT_FOUND', $body['error_code']);
        }
    }

    public function test_placeholders_interpolate_in_both_languages(): void
    {
        foreach (['fa' => 'تغییر وضعیت از PD به OK مجاز نیست.', 'en' => 'The PD to OK transition is not allowed.'] as $locale => $expected) {
            $request = Request::create('/api/v1/probe');
            $request->attributes->set('locale', $locale);
            $body = json_decode(ApiResponder::error($request, ApiErrorCode::ValidationError, 'operations.transition_is_not_allowed', 422, messageParams: ['from' => 'PD', 'to' => 'OK'])->getContent(), true);
            self::assertSame($expected, $body['message']);
        }
    }

    public function test_exception_carries_the_key_so_the_domain_stays_locale_free(): void
    {
        $exception = new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        self::assertSame('common.resource_not_found', $exception->messageKey);
        // Logs show the stable key; only the Presentation layer resolves prose.
        self::assertSame('common.resource_not_found', $exception->getMessage());
    }

    public function test_domain_exceptions_also_carry_keys(): void
    {
        $violation = new ManifestRuleViolation(ApiErrorCode::ManifestNotEditable, 'manifest.manifest_can_no_longer_be_edited');
        self::assertSame('manifest.manifest_can_no_longer_be_edited', $violation->messageKey);
        self::assertSame('manifest.manifest_can_no_longer_be_edited', $violation->getMessage());

        $request = Request::create('/api/v1/probe');
        $request->attributes->set('locale', 'fa');
        $body = json_decode(ApiResponder::error($request, $violation->errorCode, $violation->messageKey, 422)->getContent(), true);
        self::assertSame('این مانیفست دیگر قابل ویرایش نیست.', $body['message']);
    }

    public function test_every_key_exists_in_both_catalogues(): void
    {
        $flatten = static function (array $catalogue): array {
            $flat = [];
            foreach ($catalogue as $group => $messages) {
                foreach ($messages as $key => $message) {
                    $flat["{$group}.{$key}"] = $message;
                }
            }

            return $flat;
        };
        $en = $flatten(require base_path('lang/en/api.php'));
        $fa = $flatten(require base_path('lang/fa/api.php'));

        self::assertSame(array_keys($en), array_keys($fa), 'lang/en and lang/fa must define the same keys.');
        foreach ($fa as $key => $message) {
            self::assertMatchesRegularExpression('/\p{Arabic}/u', $message, "{$key} is not translated.");
        }
    }

    public function test_field_errors_are_persian(): void
    {
        app()->setLocale('fa');
        self::assertSame('فیلد name الزامی است.', __('validation.required', ['attribute' => 'name']));
        app()->setLocale('en');
        self::assertSame('The name field is required.', __('validation.required', ['attribute' => 'name']));
    }
}
