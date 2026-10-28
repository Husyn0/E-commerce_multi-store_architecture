<?php

namespace App\Providers;

use App\Models\User;
use App\Support\TenantAccessToken;
use App\Tenancy\EloquentTenantHostLocator;
use App\Tenancy\TenantHostLocator;
use App\Tenancy\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TenantHostLocator::class, EloquentTenantHostLocator::class);
        $this->app->scoped(TenantManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('payment-webhook', function (Request $request): Limit {
            $provider = $request->header('Stripe-Signature') !== null ? 'stripe' : 'demo';

            return Limit::perMinute(30)->by($request->ip().'|'.$provider);
        });

        Auth::viaRequest('tenant-jwt', function (Request $request): ?User {
            $context = app(TenantManager::class)->current();
            $token = $request->bearerToken();

            if ($context === null || $token === null) {
                return null;
            }

            $claims = app(TenantAccessToken::class)->verify($token, $context);
            if ($claims === null) {
                return null;
            }

            return User::query()
                ->where('tenant_id', $claims['tid'])
                ->whereKey($claims['sub'])
                ->where('role', 'admin')
                ->where('status', 'active')
                ->first();
        });

        $trustedProxies = config('tenancy.trusted_proxies');
        TrustProxies::flushState();

        if ($trustedProxies !== []) {
            TrustProxies::at($trustedProxies);
        }

        if (app()->isProduction()) {
            $defaults = config('services.defaults');
            if ($defaults['payment_driver'] === 'demo'
                || $defaults['verification_driver'] === 'demo'
                || $defaults['human_check_driver'] === 'demo'
                || $defaults['email_provider'] === 'log'
                || $defaults['sms_provider'] === 'log') {
                throw new LogicException('Production default payment, verification, and delivery drivers must be configured.');
            }
        }
    }
}
