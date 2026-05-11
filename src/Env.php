<?php

declare(strict_types=1);

namespace PayCrypto\Payouts\Client;

final class Env
{
    public static function get(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            if ($default !== null) {
                return $default;
            }
            throw new \RuntimeException("Missing env: {$key}");
        }
        return trim($value);
    }
}
