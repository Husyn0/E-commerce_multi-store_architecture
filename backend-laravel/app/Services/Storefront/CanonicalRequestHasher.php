<?php

namespace App\Services\Storefront;

use JsonException;
use RuntimeException;

class CanonicalRequestHasher
{
    /**
     * @param  array<string, mixed>  $request
     */
    public function hash(array $request): string
    {
        try {
            return hash('sha256', json_encode(
                $this->sortRecursively($request),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to canonicalize checkout request.', previous: $exception);
        }
    }

    private function sortRecursively(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortRecursively($item);
            }
        }
        unset($item);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
