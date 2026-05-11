<?php

declare(strict_types=1);

namespace PayCrypto\Payouts\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PayCrypto\Payouts\Client\PayoutApiException;
use PayCrypto\Payouts\Client\PayoutClient;
use PayCrypto\Payouts\Client\PayoutHttpClient;
use PHPUnit\Framework\TestCase;

final class PayoutClientTest extends TestCase
{
    private function clientWithMock(MockHandler $mock): PayoutClient
    {
        $stack = HandlerStack::create($mock);
        $guzzle = new Client(['handler' => $stack, 'base_uri' => 'http://test.local/api/v1/']);

        return new PayoutClient('sk_test_key', 'http://test.local/api/v1/', $guzzle);
    }

    public function testServices_postsJsonAndReturnsEnvelope(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => ['services' => []]], JSON_THROW_ON_ERROR)),
        ]);
        $client = $this->clientWithMock($mock);
        $r = $client->services();
        $this->assertTrue($r['success']);
        $this->assertSame([], $r['data']['services']);
        $req = $mock->getLastRequest();
        $this->assertNotNull($req);
        $this->assertSame('POST', $req->getMethod());
        $this->assertStringEndsWith('/payout/services', (string) $req->getUri());
    }

    public function testCalc_sendsDtoInBody(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => ['ok' => true]], JSON_THROW_ON_ERROR)),
        ]);
        $client = $this->clientWithMock($mock);
        $client->calc([
            'amount' => '10',
            'address' => 'T9',
            'currency' => 'USDT',
            'network' => 'TRON',
            'priority' => 'high',
        ]);
        $req = $mock->getLastRequest();
        $this->assertNotNull($req);
        $this->assertStringEndsWith('/payout/calc', (string) $req->getUri());
        $body = json_decode((string) $req->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('high', $body['priority']);
        $this->assertSame('Bearer sk_test_key', $req->getHeaderLine('Authorization'));
    }

    public function testCreatePayout_setsMasterPasswordHeaderAndStripsFromJson(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => ['id' => 'new-id']], JSON_THROW_ON_ERROR)),
        ]);
        $client = $this->clientWithMock($mock);
        $client->createPayout([
            'amount' => '1',
            'address' => 'T1',
            'currency' => 'USDT',
            'network' => 'TRON',
            'order_id' => 'ord_1',
            'masterPassword' => 'should-not-appear',
        ], 'header-mp');
        $req = $mock->getLastRequest();
        $this->assertNotNull($req);
        $this->assertSame('header-mp', $req->getHeaderLine('X-Payout-Master-Password'));
        $this->assertSame('', $req->getHeaderLine('Idempotency-Key'));
        $body = json_decode((string) $req->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('masterPassword', $body);
        $this->assertSame('ord_1', $body['order_id']);
    }

    public function testCreatePayout_prefersSecondArgOverBodyMasterPassword(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => []], JSON_THROW_ON_ERROR)),
        ]);
        $client = $this->clientWithMock($mock);
        $client->createPayout([
            'amount' => '1',
            'address' => 'T1',
            'currency' => 'USDT',
            'network' => 'TRON',
            'order_id' => 'o',
            'masterPassword' => 'from-body',
        ], 'from-arg');
        $req = $mock->getLastRequest();
        $this->assertNotNull($req);
        $this->assertSame('from-arg', $req->getHeaderLine('X-Payout-Master-Password'));
    }

    public function testInfoAndResend_postExpectedPaths(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => []], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['success' => true, 'data' => ['ok' => true]], JSON_THROW_ON_ERROR)),
        ]);
        $client = $this->clientWithMock($mock);
        $client->info('uuid-here');
        $req1 = $mock->getLastRequest();
        $this->assertNotNull($req1);
        $this->assertStringEndsWith('/payout/info', (string) $req1->getUri());
        $this->assertSame(
            ['id' => 'uuid-here'],
            json_decode((string) $req1->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        $client->resend('uuid-here');
        $req2 = $mock->getLastRequest();
        $this->assertNotNull($req2);
        $this->assertStringEndsWith('/payout/resend', (string) $req2->getUri());
        $this->assertSame(
            ['id' => 'uuid-here'],
            json_decode((string) $req2->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testList_postsFilters(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => []], JSON_THROW_ON_ERROR)),
        ]);
        $client = $this->clientWithMock($mock);
        $client->list([
            'date_from' => '2026-01-01 00:00:00',
            'cursor' => 'c1',
        ]);
        $req = $mock->getLastRequest();
        $this->assertNotNull($req);
        $body = json_decode((string) $req->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026-01-01 00:00:00', $body['date_from']);
        $this->assertSame('c1', $body['cursor']);
    }

    public function testPayoutHttpClient_throwsPayoutApiExceptionOnErrorEnvelope(): void
    {
        $mock = new MockHandler([
            new Response(401, [], json_encode(['success' => false, 'data' => ['message' => 'Invalid']], JSON_THROW_ON_ERROR)),
        ]);
        $stack = HandlerStack::create($mock);
        $guzzle = new Client(['handler' => $stack, 'base_uri' => 'http://test.local/api/v1/']);
        $http = new PayoutHttpClient($guzzle, 'bad');
        $this->expectException(PayoutApiException::class);
        $this->expectExceptionMessage('Invalid');
        $http->postJson('payout/services', []);
    }
}
