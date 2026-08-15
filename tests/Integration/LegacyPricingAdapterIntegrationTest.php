<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Modules\Consignment\Application\Contracts\PricingQuoteProvider;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class LegacyPricingAdapterIntegrationTest extends MySqlRedisTestCase
{
    public function test_invalid_cached_token_is_refreshed_once_and_quote_is_retried_once(): void
    {
        $username = Str::random(24);
        $password = Str::random(48);
        $oldToken = Str::random(64);
        $freshToken = Str::random(64);
        $nodeId = (string) Str::uuid();
        $hqId = (string) Str::uuid();
        $this->configure($username, $password, $nodeId, $hqId);
        Redis::connection('cache')->setex(
            'chabok:legacy-pricing:token',
            300,
            Crypt::encryptString($oldToken),
        );
        $quoteCalls = 0;
        Http::fake(function (Request $request) use (&$quoteCalls, $freshToken): \GuzzleHttp\Promise\PromiseInterface {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/login') {
                $fixture = $this->fixture('login-success');
                $fixture['objects']['user']['token'] = $freshToken;
                $fixture['objects']['user']['expiry'] = now()->addMinutes(10)->format('Y-m-d H:i:s');

                return Http::response($fixture, 200);
            }
            if ($path === '/getQuote') {
                $quoteCalls++;
                if ($quoteCalls === 1) {
                    return Http::response($this->fixture('quote-invalid-token'), 401);
                }

                return Http::response($this->fixture('quote-success'), 200);
            }

            return Http::response([], 500);
        });

        $options = $this->app->make(PricingQuoteProvider::class)->calculate(
            $this->normalizedInput($nodeId, $hqId),
        );

        $this->assertSame(2, $quoteCalls);
        $this->assertCount(2, $options);
        $this->assertSame(2500, $options[0]['total_amount']);
        $this->assertFalse($options[1]['available']);
        $encrypted = (string) Redis::connection('cache')->get('chabok:legacy-pricing:token');
        $this->assertStringNotContainsString($freshToken, $encrypted);
        $this->assertSame($freshToken, Crypt::decryptString($encrypted));
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/getQuote')
            && ($request->data()['order']['origin'] ?? null) === '10866'
            && ($request->data()['order']['destination'] ?? null) === '11944');
    }

    public function test_login_rejection_fails_closed_without_quote_or_credential_disclosure(): void
    {
        $nodeId = (string) Str::uuid();
        $hqId = (string) Str::uuid();
        $this->configure(Str::random(24), Str::random(48), $nodeId, $hqId);
        Http::fake([
            'https://api-zap.chabok.app/login*' => Http::response(
                $this->fixture('login-rejected'),
                403,
            ),
        ]);

        try {
            $this->app->make(PricingQuoteProvider::class)->calculate(
                $this->normalizedInput($nodeId, $hqId),
            );
            $this->fail('Rejected provider login must fail closed.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PricingUnavailable, $exception->errorCode);
            $this->assertSame('Pricing is temporarily unavailable.', $exception->getMessage());
        }
        Http::assertSentCount(1);
        Http::assertNotSent(
            static fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/getQuote',
        );
    }

    public function test_cross_host_redirect_and_missing_mappings_fail_closed_without_fallback_price(): void
    {
        $nodeId = (string) Str::uuid();
        $hqId = (string) Str::uuid();
        config()->set('chabok.consignment.legacy_pricing', [
            ...config('chabok.consignment.legacy_pricing'),
            'base_url' => 'https://api-zap.chabok.app',
            'username' => Str::random(24),
            'password' => Str::random(48),
            'origin_codes' => [],
            'destination_codes' => [],
            'party_codes' => [],
            'input_values' => [],
        ]);
        Http::fake();

        try {
            $this->app->make(PricingQuoteProvider::class)->calculate(
                $this->normalizedInput($nodeId, $hqId),
            );
            $this->fail('Missing semantic mappings must fail closed.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PricingUnavailable, $exception->errorCode);
            $this->assertSame(503, $exception->httpStatus);
        }
        Http::assertNothingSent();

        $this->configure(Str::random(24), Str::random(48), $nodeId, $hqId);
        Redis::connection('cache')->setex(
            'chabok:legacy-pricing:token',
            300,
            Crypt::encryptString(Str::random(64)),
        );
        Http::fake([
            'https://api-zap.chabok.app/getQuote' => Http::response(
                '',
                302,
                ['Location' => 'https://foreign.example/collect'],
            ),
        ]);
        try {
            $this->app->make(PricingQuoteProvider::class)->calculate(
                $this->normalizedInput($nodeId, $hqId),
            );
            $this->fail('Redirects must never produce a price.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PricingUnavailable, $exception->errorCode);
        }
    }

    private function configure(string $username, string $password, string $nodeId, string $hqId): void
    {
        config()->set('chabok.consignment.legacy_pricing', [
            ...config('chabok.consignment.legacy_pricing'),
            'base_url' => 'https://api-zap.chabok.app',
            'username' => $username,
            'password' => $password,
            'token_ttl_seconds' => 300,
            'expiry_timezone' => 'UTC',
            'expiry_skew_seconds' => 30,
            'origin_codes' => [$nodeId => 'origin-test-code'],
            'destination_codes' => ['ir|tehran|tehran' => 'destination-test-code'],
            'party_codes' => [$hqId => [
                'sender_code' => 'sender-test-code',
                'receiver_code' => 'receiver-test-code',
            ]],
            'input_values' => [
                'cod' => '',
                'extra_service' => '',
                'Extra_to' => '',
                'packing' => '',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function normalizedInput(string $nodeId, string $hqId): array
    {
        return [
            '_node_id' => $nodeId,
            '_hq_id' => $hqId,
            'sender' => ['country' => 'IR', 'state' => 'Tehran', 'city' => 'Tehran', 'legacy_city_code' => '10866'],
            'receiver' => ['country' => 'IR', 'state' => 'Tehran', 'city' => 'Tehran', 'legacy_city_code' => '11944'],
            'pickup_commitment_at' => '2026-08-01T11:00:00Z',
            'declared_value_amount' => 2000000,
            'weight_kg' => 1,
            'parcels' => [[
                'width_cm' => 10,
                'height_cm' => 10,
                'length_cm' => 10,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $path = dirname(__DIR__)."/Fixtures/LegacyPricing/{$name}.json";
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
