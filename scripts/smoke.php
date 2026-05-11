<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PayCrypto\Payouts\Client\Env;
use PayCrypto\Payouts\Client\PayoutClient;

$baseUrl = Env::get('PAYCRYPTO_PAYOUT_BASE_URL', 'https://api.paycrypto.one/api/v1/');
$apiKey = Env::get('PAYCRYPTO_PAYOUT_API_KEY');
$client = new PayoutClient($apiKey, $baseUrl);
$r = $client->services();
if (($r['success'] ?? null) !== true) {
    fwrite(STDERR, "services failed\n");
    exit(1);
}
echo json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n";
