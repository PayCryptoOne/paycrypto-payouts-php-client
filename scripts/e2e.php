<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PayCrypto\Payouts\Client\Env;
use PayCrypto\Payouts\Client\PayoutClient;

const FALLBACK_TRON_USDT = 'TNVq3iEcaGWbbsR34MTdg1JMTxvYFU8Qir';

$passed = 0;
$failed = 0;

function run(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        ++$passed;
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        ++$failed;
        echo 'FAIL '.$name.': '.$e->getMessage()."\n";
    }
}

function assertTrue(bool $c, string $m): void
{
    if (!$c) {
        throw new RuntimeException($m);
    }
}

$baseUrl = Env::get('PAYCRYPTO_PAYOUT_BASE_URL', 'http://localhost:3002/api/v1/');
$apiKey = Env::get('PAYCRYPTO_PAYOUT_API_KEY');
$masterPassword = Env::get('PAYCRYPTO_PAYOUT_MASTER_PASSWORD');
$amount = Env::get('PAYCRYPTO_PAYOUT_TEST_AMOUNT', '0.01');
$currency = Env::get('PAYCRYPTO_PAYOUT_TEST_CURRENCY', 'USDT');
$network = Env::get('PAYCRYPTO_PAYOUT_TEST_NETWORK', 'TRON');
$addrRaw = Env::get('PAYCRYPTO_PAYOUT_TEST_ADDRESS', '');
$address = $addrRaw !== '' ? $addrRaw : FALLBACK_TRON_USDT;

$client = new PayoutClient($apiKey, $baseUrl);
$ctx = ['createdId' => ''];

run('services', function () use ($client): void {
    $r = $client->services();
    assertTrue(($r['success'] ?? null) === true, 'services');
});

run('calc', function () use ($client, $amount, $address, $currency, $network): void {
    $r = $client->calc([
        'amount' => $amount,
        'address' => $address,
        'currency' => $currency,
        'network' => $network,
    ]);
    assertTrue(($r['success'] ?? null) === true, 'calc');
});

run('createPayout', function () use ($client, $amount, $address, $currency, $network, $masterPassword, &$ctx): void {
    $orderId = 'e2e_php_'.(string) time();
    $r = $client->createPayout([
        'amount' => $amount,
        'address' => $address,
        'currency' => $currency,
        'network' => $network,
        'order_id' => $orderId,
    ], $masterPassword);
    assertTrue(($r['success'] ?? null) === true, 'create envelope');
    $data = $r['data'] ?? null;
    assertTrue(is_array($data) && isset($data['id']) && is_string($data['id']) && $data['id'] !== '', 'create id');
    $ctx['createdId'] = $data['id'];
});

run('list', function () use ($client): void {
    $r = $client->list([]);
    assertTrue(($r['success'] ?? null) === true, 'list');
});

run('info', function () use ($client, &$ctx): void {
    $id = $ctx['createdId'];
    $r = $client->info($id);
    $data = $r['data'] ?? null;
    assertTrue(is_array($data) && isset($data['id']) && $data['id'] === $id, 'info id');
});

run('resend', function () use ($client, &$ctx): void {
    $r = $client->resend($ctx['createdId']);
    assertTrue(($r['success'] ?? null) === true, 'resend');
});

echo json_encode(['passed' => $passed, 'failed' => $failed], JSON_THROW_ON_ERROR)."\n";
if ($failed > 0) {
    exit(1);
}
