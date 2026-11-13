<?php

namespace Scottbass3\Hydra\Client\Test\Api;

use Scottbass3\Hydra\Client\Api\JwkApi;
use Scottbass3\Hydra\Client\Model\CreateJsonWebKeySet;
use Scottbass3\Hydra\Client\Model\ErrorOAuth2;
use Scottbass3\Hydra\Client\Model\JsonWebKeySet;

class JwkApiTest extends ApiTestCase
{
    private function api(string $mode): JwkApi
    {
        return new JwkApi(
            httpClient: $this->httpClient,
            config: $this->config,
            httpAsyncClient: $this->asyncClient($mode),
        );
    }

    /**
     * @dataProvider modes
     */
    public function testCreateKeySetPostsTheBodyAndReturnsTheKeySet(string $mode): void
    {
        $this->respond(201, ['keys' => [['kid' => 'k1', 'alg' => 'RS256', 'use' => 'sig', 'kty' => 'RSA']]]);
        $body = (new CreateJsonWebKeySet())->setAlg('RS256')->setKid('k1')->setUse('sig');

        $result = $this->call($this->api($mode), 'createJsonWebKeySet', $mode, 'hydra.openid', $body);

        self::assertInstanceOf(JsonWebKeySet::class, $result);
        self::assertSame('k1', $result->getKeys()[0]->getKid());
        self::assertSame('POST', $this->lastRequest()->getMethod());
        self::assertSame(self::HOST . '/admin/keys/hydra.openid', (string) $this->lastRequest()->getUri());
        self::assertSame(['alg' => 'RS256', 'kid' => 'k1', 'use' => 'sig'], $this->lastRequestJson());
    }

    /**
     * @dataProvider modes
     */
    public function testPathParametersAreEncoded(string $mode): void
    {
        $this->respond(200, ['keys' => []]);

        $this->call($this->api($mode), 'getJsonWebKey', $mode, 'my set', 'a/b');

        self::assertSame(self::HOST . '/admin/keys/my%20set/a%2Fb', (string) $this->lastRequest()->getUri());
    }

    /**
     * @dataProvider modes
     */
    public function testDeleteKeySetReturnsNullOn204(string $mode): void
    {
        $this->respond(204);

        $result = $this->call($this->api($mode), 'deleteJsonWebKeySet', $mode, 'hydra.openid');

        self::assertNull($result);
        self::assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    /**
     * @dataProvider modes
     */
    public function testGetKeySetReturnsTheErrorOnDefaultResponse(string $mode): void
    {
        $this->respond(404, ['error' => 'Not Found']);

        $result = $this->call($this->api($mode), 'getJsonWebKeySet', $mode, 'missing');

        self::assertInstanceOf(ErrorOAuth2::class, $result);
        self::assertSame('Not Found', $result->getError());
    }
}
