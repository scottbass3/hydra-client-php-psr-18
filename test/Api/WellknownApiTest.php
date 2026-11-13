<?php

namespace Scottbass3\Hydra\Client\Test\Api;

use Scottbass3\Hydra\Client\Api\WellknownApi;
use Scottbass3\Hydra\Client\Model\ErrorOAuth2;
use Scottbass3\Hydra\Client\Model\JsonWebKeySet;

class WellknownApiTest extends ApiTestCase
{
    private function api(string $mode): WellknownApi
    {
        return new WellknownApi(
            httpClient: $this->httpClient,
            config: $this->config,
            httpAsyncClient: $this->asyncClient($mode),
        );
    }

    /**
     * @dataProvider modes
     */
    public function testDiscoverJsonWebKeysReturnsTheKeys(string $mode): void
    {
        $this->respond(200, ['keys' => [
            ['kid' => 'k1', 'alg' => 'RS256', 'use' => 'sig', 'kty' => 'RSA'],
            ['kid' => 'k2', 'alg' => 'ES256', 'use' => 'sig', 'kty' => 'EC'],
        ]]);

        $result = $this->call($this->api($mode), 'discoverJsonWebKeys', $mode);

        self::assertInstanceOf(JsonWebKeySet::class, $result);
        self::assertSame(['k1', 'k2'], array_map(fn ($key) => $key->getKid(), $result->getKeys()));
        self::assertSame('ES256', $result->getKeys()[1]->getAlg());
        self::assertSame(self::HOST . '/.well-known/jwks.json', (string) $this->lastRequest()->getUri());
    }

    /**
     * @dataProvider modes
     */
    public function testDiscoverJsonWebKeysReturnsTheErrorOnDefaultResponse(string $mode): void
    {
        $this->respond(500, ['error' => 'server_error']);

        $result = $this->call($this->api($mode), 'discoverJsonWebKeys', $mode);

        self::assertInstanceOf(ErrorOAuth2::class, $result);
        self::assertSame('server_error', $result->getError());
    }
}
