<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PayCrypto\Payouts\Client\Env;
use PayCrypto\Payouts\Client\PayoutApiException;
use PayCrypto\Payouts\Client\PayoutClient;

const FALLBACK_TRON_USDT = 'TNVq3iEcaGWbbsR34MTdg1JMTxvYFU8Qir';

$passed = 0;
$failed = 0;
$skipped = 0;

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

function runOptional(string $name, callable $fn): void
{
    global $passed, $skipped;
    try {
        $fn();
        ++$passed;
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        ++$skipped;
        echo 'SKIP '.$name.': '.$e->getMessage()."\n";
    }
}

function assertTrue(bool $c, string $m): void
{
    if (!$c) {
        throw new RuntimeException($m);
    }
}

function isoDaysAgo(int $days): string
{
    $d = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $d = $d->modify('-'.$days.' days');

    return $d->format('Y-m-d H:i:s');
}

function nowUtcFormatted(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

$baseUrl = Env::get('PAYCRYPTO_PAYOUT_BASE_URL', 'http://localhost:3002/api/v1/');
$apiKey = Env::get('PAYCRYPTO_PAYOUT_API_KEY');
$masterPassword = trim((string) getenv('PAYCRYPTO_PAYOUT_MASTER_PASSWORD'));
$liveWithdraw = trim((string) getenv('PAYCRYPTO_PAYOUT_LIVE_WITHDRAW')) === '1';
$amount = Env::get('PAYCRYPTO_PAYOUT_TEST_AMOUNT', '0.01');
$currency = Env::get('PAYCRYPTO_PAYOUT_TEST_CURRENCY', 'USDT');
$network = Env::get('PAYCRYPTO_PAYOUT_TEST_NETWORK', 'TRON');
$addrRaw = Env::get('PAYCRYPTO_PAYOUT_TEST_ADDRESS', '');
$address = $addrRaw !== '' ? $addrRaw : FALLBACK_TRON_USDT;

$client = new PayoutClient($apiKey, $baseUrl);
$createdId = '';
$lastOrderId = '';

run('services_envelope', function () use ($client): void {
    $r = $client->services();
    assertTrue(($r['success'] ?? null) === true, 'success');
    $data = $r['data'] ?? null;
    assertTrue(is_array($data) && isset($data['services']) && is_array($data['services']), 'data.services');
});

run('calc_basic', function () use ($client, $amount, $address, $currency, $network): void {
    $r = $client->calc([
        'amount' => $amount,
        'address' => $address,
        'currency' => $currency,
        'network' => $network,
    ]);
    assertTrue(($r['success'] ?? null) === true, 'success');
    $d = $r['data'] ?? null;
    assertTrue(is_array($d), 'data');
    assertTrue(isset($d['commission']) || isset($d['merchant_amount']), 'calc fields');
});

runOptional('calc_with_priority', function () use ($client, $amount, $address, $currency, $network): void {
    $r = $client->calc([
        'amount' => $amount,
        'address' => $address,
        'currency' => $currency,
        'network' => $network,
        'priority' => 'recommended',
    ]);
    assertTrue(($r['success'] ?? null) === true, 'success');
});

runOptional('calc_convert_usd_to_usdt', function () use ($client, $address, $network): void {
    $r = $client->calc([
        'amount' => '10',
        'address' => $address,
        'currency' => 'USD',
        'to_currency' => 'USDT',
        'network' => $network,
    ]);
    assertTrue(($r['success'] ?? null) === true, 'success');
    $d = $r['data'] ?? [];
    assertTrue(is_array($d) && array_key_exists('convert', $d) && $d['convert'] !== null, 'convert');
});

run('list_no_filters', function () use ($client): void {
    $r = $client->list([]);
    assertTrue(($r['success'] ?? null) === true, 'success');
    $d = $r['data'] ?? null;
    assertTrue(is_array($d) && isset($d['items']) && is_array($d['items']), 'items');
    assertTrue(isset($d['paginate']) && is_array($d['paginate']), 'paginate');
});

run('list_date_range', function () use ($client): void {
    $r = $client->list([
        'date_from' => isoDaysAgo(30),
        'date_to' => nowUtcFormatted(),
    ]);
    assertTrue(($r['success'] ?? null) === true, 'success');
    $d = $r['data'] ?? null;
    assertTrue(is_array($d) && isset($d['items']) && is_array($d['items']), 'items');
});

runOptional('list_next_cursor_page', function () use ($client): void {
    $page1 = $client->list([]);
    $next = $page1['data']['paginate']['nextCursor'] ?? null;
    if ($next === null || $next === '') {
        throw new RuntimeException('no nextCursor on first page');
    }
    $page2 = $client->list(['cursor' => $next]);
    assertTrue(($page2['success'] ?? null) === true, 'page2');
    assertTrue(isset($page2['data']['items']) && is_array($page2['data']['items']), 'page2 items');
});

run('info_not_found_returns_error', function () use ($client): void {
    try {
        $client->info('11111111-1111-4111-8111-111111111111');
        throw new RuntimeException('expected PayoutApiException');
    } catch (PayoutApiException $e) {
        assertTrue($e->statusCode === 404 || $e->statusCode === 400, 'status 404 or 400');
    }
});

if ($liveWithdraw && $masterPassword === '') {
    run('live_withdraw_requires_master_password', function (): void {
        throw new RuntimeException(
            'Set PAYCRYPTO_PAYOUT_MASTER_PASSWORD when PAYCRYPTO_PAYOUT_LIVE_WITHDRAW=1',
        );
    });
} elseif ($liveWithdraw) {
    run('createPayout', function () use ($client, $amount, $address, $currency, $network, $masterPassword, &$createdId, &$lastOrderId): void {
        $lastOrderId = 'live_php_'.(string) time();
        $r = $client->createPayout([
            'amount' => $amount,
            'address' => $address,
            'currency' => $currency,
            'network' => $network,
            'order_id' => $lastOrderId,
        ], $masterPassword);
        assertTrue(($r['success'] ?? null) === true, 'success');
        $data = $r['data'] ?? null;
        assertTrue(is_array($data) && isset($data['id']) && is_string($data['id']) && $data['id'] !== '', 'id');
        $createdId = $data['id'];
    });

    run('createPayout_same_order_id_idempotent', function () use ($client, $amount, $address, $currency, $network, $masterPassword, $createdId, $lastOrderId): void {
        $r = $client->createPayout([
            'amount' => $amount,
            'address' => $address,
            'currency' => $currency,
            'network' => $network,
            'order_id' => $lastOrderId,
        ], $masterPassword);
        assertTrue(($r['success'] ?? null) === true, 'success');
        assertTrue(($r['data']['id'] ?? null) === $createdId, 'same id');
    });

    run('info_created', function () use ($client, $createdId, $lastOrderId): void {
        $r = $client->info($createdId);
        assertTrue(($r['success'] ?? null) === true, 'success');
        $data = $r['data'] ?? [];
        assertTrue(($data['id'] ?? null) === $createdId, 'id');
        assertTrue(($data['order_id'] ?? null) === $lastOrderId, 'order_id');
    });

    run('resend_created', function () use ($client, $createdId): void {
        $r = $client->resend($createdId);
        assertTrue(($r['success'] ?? null) === true, 'success');
    });
} else {
    echo "SKIP withdraw block: set PAYCRYPTO_PAYOUT_LIVE_WITHDRAW=1 and PAYCRYPTO_PAYOUT_MASTER_PASSWORD\n";
}

echo json_encode(['passed' => $passed, 'failed' => $failed, 'skipped' => $skipped], JSON_THROW_ON_ERROR)."\n";
if ($failed > 0) {
    exit(1);
}
