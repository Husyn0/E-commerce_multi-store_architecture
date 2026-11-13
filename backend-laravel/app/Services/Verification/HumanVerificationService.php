<?php

namespace App\Services\Verification;

use App\Models\TenantSettings;
use App\Support\SessionTokenHasher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HumanVerificationService
{
    public function __construct(private readonly SessionTokenHasher $tokenHasher) {}

    public function issueChallenge(Request $request, string $action): array
    {
        $sessionToken = $request->cookie('storefront_session') ?? $request->cookie('storefront_cart');
        if (! is_string($sessionToken) || strlen($sessionToken) !== 64 || ! ctype_xdigit($sessionToken)) {
            throw ValidationException::withMessages(['verification' => 'Initialize a Storefront session before verification.']);
        }

        $answer = '';
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($index = 0; $index < 6; $index++) {
            $answer .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $payload = Crypt::encryptString(json_encode([
            'answer' => $answer,
            'session' => bin2hex($this->tokenHasher->hash($sessionToken)),
            'action' => $action,
            'expires' => now()->addMinutes(3)->timestamp,
        ], JSON_THROW_ON_ERROR));

        $svg = $this->captchaSvg($answer);
        return [
            'challenge_token' => $payload,
            'image' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'expires_in' => 180,
        ];
    }

    public function verify(
        Request $request,
        TenantSettings $settings,
        string $action,
        string $responseToken,
        ?string $answer = null,
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
            try {
                $challenge = json_decode(Crypt::decryptString($responseToken), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $challenge = null;
            }
            if (! is_array($challenge)
                || ($challenge['action'] ?? null) !== $action
                || ($challenge['session'] ?? null) !== bin2hex($this->tokenHasher->hash($sessionToken))
                || ! is_int($challenge['expires'] ?? null)
                || $challenge['expires'] < now()->timestamp
                || ! is_string($answer)
                || ! hash_equals((string) ($challenge['answer'] ?? ''), strtoupper(trim($answer)))) {
                throw ValidationException::withMessages(['verification' => 'Human verification failed.']);
            }
        } else {
            $this->verifyWithProvider($request, $provider, $action, $responseToken);
        }

        DB::table('human_verifications')->upsert([[
            'id' => (string) Str::uuid(),
            'tenant_id' => $settings->tenant_id,
            'session_token_hash' => $this->tokenHasher->hash($sessionToken),
            'provider' => $provider,
            'action' => $action,
            'passed' => true,
            'ip_address' => $request->ip(),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ]], ['tenant_id', 'session_token_hash'], [
            'provider', 'action', 'passed', 'ip_address', 'expires_at',
        ]);
    }

    private function captchaSvg(string $answer): string
    {
        $noise = '';
        for ($i = 0; $i < 7; $i++) {
            $x1 = random_int(0, 240); $y1 = random_int(0, 72);
            $noise .= '<path d="M'.$x1.' '.$y1.' Q'.random_int(0, 240).' '.random_int(0, 72).' '.random_int(0, 240).' '.random_int(0, 72).'"/>';
        }
        $letters = '';
        foreach (str_split($answer) as $index => $letter) {
            $x = 20 + $index * 34;
            $rotation = random_int(-18, 18);
            $letters .= '<text x="'.$x.'" y="52" transform="rotate('.$rotation.' '.$x.' 42)">'.$letter.'</text>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="72" viewBox="0 0 240 72"><rect width="240" height="72" rx="8" fill="#f4f0e8"/><g fill="none" stroke="#91887b" stroke-width="1.5" opacity=".6">'.$noise.'</g><g fill="#26231f" font-family="monospace" font-size="32" font-weight="700">'.$letters.'</g></svg>';
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
