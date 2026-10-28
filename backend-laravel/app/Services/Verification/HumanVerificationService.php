<?php

namespace App\Services\Verification;

use App\Models\HumanVerification;
use App\Models\TenantSettings;
use App\Support\SessionTokenHasher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class HumanVerificationService
{
    public function __construct(private readonly SessionTokenHasher $tokenHasher) {}

    public function verify(
        Request $request,
        TenantSettings $settings,
        string $action,
        string $responseToken,
    ): void {
        $sessionToken = $request->cookie('storefront_session') ?? $request->cookie('storefront_cart');
        if (! is_string($sessionToken) || strlen($sessionToken) !== 64 || ! ctype_xdigit($sessionToken)) {
            throw ValidationException::withMessages(['verification' => 'Initialize a Storefront session before verification.']);
        }

        $provider = $settings->human_check_driver;
        if ($provider === 'demo') {
            if (! app()->environment(['local', 'testing'])) {
                throw HumanVerificationUnavailable::forProvider();
            }
            if (! hash_equals('demo-pass', $responseToken)) {
                throw ValidationException::withMessages(['verification' => 'Human verification failed.']);
            }
        } else {
            $this->verifyWithProvider($request, $provider, $action, $responseToken);
        }

        $tokenHash = $this->tokenHasher->hash($sessionToken);
        DB::transaction(function () use ($action, $provider, $request, $settings, $tokenHash): void {
            $verification = HumanVerification::query()
                ->forTenant($settings->tenant_id)
                ->where('session_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($verification === null) {
                $verification = new HumanVerification;
                $verification->tenant_id = $settings->tenant_id;
                $verification->session_token_hash = $tokenHash;
            }
            $verification->provider = $provider;
            $verification->action = $action;
            $verification->passed = true;
            $verification->ip_address = $request->ip();
            $verification->expires_at = now()->addMinutes(5);
            $verification->save();
        });
    }

    private function verifyWithProvider(Request $request, string $provider, string $action, string $responseToken): void
    {
        if (! in_array($provider, ['turnstile', 'hcaptcha'], true)) {
            throw HumanVerificationUnavailable::forProvider();
        }

        $secretKey = $provider === 'turnstile' ? 'services.turnstile.secret' : 'services.hcaptcha.secret';
        $secret = (string) config($secretKey);
        if ($secret === '') {
            throw HumanVerificationUnavailable::forProvider();
        }

        $url = $provider === 'turnstile'
            ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            : 'https://api.hcaptcha.com/siteverify';

        try {
            $result = Http::asForm()
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(4)
                ->post($url, [
                    'secret' => $secret,
                    'response' => $responseToken,
                    'remoteip' => $request->ip(),
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            throw HumanVerificationUnavailable::forProvider();
        }

        if (! is_array($result) || ($result['success'] ?? false) !== true) {
            throw ValidationException::withMessages(['verification' => 'Human verification failed.']);
        }
        if (! is_string($result['hostname'] ?? null)
            || strtolower($result['hostname']) !== strtolower($request->getHost())) {
            throw ValidationException::withMessages(['verification' => 'Human verification failed.']);
        }
        if ($provider === 'turnstile' && ($result['action'] ?? null) !== $action) {
            throw ValidationException::withMessages(['verification' => 'Human verification failed.']);
        }
    }
}
