<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Refund;
use App\Models\TenantSettings;
use App\Services\Payment\CheckoutCurrency;
use App\Services\Payment\DemoPaymentGateway;
use App\Services\Payment\PaymentProviderException;
use App\Services\Payment\StripePaymentGateway;
use App\Services\Storefront\CanonicalRequestHasher;
use App\Services\Storefront\StorefrontCsrfToken;
use App\Support\VerificationCodeHasher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutServicesTest extends TestCase
{
    public function test_checkout_accepts_only_two_minor_unit_currencies(): void
    {
        $currencies = new CheckoutCurrency;

        $this->assertTrue($currencies->supportsTwoMinorUnits('USD'));
        $this->assertFalse($currencies->supportsTwoMinorUnits('JPY'));
        $this->assertFalse($currencies->supportsTwoMinorUnits('us1'));
    }

    public function test_canonical_checkout_hash_ignores_object_key_order_but_not_values(): void
    {
        $hasher = new CanonicalRequestHasher;
        $first = [
            'shipping_address' => ['recipient_name' => 'Shopper', 'line1' => 'Main St'],
            'currency' => 'USD',
        ];
        $reordered = [
            'currency' => 'USD',
            'shipping_address' => ['line1' => 'Main St', 'recipient_name' => 'Shopper'],
        ];

        $this->assertSame($hasher->hash($first), $hasher->hash($reordered));
        $this->assertNotSame($hasher->hash($first), $hasher->hash(['currency' => 'EUR']));
    }

    public function test_storefront_csrf_cookie_is_signed_and_tamper_evident(): void
    {
        config(['services.session_token_pepper' => str_repeat('p', 48)]);
        $csrf = new StorefrontCsrfToken;
        $token = $csrf->issue();
        [$nonce, $signature] = explode('.', $token);
        $tampered = $nonce.'.'.($signature[0] === 'a' ? 'b' : 'a').substr($signature, 1);

        $this->assertTrue($csrf->isValid($token));
        $this->assertFalse($csrf->isValid($tampered));
        $this->assertFalse($csrf->isValid('untrusted-cookie-value'));
    }

    public function test_verification_code_hash_is_tenant_and_purpose_bound(): void
    {
        config(['services.verification_code_pepper' => str_repeat('v', 48)]);
        $hasher = new VerificationCodeHasher;
        $first = $hasher->hash('tenant-a', 'shopper@example.test', 'verify_email', '123456');

        $this->assertSame($first, $hasher->hash('tenant-a', 'shopper@example.test', 'verify_email', '123456'));
        $this->assertNotSame($first, $hasher->hash('tenant-b', 'shopper@example.test', 'verify_email', '123456'));
        $this->assertNotSame($first, $hasher->hash('tenant-a', 'shopper@example.test', 'password_reset', '123456'));
    }

    public function test_demo_payment_intent_is_tenant_scoped_and_repeatable(): void
    {
        $gateway = new DemoPaymentGateway;
        $settings = new TenantSettings;
        $settings->tenant_id = 'tenant-a';
        $order = new Order;
        $order->total_cents = 2500;
        $order->currency = 'USD';

        $first = $gateway->createIntent($settings, $order, 'idem-1');
        $retry = $gateway->createIntent($settings, $order, 'idem-1');
        $settings->tenant_id = 'tenant-b';
        $otherTenant = $gateway->createIntent($settings, $order, 'idem-1');

        $this->assertSame($first->id, $retry->id);
        $this->assertSame($first->clientSecret, $retry->clientSecret);
        $this->assertNotSame($first->id, $otherTenant->id);
    }

    public function test_stripe_payment_intent_uses_tenant_account_without_extra_payment_parameters(): void
    {
        config(['services.stripe.secret' => 'sk_test_example']);
        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);
        $settings = new TenantSettings;
        $settings->tenant_id = 'tenant-a';
        $settings->stripe_account_id = 'acct_tenant';
        $order = new Order;
        $order->total_cents = 2500;
        $order->currency = 'USD';
        $order->id = 'order-123';
        $order->order_number = '01J00000000000000000000000';

        $result = (new StripePaymentGateway)->createIntent($settings, $order, 'checkout_record-1');

        $this->assertSame('pi_test_123', $result->id);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Stripe-Account', 'acct_tenant')
            && $request->hasHeader('Idempotency-Key', 'checkout_record-1')
            && $request['amount'] === 2500
            && $request['currency'] === 'usd');
    }

    public function test_stripe_provider_errors_are_returned_without_provider_body_details(): void
    {
        config(['services.stripe.secret' => 'sk_test_example']);
        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response(['error' => ['message' => 'private']], 400),
        ]);
        $settings = new TenantSettings;
        $settings->tenant_id = 'tenant-a';
        $settings->stripe_account_id = 'acct_tenant';
        $order = new Order;
        $order->total_cents = 2500;
        $order->currency = 'USD';

        try {
            (new StripePaymentGateway)->createIntent($settings, $order, 'checkout_record-1');
            $this->fail('A failed Stripe response must raise a payment provider exception.');
        } catch (PaymentProviderException $exception) {
            $this->assertSame('The payment provider could not complete the request.', $exception->getMessage());
            $this->assertStringNotContainsString('private', $exception->getMessage());
        }
    }

    public function test_stripe_refund_stays_on_the_tenant_connected_account(): void
    {
        config(['services.stripe.secret' => 'sk_test_example']);
        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_123',
                'status' => 'pending',
            ]),
        ]);
        $settings = new TenantSettings;
        $settings->tenant_id = 'tenant-a';
        $settings->stripe_account_id = 'acct_tenant';
        $order = new Order;
        $order->id = 'order-123';
        $order->payment_intent_id = 'pi_tenant_123';
        $refund = new Refund;
        $refund->id = 'refund-123';
        $refund->amount_cents = 700;

        $result = (new StripePaymentGateway)->createRefund($settings, $order, $refund, 'refund_refund-123');

        $this->assertSame('re_test_123', $result->id);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Stripe-Account', 'acct_tenant')
            && $request->hasHeader('Idempotency-Key', 'refund_refund-123')
            && $request['payment_intent'] === 'pi_tenant_123'
            && $request['amount'] === 700);
    }
}
