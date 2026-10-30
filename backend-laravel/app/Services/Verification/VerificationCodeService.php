<?php

namespace App\Services\Verification;

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\VerificationCodeHasher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class VerificationCodeService
{
    public function __construct(private readonly VerificationCodeHasher $hasher) {}

    public function issueEmail(
        TenantSettings $settings,
        string $identifier,
        string $purpose,
        ?string $userId,
        ?string $ip,
    ): bool {
        $identifier = mb_strtolower(trim($identifier));
        $tenantId = $settings->tenant_id;
        $identifierKey = 'otp:identifier:'.hash('sha256', $tenantId.'|'.$identifier.'|'.$purpose);
        $ipKey = 'otp:ip:'.hash('sha256', $tenantId.'|'.($ip ?? 'unknown'));
        if (RateLimiter::tooManyAttempts($identifierKey, 3) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return false;
        }
        RateLimiter::hit($identifierKey, 900);
        RateLimiter::hit($ipKey, 3600);

        $mailer = $settings->email_provider;
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if (! in_array($mailer, ['smtp', 'postmark', 'ses'], true)
            || ! is_string($transport)
            || $transport !== $mailer) {
            throw new VerificationDeliveryException('A non-logging email provider must be configured.');
        }

        $code = (string) random_int(100000, 999999);
        $record = DB::transaction(function () use ($identifier, $purpose, $tenantId, $userId, $code): VerificationCode {
            VerificationCode::query()
                ->forTenant($tenantId)
                ->where('identifier', $identifier)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $record = new VerificationCode;
            $record->tenant_id = $tenantId;
            $record->user_id = $userId;
            $record->identifier = $identifier;
            $record->channel = 'email';
            $record->purpose = $purpose;
            $record->code_hash = $this->hasher->hash($tenantId, $identifier, $purpose, $code);
            $record->expires_at = now()->addMinutes(10);
            $record->attempts = 0;
            $record->max_attempts = 5;
            $record->save();

            return $record;
        });

        try {
            Mail::mailer($mailer)->raw(
                "Your verification code is {$code}. It expires in 10 minutes.",
                fn ($message) => $message->to($identifier)->subject('Your verification code'),
            );
        } catch (TransportExceptionInterface) {
            VerificationCode::query()
                ->forTenant($tenantId)
                ->whereKey($record->id)
                ->update(['used_at' => now()]);

            throw new VerificationDeliveryException('The verification message could not be delivered.');
        }

        return true;
    }

    public function issueSms(
        TenantSettings $settings,
        string $phone,
        string $purpose,
        ?string $userId,
        ?string $ip,
    ): bool {
        $phone = trim($phone);
        $tenantId = $settings->tenant_id;
        $identifierKey = 'otp:identifier:'.hash('sha256', $tenantId.'|'.$phone.'|'.$purpose);
        $ipKey = 'otp:ip:'.hash('sha256', $tenantId.'|'.($ip ?? 'unknown'));
        if (RateLimiter::tooManyAttempts($identifierKey, 3) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return false;
        }
        RateLimiter::hit($identifierKey, 900);
        RateLimiter::hit($ipKey, 3600);

        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');
        $fromNumber = (string) config('services.twilio.from_number');
        if ($settings->sms_provider !== 'twilio' || $accountSid === '' || $authToken === '' || $fromNumber === '') {
            throw new VerificationDeliveryException('SMS delivery is not configured.');
        }

        $code = (string) random_int(100000, 999999);
        $record = DB::transaction(function () use ($phone, $purpose, $tenantId, $userId, $code): VerificationCode {
            VerificationCode::query()
                ->forTenant($tenantId)
                ->where('identifier', $phone)
                ->where('channel', 'sms')
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $record = new VerificationCode;
            $record->tenant_id = $tenantId;
            $record->user_id = $userId;
            $record->identifier = $phone;
            $record->channel = 'sms';
            $record->purpose = $purpose;
            $record->code_hash = $this->hasher->hash($tenantId, $phone, $purpose, $code);
            $record->expires_at = now()->addMinutes(10);
            $record->attempts = 0;
            $record->max_attempts = 5;
            $record->save();

            return $record;
        });

        try {
            Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->connectTimeout(3)
                ->timeout(8)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $phone,
                    'Body' => ($purpose === 'verify_phone' ? 'Your phone verification code' : 'Your password reset code')
                        ." is {$code}. It expires in 10 minutes.",
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            VerificationCode::query()
                ->forTenant($tenantId)
                ->whereKey($record->id)
                ->update(['used_at' => now()]);
            throw new VerificationDeliveryException('The verification message could not be delivered.', previous: $exception);
        }

        return true;
    }

    public function issueCliAdminPasswordReset(TenantSettings $settings, User $user): ?string
    {
        if ($user->tenant_id !== $settings->tenant_id || $user->role !== 'admin' || $user->status !== 'active') {
            throw new VerificationDeliveryException('An active tenant Admin account is required.');
        }

        $identifier = mb_strtolower(trim((string) $user->email));
        $tenantId = $settings->tenant_id;
        $identifierKey = 'otp:identifier:'.hash('sha256', $tenantId.'|'.$identifier.'|password_reset');
        $ipKey = 'otp:ip:'.hash('sha256', $tenantId.'|cli');
        if (RateLimiter::tooManyAttempts($identifierKey, 3) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return null;
        }
        RateLimiter::hit($identifierKey, 900);
        RateLimiter::hit($ipKey, 3600);

        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($identifier, $tenantId, $user, $code): void {
            VerificationCode::query()
                ->forTenant($tenantId)
                ->where('identifier', $identifier)
                ->where('purpose', 'password_reset')
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $record = new VerificationCode;
            $record->tenant_id = $tenantId;
            $record->user_id = $user->id;
            $record->identifier = $identifier;
            $record->channel = 'email';
            $record->purpose = 'password_reset';
            $record->code_hash = $this->hasher->hash($tenantId, $identifier, 'password_reset', $code);
            $record->expires_at = now()->addMinutes(10);
            $record->attempts = 0;
            $record->max_attempts = 5;
            $record->save();
        });

        return $code;
    }

    public function consumeEmail(string $tenantId, string $identifier, string $purpose, string $code): bool
    {
        $identifier = mb_strtolower(trim($identifier));

        return DB::transaction(function () use ($tenantId, $identifier, $purpose, $code): bool {
            $record = VerificationCode::query()
                ->forTenant($tenantId)
                ->where('identifier', $identifier)
                ->where('channel', 'email')
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->whereColumn('attempts', '<', 'max_attempts')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                return false;
            }

            $expected = $this->hasher->hash($tenantId, $identifier, $purpose, $code);
            if (! hash_equals($record->code_hash, $expected)) {
                $record->attempts++;
                $record->save();

                return false;
            }

            $record->used_at = now();
            $record->save();

            return true;
        });
    }

    public function consumeSms(string $tenantId, string $identifier, string $purpose, string $code): bool
    {
        $identifier = trim($identifier);

        return DB::transaction(function () use ($tenantId, $identifier, $purpose, $code): bool {
            $record = VerificationCode::query()
                ->forTenant($tenantId)
                ->where('identifier', $identifier)
                ->where('channel', 'sms')
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->whereColumn('attempts', '<', 'max_attempts')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                return false;
            }

            $expected = $this->hasher->hash($tenantId, $identifier, $purpose, $code);
            if (! hash_equals($record->code_hash, $expected)) {
                $record->attempts++;
                $record->save();

                return false;
            }

            $record->used_at = now();
            $record->save();

            return true;
        });
    }
}
