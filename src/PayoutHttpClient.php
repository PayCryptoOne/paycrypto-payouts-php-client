<?php

declare(strict_types=1);

namespace PayCrypto\Payouts\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

final class PayoutHttpClient
{
    public function __construct(
        private readonly Client $client,
        private readonly string $apiKey,
    ) {
    }

    public function postJson(string $uri, array $body, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $extraHeaders);
        try {
            $response = $this->client->post($uri, [
                'json' => $body,
                'headers' => $headers,
            ]);
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
            if ($response === null) {
                throw $exception;
            }
        }
        $text = (string) $response->getBody();
        $parsed = $text !== '' ? json_decode($text, true, 512, JSON_THROW_ON_ERROR) : null;
        if (!is_array($parsed)) {
            throw new PayoutApiException('Invalid JSON response', $response->getStatusCode(), $text);
        }
        $success = $parsed['success'] ?? null;
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300 || $success !== true) {
            $msg = 'HTTP '.$status;
            if (isset($parsed['data']) && is_array($parsed['data']) && isset($parsed['data']['message']) && is_string($parsed['data']['message'])) {
                $msg = $parsed['data']['message'];
            }
            throw new PayoutApiException($msg, $status, $parsed);
        }
        return $parsed;
    }
}
