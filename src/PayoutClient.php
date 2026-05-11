<?php

declare(strict_types=1);

namespace PayCrypto\Payouts\Client;

use GuzzleHttp\Client;

final class PayoutClient
{
    private PayoutHttpClient $http;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.paycrypto.one/api/v1/',
        ?Client $guzzle = null,
    ) {
        $trimmed = rtrim($baseUrl, '/').'/';
        $client = $guzzle ?? new Client(['base_uri' => $trimmed]);
        $this->http = new PayoutHttpClient($client, trim($apiKey));
    }

    public function services(): array
    {
        return $this->http->postJson('payout/services', []);
    }

    public function calc(array $dto): array
    {
        return $this->http->postJson('payout/calc', $dto);
    }

    public function createPayout(array $dto, ?string $masterPassword = null): array
    {
        $headers = [];
        $mp = $masterPassword !== null && trim($masterPassword) !== ''
            ? trim($masterPassword)
            : (isset($dto['masterPassword']) && is_string($dto['masterPassword']) ? trim($dto['masterPassword']) : '');
        if ($mp !== '') {
            $headers['X-Payout-Master-Password'] = $mp;
        }
        $body = $dto;
        unset($body['masterPassword']);
        return $this->http->postJson('payout', $body, $headers);
    }

    public function info(string $id): array
    {
        return $this->http->postJson('payout/info', ['id' => $id]);
    }

    public function list(array $dto = []): array
    {
        return $this->http->postJson('payout/list', $dto);
    }

    public function resend(string $id): array
    {
        return $this->http->postJson('payout/resend', ['id' => $id]);
    }
}
