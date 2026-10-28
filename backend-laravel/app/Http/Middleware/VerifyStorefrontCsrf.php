<?php

namespace App\Http\Middleware;

use App\Services\Storefront\StorefrontCsrfToken;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VerifyStorefrontCsrf
{
    public function __construct(private readonly StorefrontCsrfToken $csrf) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with((string) $request->header('Authorization'), 'Bearer ')) {
            return $next($request);
        }

        $cookieToken = $request->cookie('storefront_csrf');
        $headerToken = $request->header('X-CSRF-TOKEN');

        try {
            $valid = is_string($cookieToken) && $this->csrf->isValid($cookieToken);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Storefront CSRF security is not configured.'], 503);
        }

        if (! $valid || ! is_string($headerToken) || ! hash_equals($cookieToken, $headerToken)) {
            return response()->json(['message' => 'CSRF token mismatch.'], 419);
        }

        return $next($request);
    }
}
