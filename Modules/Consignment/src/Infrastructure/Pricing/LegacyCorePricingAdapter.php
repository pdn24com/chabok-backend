<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Modules\Consignment\Application\Contracts\PricingQuoteProvider;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class LegacyCorePricingAdapter implements PricingQuoteProvider
{
    public function __construct(
        private LegacyPricingRequestMapper $mapper,
        private LegacyQuoteNormalizer $normalizer,
    ) {}

    public function calculate(array $normalizedInput): array
    {
        $body = $this->mapper->map($normalizedInput);
        $token = $this->token();
        $response = $this->quote($token, $body);
        if ($response->status() === 401) {
            $this->forgetToken();
            $token = $this->refreshToken();
            $response = $this->quote($token, $body);
        }
        if ($response->status() === 401) {
            $this->forgetToken();
            $this->unavailable();
        }
        if (! $response->successful()) {
            $this->unavailable();
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            $this->unavailable();
        }

        return $this->normalizer->normalize($payload);
    }

    /** @param array<string, mixed> $body */
    private function quote(string $token, array $body): Response
    {
        try {
            return $this->client()
                ->withHeaders(['auth' => $token])
                ->post($this->endpoint('/getQuote'), $body);
        } catch (\Throwable) {
            $this->unavailable();
        }
    }

    private function token(): string
    {
        $encrypted = Redis::connection('cache')->get($this->tokenKey());
        if (is_string($encrypted)) {
            try {
                $token = Crypt::decryptString($encrypted);
                if ($token !== '') {
                    return $token;
                }
            } catch (\Throwable) {
                $this->forgetToken();
            }
        }

        return $this->refreshToken();
    }

    private function refreshToken(): string
    {
        try {
            return Cache::store('redis')->lock($this->lockKey(), 15)->block(3, function (): string {
                $encrypted = Redis::connection('cache')->get($this->tokenKey());
                if (is_string($encrypted)) {
                    try {
                        $cached = Crypt::decryptString($encrypted);
                        if ($cached !== '') {
                            return $cached;
                        }
                    } catch (\Throwable) {
                        $this->forgetToken();
                    }
                }

                return $this->authenticate();
            });
        } catch (\Throwable) {
            $this->unavailable();
        }
    }

    private function authenticate(): string
    {
        $config = $this->config();
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $tokenField = (string) ($config['token_field'] ?? '');
        if ($username === '' || $password === '' || $tokenField === '') {
            $this->unavailable();
        }
        $input = json_encode([
            'user' => ['username' => $username, 'password' => $password],
        ], JSON_THROW_ON_ERROR);
        try {
            // Request logging is intentionally not attached to this isolated
            // client because the legacy protocol places credentials in a query.
            $response = $this->client()
                ->withQueryParameters(['input' => $input])
                ->post($this->endpoint('/login'));
        } catch (\Throwable) {
            $this->unavailable();
        }
        if (! $response->successful()) {
            $this->unavailable();
        }
        $payload = $response->json();
        $token = is_array($payload) ? ($payload[$tokenField] ?? null) : null;
        if (! is_string($token) || trim($token) === '') {
            $this->unavailable();
        }
        $ttl = max(30, min(3600, (int) ($config['token_ttl_seconds'] ?? 300)));
        Redis::connection('cache')->setex(
            $this->tokenKey(),
            $ttl,
            Crypt::encryptString($token),
        );

        return $token;
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $config = $this->config();

        return Http::acceptJson()
            ->asJson()
            ->withoutRedirecting()
            ->withOptions([
                'verify' => true,
                'connect_timeout' => (int) ($config['connect_timeout_seconds'] ?? 3),
                'timeout' => (int) ($config['request_timeout_seconds'] ?? 10),
            ]);
    }

    private function endpoint(string $path): string
    {
        $base = rtrim((string) ($this->config()['base_url'] ?? ''), '/');
        $parts = parse_url($base);
        if ($base === '' || ! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host']) || isset($parts['user'], $parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            $this->unavailable();
        }

        return $base.$path;
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return (array) config('chabok.consignment.legacy_pricing', []);
    }

    private function forgetToken(): void
    {
        Redis::connection('cache')->del($this->tokenKey());
    }

    private function tokenKey(): string
    {
        return 'chabok:legacy-pricing:token';
    }

    private function lockKey(): string
    {
        return 'chabok:legacy-pricing:token-refresh';
    }

    private function unavailable(): never
    {
        throw new ApiException(
            ApiErrorCode::PricingUnavailable,
            503,
            'Pricing is temporarily unavailable.',
        );
    }
}
