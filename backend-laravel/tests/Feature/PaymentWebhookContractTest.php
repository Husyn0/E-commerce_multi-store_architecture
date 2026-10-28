<?php

namespace Tests\Feature;

use Tests\TestCase;

class PaymentWebhookContractTest extends TestCase
{
    public function test_demo_webhook_rejects_an_invalid_signature(): void
    {
        config(['services.demo_webhook_secret' => str_repeat('s', 40)]);

        $response = $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_DEMO_SIGNATURE' => str_repeat('0', 64)],
            json_encode(['id' => 'tenant:event-1', 'type' => 'payment_intent.succeeded']),
        );

        $response->assertBadRequest()->assertJson(['message' => 'Invalid webhook signature.']);
    }

    public function test_signed_platform_event_is_rejected_before_tenant_resolution(): void
    {
        $secret = str_repeat('s', 40);
        config(['services.demo_webhook_secret' => $secret]);
        $body = json_encode(['id' => 'tenant-a:event-1', 'type' => 'customer.subscription.created', 'tenant_id' => 'tenant-a']);

        $response = $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DEMO_SIGNATURE' => hash_hmac('sha256', $body, $secret),
            ],
            $body,
        );

        $response->assertBadRequest()->assertJson(['message' => 'Unsupported payment event.']);
    }

    public function test_demo_webhook_fails_closed_when_secret_is_blank(): void
    {
        config(['services.demo_webhook_secret' => '']);

        $response = $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"id":"tenant-a:event-1","type":"payment_intent.succeeded"}',
        );

        $response->assertServiceUnavailable();
    }
}
