<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim($phone));
        if (! is_string($normalized) || ! preg_match('/^\+[1-9][0-9]{7,14}$/', $normalized)) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a phone number in international format, such as +14155552671.',
            ]);
        }

        return $normalized;
    }
}
