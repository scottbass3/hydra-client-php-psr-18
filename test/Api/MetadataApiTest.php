<?php

namespace Scottbass3\Hydra\Client\Test\Api;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\NetworkException;
use Scottbass3\Hydra\Client\Api\MetadataApi;
use Scottbass3\Hydra\Client\ApiException;
use Scottbass3\Hydra\Client\Model\GetVersion200Response;
use Scottbass3\Hydra\Client\Model\IsReady503Response;

class MetadataApiTest extends ApiTestCase
{
    private function api(string $mode): MetadataApi
    {
        return new MetadataApi(
            httpClient: $this->httpClient,
            config: $this->config,
            httpAsyncClient: $this->asyncClient($mode),
        );
    }

    /**
     * @dataProvider modes
     */
    public function testReturnsTheVersionOn200(string $mode): void
    {
        $this->respond(200, ['version' => 'v26.2.0']);

        $result = $this->call($this->api($mode), 'getVersion', $mode);

        self::assertInstanceOf(GetVersion200Response::class, $result);
        self::assertSame('v26.2.0', $result->getVersion());
    }

    /**
     * @dataProvider modes
     */
    public function testReadinessReturnsTheDeclared503(string $mode): void
    {
        $this->respond(503, ['errors' => ['database' => 'unreachable']]);

        $result = $this->call($this->api($mode), 'isReady', $mode);

        self::assertInstanceOf(IsReady503Response::class, $result);
        self::assertSame(['database' => 'unreachable'], $result->getErrors());
        self::assertSame(self::HOST . '/health/ready', (string) $this->lastRequest()->getUri());
    }

    /**
     * @dataProvider modes
     */
    public function testUndeclaredStatusThrowsApiException(string $mode): void
    {
        $this->respond(500, ['message' => 'boom']);

        try {
            $this->call($this->api($mode), 'getVersion', $mode);
            self::fail('ApiException expected');
        } catch (ApiException $e) {
            self::assertSame(500, $e->getCode());
            self::assertSame('{"message":"boom"}', $e->getResponseBody());
        }
    }

    /**
     * @dataProvider modes
     */
    public function testNetworkErrorThrowsApiException(string $mode): void
    {
        $this->handler->append(new ConnectException('Connection refused', new Request('GET', self::HOST . '/version')));

        try {
            $this->call($this->api($mode), 'getVersion', $mode);
            self::fail('ApiException expected');
        } catch (ApiException $e) {
            self::assertSame(0, $e->getCode());
            self::assertNull($e->getResponseBody());
            self::assertInstanceOf(NetworkException::class, $e->getPrevious());
            self::assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    /**
     * @dataProvider modes
     */
    public function testInvalidJsonThrowsApiException(string $mode): void
    {
        $this->handler->append(new Response(200, ['Content-Type' => 'application/json'], 'not json'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Error JSON decoding server response');

        $this->call($this->api($mode), 'getVersion', $mode);
    }
}
