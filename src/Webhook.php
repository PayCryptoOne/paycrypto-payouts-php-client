<?php

declare(strict_types=1);

namespace PayCrypto\Payouts\Client;

final class Webhook
{
    public static function verify(array $payloadWithSign, string $secret): bool
    {
        if (!isset($payloadWithSign['sign']) || !is_string($payloadWithSign['sign'])) {
            return false;
        }
        $sign = $payloadWithSign['sign'];
        $rest = $payloadWithSign;
        unset($rest['sign']);
        $bodyJson = json_encode($rest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', $bodyJson, trim($secret));

        return hash_equals($expected, $sign);
    }
}
