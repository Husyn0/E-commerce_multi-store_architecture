<?php

namespace Tests\Unit;

use App\Models\TenantSettings;
use App\Services\Verification\HumanVerificationService;
use App\Support\SessionTokenHasher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HumanVerificationServiceTest extends TestCase
{
    public function test_demo_driver_rejects_an_invalid_token_as_validation_error(): void
    {
        $request = Request::create('/', 'POST', [], ['storefront_cart' => str_repeat('a', 64)]);
        $settings = new TenantSettings;
        $settings->human_check_driver = 'demo';

        try {
            (new HumanVerificationService(app(SessionTokenHasher::class)))
                ->verify($request, $settings, 'checkout', 'not-the-demo-token');
            $this->fail('Invalid human-verification tokens must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification', $exception->errors());
        }
    }

    public function test_verification_requires_a_storefront_session_cookie(): void
    {
        $settings = new TenantSettings;
        $settings->human_check_driver = 'demo';

        try {
            (new HumanVerificationService(app(SessionTokenHasher::class)))
                ->verify(Request::create('/', 'POST'), $settings, 'register', 'demo-pass');
            $this->fail('A storefront session cookie is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification', $exception->errors());
        }
    }
}
