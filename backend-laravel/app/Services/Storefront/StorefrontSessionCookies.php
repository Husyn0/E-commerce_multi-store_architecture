<?php

namespace App\Services\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontSessionCookies
{
    public function __construct(private readonly StorefrontCsrfToken $csrf) {}

    public function attach(JsonResponse $response, Request $request, string $token): JsonResponse
    {
        $secure = $request->isSecure() || app()->isProduction();

        return $response
            ->withCookie(cookie('storefront_session', $token, 60 * 24 * 30, '/', null, $secure, true, false, 'lax'))
            ->withCookie(cookie('storefront_csrf', $this->csrf->issue(), 60 * 24 * 30, '/', null, $secure, false, false, 'lax'))
            ->header('Cache-Control', 'no-store');
    }

    public function clear(JsonResponse $response, Request $request): JsonResponse
    {
        $secure = $request->isSecure() || app()->isProduction();

        return $response
            ->withCookie(cookie('storefront_session', '', -1, '/', null, $secure, true, false, 'lax'))
            ->withCookie(cookie('storefront_csrf', $this->csrf->issue(), 60 * 24 * 30, '/', null, $secure, false, false, 'lax'))
            ->header('Cache-Control', 'no-store');
    }
}
