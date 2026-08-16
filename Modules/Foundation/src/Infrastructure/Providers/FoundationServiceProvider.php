<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Application\Contracts\NodeAccessValidator;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Infrastructure\Persistence\LaravelTransactionManager;
use Modules\Foundation\Infrastructure\Authorization\DenyNodeAccessValidator;
use Modules\Foundation\Infrastructure\Security\FirebaseAccessTokenService;
use Modules\Foundation\Infrastructure\Observability\RedisSecurityMetricRecorder;
use Modules\Foundation\Infrastructure\Console\SecurityMetricsCommand;

final class FoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccessTokenService::class, FirebaseAccessTokenService::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(NodeAccessValidator::class, DenyNodeAccessValidator::class);
        $this->app->singleton(SecurityMetricRecorder::class, RedisSecurityMetricRecorder::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->validateDeploymentConfiguration();

        RateLimiter::for('auth-login', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateKey($request, 'identifier')));
        RateLimiter::for('auth-refresh', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateKey($request, null)));
        RateLimiter::for('auth-otp-send', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->rateKey($request, 'identifier')));
        RateLimiter::for('auth-otp-verify', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateKey($request, 'challenge_id')));
        RateLimiter::for('auth-password', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($this->rateKey($request, null)));

        if ($this->app->runningInConsole()) {
            $this->commands([SecurityMetricsCommand::class]);
        }
    }

    private function rateKey(Request $request, ?string $field): string
    {
        $subject = $field === null
            ? ''
            : mb_strtolower(trim((string) $request->input($field, '')));

        return hash('sha256', $request->ip().'|'.$subject);
    }

    private function validateDeploymentConfiguration(): void
    {
        if (! $this->app->environment(['staging', 'production'])) {
            return;
        }

        $httpAllowedInStaging = $this->app->environment('staging')
            && (bool) config('chabok.branch_panel.local_http_allowed');

        foreach ((array) config('chabok.branch_panel.origins', []) as $origin) {
            if (! $httpAllowedInStaging && ! str_starts_with((string) $origin, 'https://')) {
                throw new \LogicException(
                    'Staging and production Branch Panel origins must use HTTPS.',
                );
            }
        }

        if ((array) config('chabok.branch_panel.origins', []) === []) {
            throw new \LogicException(
                'At least one exact Branch Panel origin is required.',
            );
        }
    }
}
