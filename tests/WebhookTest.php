<?php

declare(strict_types=1);

namespace PayCrypto\Payouts\Client\Tests;

use PayCrypto\Payouts\Client\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    public function testVerify_acceptsValidSign(): void
    {
        $secret = 'abc';
        $core = [
            'type' => 'payout',
            'id' => '11111111-1111-4111-8111-111111111111',
            'amount' => '1',
            'commission' => ['fee_amount' => '0.1'],
            'is_final' => false,
            'status' => 'process',
            'txid' => null,
            'currency' => 'USDT',
            'network' => 'tron',
        ];
        $bodyJson = json_encode($core, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $sign = hash_hmac('sha256', $bodyJson, $secret);
        $payload = array_merge($core, ['sign' => $sign]);
        $this->assertTrue(Webhook::verify($payload, $secret));
    }

    public function testVerify_rejectsWrongSecret(): void
    {
        $secret = 'abc';
        $core = ['type' => 'payout', 'id' => 'a', 'amount' => '1'];
        $sign = hash_hmac('sha256', json_encode($core, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $secret);
        $payload = array_merge($core, ['sign' => $sign]);
        $this->assertFalse(Webhook::verify($payload, 'wrong'));
    }

    public function testVerify_rejectsMissingSign(): void
    {
        $this->assertFalse(Webhook::verify(['type' => 'payout'], 's'));
    }

    public function testVerify_rejectsTamperedBody(): void
    {
        $secret = 'k';
        $core = ['type' => 'payout', 'id' => 'b', 'amount' => '1'];
        $sign = hash_hmac('sha256', json_encode($core, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $secret);
        $payload = array_merge($core, ['sign' => $sign]);
        $payload['amount'] = '999';
        $this->assertFalse(Webhook::verify($payload, $secret));
    }

    public function testVerify_trimsSecret(): void
    {
        $secret = '  xyz  ';
        $trimmed = trim($secret);
        $core = ['type' => 'payout', 'id' => 'c'];
        $sign = hash_hmac('sha256', json_encode($core, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $trimmed);
        $payload = array_merge($core, ['sign' => $sign]);
        $this->assertTrue(Webhook::verify($payload, $secret));
    }
}
