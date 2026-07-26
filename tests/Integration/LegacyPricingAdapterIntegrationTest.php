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
                return Http::response(['session_token' => $freshToken], 200);
            }
            if ($path === '/getQuote') {
                $quoteCalls++;
                if ($quoteCalls === 1) {
                    return Http::response(['result' => false], 401);
                }

                return Http::response($this->successfulQuote(), 200);
            }

            return Http::response([], 500);
        });

        $options = $this->app->make(PricingQuoteProvider::class)->calculate(
            $this->normalizedInput($nodeId, $hqId),
        );

        $this->assertSame(2, $quoteCalls);
        $this->assertCount(1, $options);
        $this->assertSame(2500, $options[0]['total_amount']);
        $encrypted = (string) Redis::connection('cache')->get('chabok:legacy-pricing:token');
        $this->assertStringNotContainsString($freshToken, $encrypted);
        $this->assertSame($freshToken, Crypt::decryptString($encrypted));
        Http::assertSentCount(3);
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
            'token_field' => 'session_token',
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
            'token_field' => 'session_token',
            'token_ttl_seconds' => 300,
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
            'receiver' => ['country' => 'IR', 'state' => 'Tehran', 'city' => 'Tehran'],
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
    private function successfulQuote(): array
    {
        return [
            'result' => true,
            'objects' => [[
                'result' => true,
                'method_no' => '7',
                'method_name' => 'Sanitized method',
                'currency' => 'IRR',
                'quote' => '2500',
                'deliveryTimeWindow' => [],
                'price' => [
                    'zone' => '2',
                    'fld_Manual_Cost' => 2000,
                    'fld_Pack_Cost' => 0,
                    'fld_Charge_Cost' => 0,
                    'fld_Manual_Insurance' => 0,
                    'fld_Lab_Cost' => 0,
                    'fld_Agency_Cost_From' => 0,
                    'fld_Agency_Cost' => 0,
                    'fld_Manual_VAT' => 500,
                    'fld_Total_Cost' => 2500,
                    'price_list' => '4',
                    'min_ins' => 100,
                ],
            ]],
        ];
    }
}
