<?php

namespace Scottbass3\Hydra\Client\Test\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Http\Client\Common\EmulatedHttpAsyncClient;
use Http\Client\HttpAsyncClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Scottbass3\Hydra\Client\Configuration;

abstract class ApiTestCase extends TestCase
{
    protected const HOST = 'http://hydra.test';

    protected MockHandler $handler;

    protected GuzzleAdapter $httpClient;

    protected Configuration $config;

    protected array $history = [];

    protected function setUp(): void
    {
        $this->handler = new MockHandler();
        $stack = HandlerStack::create($this->handler);
        $stack->push(Middleware::history($this->history));
        $this->httpClient = new GuzzleAdapter(new GuzzleClient([
            'handler' => $stack,
            'http_errors' => false,
            'allow_redirects' => false,
        ]));
        $this->config = (new Configuration())->setHost(self::HOST);
    }

    public static function modes(): array
    {
        return [
            'sync' => ['sync'],
            'async' => ['async'],
            'async emulated' => ['async-emulated'],
        ];
    }

    /**
     * "async-emulated" wraps the sync client, whose promises only accept responses.
     */
    protected function asyncClient(string $mode): HttpAsyncClient
    {
        return $mode === 'async-emulated'
            ? new EmulatedHttpAsyncClient($this->httpClient)
            : $this->httpClient;
    }

    protected function respond(int $status, ?array $json = null, array $headers = []): void
    {
        $this->handler->append(new Response(
            $status,
            ['Content-Type' => 'application/json'] + $headers,
            $json === null ? '' : json_encode($json)
        ));
    }

    protected function lastRequestForm(): array
    {
        parse_str((string) $this->lastRequest()->getBody(), $form);

        return $form;
    }

    protected function lastRequestJson(): mixed
    {
        return json_decode((string) $this->lastRequest()->getBody(), true);
    }

    protected function lastRequest(): RequestInterface
    {
        return end($this->history)['request'];
    }

    /**
     * Calls the operation synchronously, or through its Async variant and waits for the promise.
     */
    protected function call(object $api, string $operation, string $mode, ...$args)
    {
        if ($mode !== 'sync') {
            $method = str_ends_with($operation, 'WithHttpInfo')
                ? substr($operation, 0, -strlen('WithHttpInfo')) . 'AsyncWithHttpInfo'
                : $operation . 'Async';

            return $api->{$method}(...$args)->wait();
        }

        return $api->{$operation}(...$args);
    }
}
