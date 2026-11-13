<?php

namespace Scottbass3\Hydra\Client\Test\Api;

use Scottbass3\Hydra\Client\Api\OidcApi;
use Scottbass3\Hydra\Client\Model\ErrorOAuth2;
use Scottbass3\Hydra\Client\Model\OAuth2Client;
use Scottbass3\Hydra\Client\Model\OidcConfiguration;
use Scottbass3\Hydra\Client\Model\OidcUserInfo;

class OidcApiTest extends ApiTestCase
{
    private function api(string $mode): OidcApi
    {
        return new OidcApi(
            httpClient: $this->httpClient,
            config: $this->config,
            httpAsyncClient: $this->asyncClient($mode),
        );
    }

    /**
     * @dataProvider modes
     */
    public function testDiscoverConfigurationReturnsTheConfiguration(string $mode): void
    {
        $this->respond(200, ['issuer' => 'https://hydra.test/', 'authorization_endpoint' => 'https://hydra.test/oauth2/auth']);

        $result = $this->call($this->api($mode), 'discoverOidcConfiguration', $mode);

        self::assertInstanceOf(OidcConfiguration::class, $result);
        self::assertSame('https://hydra.test/', $result->getIssuer());
        self::assertSame(self::HOST . '/.well-known/openid-configuration', (string) $this->lastRequest()->getUri());
    }

    /**
     * @dataProvider modes
     */
    public function testUserInfoSendsTheAccessTokenAsBearer(string $mode): void
    {
        $this->config->setAccessToken('user-token');
        $this->respond(200, ['sub' => 'alice']);

        $result = $this->call($this->api($mode), 'getOidcUserInfo', $mode);

        self::assertInstanceOf(OidcUserInfo::class, $result);
        self::assertSame('alice', $result->getSub());
        self::assertSame('Bearer user-token', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    /**
     * @dataProvider modes
     */
    public function testUserInfoReturnsTheErrorOnDefaultResponse(string $mode): void
    {
        $this->respond(401, ['error' => 'request_unauthorized']);

        $result = $this->call($this->api($mode), 'getOidcUserInfo', $mode);

        self::assertInstanceOf(ErrorOAuth2::class, $result);
        self::assertSame('request_unauthorized', $result->getError());
    }

    /**
     * @dataProvider modes
     */
    public function testDynamicClientUsesTheRegistrationTokenAsBearer(string $mode): void
    {
        $this->config->setAccessToken('registration-token');
        $this->respond(200, ['client_id' => 'abc', 'client_name' => 'My app']);

        $result = $this->call($this->api($mode), 'getOidcDynamicClient', $mode, 'abc');

        self::assertInstanceOf(OAuth2Client::class, $result);
        self::assertSame('My app', $result->getClientName());
        self::assertSame(self::HOST . '/oauth2/register/abc', (string) $this->lastRequest()->getUri());
        self::assertSame('Bearer registration-token', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    /**
     * @dataProvider modes
     */
    public function testCreateDynamicClientReturnsTheClientOn201(string $mode): void
    {
        $this->respond(201, ['client_id' => 'new', 'client_name' => 'My app']);

        $result = $this->call(
            $this->api($mode),
            'createOidcDynamicClient',
            $mode,
            (new OAuth2Client())->setClientName('My app')
        );

        self::assertInstanceOf(OAuth2Client::class, $result);
        self::assertSame('new', $result->getClientId());
        self::assertSame('POST', $this->lastRequest()->getMethod());
        self::assertSame('My app', $this->lastRequestJson()['client_name']);
    }

    /**
     * @dataProvider modes
     */
    public function testRevokeSessionReturnsTheDeclaredRedirect(string $mode): void
    {
        $this->respond(302, null, ['Location' => 'https://app.test/logged-out']);

        [$result, $status, $headers] = $this->call($this->api($mode), 'revokeOidcSessionWithHttpInfo', $mode);

        self::assertNull($result);
        self::assertSame(302, $status);
        self::assertSame(['https://app.test/logged-out'], $headers['Location']);
    }
}
