<?php

namespace Scottbass3\Hydra\Client\Test\Api;

use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\Plugin\RedirectPlugin;
use Scottbass3\Hydra\Client\Api\OAuth2Api;
use Scottbass3\Hydra\Client\Model\AcceptOAuth2LoginRequest;
use Scottbass3\Hydra\Client\Model\ErrorOAuth2;
use Scottbass3\Hydra\Client\Model\GenericError;
use Scottbass3\Hydra\Client\Model\IntrospectedOAuth2Token;
use Scottbass3\Hydra\Client\Model\JsonPatch;
use Scottbass3\Hydra\Client\Model\OAuth2Client;
use Scottbass3\Hydra\Client\Model\OAuth2LoginRequest;
use Scottbass3\Hydra\Client\Model\OAuth2RedirectTo;
use Scottbass3\Hydra\Client\Model\OAuth2TokenExchange;

class OAuth2ApiTest extends ApiTestCase
{
    private function api(string $mode, ?array $plugins = null): OAuth2Api
    {
        return new OAuth2Api(
            httpClient: $this->httpClient,
            config: $this->config,
            httpAsyncClient: $this->asyncClient($mode),
            plugins: $plugins,
        );
    }

    /**
     * @dataProvider modes
     */
    public function testLoginRequestIsBuiltFromTheChallenge(string $mode): void
    {
        $this->respond(200, ['challenge' => 'abc', 'subject' => 'alice']);

        $this->call($this->api($mode), 'getOAuth2LoginRequest', $mode, 'abc');

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            self::HOST . '/admin/oauth2/auth/requests/login?login_challenge=abc',
            (string) $request->getUri()
        );
    }

    /**
     * @dataProvider modes
     */
    public function testLoginRequestReturnsTheLoginRequestOn200(string $mode): void
    {
        $this->respond(200, ['challenge' => 'abc', 'subject' => 'alice', 'skip' => true]);

        $result = $this->call($this->api($mode), 'getOAuth2LoginRequest', $mode, 'abc');

        self::assertInstanceOf(OAuth2LoginRequest::class, $result);
        self::assertSame('alice', $result->getSubject());
        self::assertTrue($result->getSkip());
    }

    /**
     * @dataProvider modes
     */
    public function testLoginRequestReturnsTheRedirectOn410(string $mode): void
    {
        $this->respond(410, ['redirect_to' => 'https://app.test/callback']);

        $result = $this->call($this->api($mode), 'getOAuth2LoginRequest', $mode, 'abc');

        self::assertInstanceOf(OAuth2RedirectTo::class, $result);
        self::assertSame('https://app.test/callback', $result->getRedirectTo());
    }

    /**
     * @dataProvider modes
     */
    public function testLoginRequestReturnsTheErrorOnDefaultResponse(string $mode): void
    {
        $this->respond(404, ['error' => 'Not Found', 'error_description' => 'Unknown challenge']);

        $result = $this->call($this->api($mode), 'getOAuth2LoginRequest', $mode, 'abc');

        self::assertInstanceOf(ErrorOAuth2::class, $result);
        self::assertSame('Not Found', $result->getError());
        self::assertSame('Unknown challenge', $result->getErrorDescription());
    }

    /**
     * @dataProvider modes
     */
    public function testDeclaredResponsesAreReturnedEvenWithErrorPlugin(string $mode): void
    {
        $this->respond(410, ['redirect_to' => 'https://app.test/callback']);
        $api = $this->api($mode, [new RedirectPlugin(['strict' => true]), new ErrorPlugin()]);

        $result = $this->call($api, 'getOAuth2LoginRequest', $mode, 'abc');

        self::assertInstanceOf(OAuth2RedirectTo::class, $result);
    }

    /**
     * @dataProvider modes
     */
    public function testEmptySuccessResponseReturnsNull(string $mode): void
    {
        $this->respond(204);

        $result = $this->call($this->api($mode), 'deleteOAuth2Client', $mode, 'client-id');

        self::assertNull($result);
        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(self::HOST . '/admin/clients/client-id', (string) $request->getUri());
    }

    /**
     * @dataProvider modes
     */
    public function testEmptySuccessOperationReturnsTheErrorOnDefaultResponse(string $mode): void
    {
        $this->respond(404, ['message' => 'Unable to locate the resource']);

        $result = $this->call($this->api($mode), 'deleteOAuth2Client', $mode, 'client-id');

        self::assertInstanceOf(GenericError::class, $result);
        self::assertSame('Unable to locate the resource', $result->getMessage());
    }

    /**
     * @dataProvider modes
     */
    public function testAcceptLoginRequestSendsTheBodyAsJson(string $mode): void
    {
        $this->respond(200, ['redirect_to' => 'https://hydra.test/oauth2/auth?login_verifier=v']);
        $body = (new AcceptOAuth2LoginRequest())->setSubject('alice')->setRemember(true)->setRememberFor(3600);

        $result = $this->call($this->api($mode), 'acceptOAuth2LoginRequest', $mode, 'abc', $body);

        self::assertInstanceOf(OAuth2RedirectTo::class, $result);
        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame(
            self::HOST . '/admin/oauth2/auth/requests/login/accept?login_challenge=abc',
            (string) $request->getUri()
        );
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            ['remember' => true, 'remember_for' => 3600, 'subject' => 'alice'],
            json_decode((string) $request->getBody(), true)
        );
    }

    /**
     * @dataProvider modes
     */
    public function testWithHttpInfoExposesStatusAndHeaders(string $mode): void
    {
        $this->respond(410, ['redirect_to' => 'https://app.test/callback']);

        [$model, $status, $headers] = $this->call($this->api($mode), 'getOAuth2LoginRequestWithHttpInfo', $mode, 'abc');

        self::assertInstanceOf(OAuth2RedirectTo::class, $model);
        self::assertSame(410, $status);
        self::assertSame(['application/json'], $headers['Content-Type']);
    }

    /**
     * @dataProvider modes
     */
    public function testTokenExchangeSendsAFormWithBasicAuth(string $mode): void
    {
        $this->config->setUsername('my-client')->setPassword('s3cret');
        $this->respond(200, ['access_token' => 'at', 'token_type' => 'bearer', 'expires_in' => 3600]);

        $result = $this->call(
            $this->api($mode),
            'oauth2TokenExchange',
            $mode,
            'authorization_code',
            'my-client',
            'the-code',
            'https://app.test/callback'
        );

        self::assertInstanceOf(OAuth2TokenExchange::class, $result);
        self::assertSame('at', $result->getAccessToken());
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::HOST . '/oauth2/token', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('Basic ' . base64_encode('my-client:s3cret'), $request->getHeaderLine('Authorization'));
        self::assertSame([
            'client_id' => 'my-client',
            'code' => 'the-code',
            'grant_type' => 'authorization_code',
            'redirect_uri' => 'https://app.test/callback',
        ], $this->lastRequestForm());
    }

    /**
     * Endpoints accepting basic or oauth2 send the access token when both are configured.
     */
    public function testAccessTokenTakesPrecedenceOverBasicAuth(): void
    {
        $this->config->setUsername('my-client')->setPassword('s3cret')->setAccessToken('at');
        $this->respond(200);

        $this->api('sync')->revokeOAuth2Token('token-to-revoke');

        self::assertSame('Bearer at', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    /**
     * @dataProvider modes
     */
    public function testIntrospectSendsAForm(string $mode): void
    {
        $this->respond(200, ['active' => true, 'sub' => 'alice', 'client_id' => 'my-client']);

        $result = $this->call($this->api($mode), 'introspectOAuth2Token', $mode, 'the-token', 'openid offline');

        self::assertInstanceOf(IntrospectedOAuth2Token::class, $result);
        self::assertTrue($result->getActive());
        self::assertSame('alice', $result->getSub());
        self::assertSame(self::HOST . '/admin/oauth2/introspect', (string) $this->lastRequest()->getUri());
        self::assertSame(['scope' => 'openid offline', 'token' => 'the-token'], $this->lastRequestForm());
    }

    /**
     * @dataProvider modes
     */
    public function testListClientsSendsPaginationAndReturnsClients(string $mode): void
    {
        $link = '<' . self::HOST . '/admin/clients?page_size=2&page_token=next>; rel="next"';
        $this->respond(200, [['client_id' => 'a'], ['client_id' => 'b']], ['Link' => $link]);

        [$clients, $status, $headers] = $this->call(
            $this->api($mode),
            'listOAuth2ClientsWithHttpInfo',
            $mode,
            2,
            'current',
            'My app'
        );

        self::assertSame(200, $status);
        self::assertContainsOnlyInstancesOf(OAuth2Client::class, $clients);
        self::assertSame(['a', 'b'], array_map(fn (OAuth2Client $c) => $c->getClientId(), $clients));
        self::assertSame([$link], $headers['Link']);
        self::assertSame(
            self::HOST . '/admin/clients?page_size=2&page_token=current&client_name=My%20app',
            (string) $this->lastRequest()->getUri()
        );
    }

    /**
     * @dataProvider modes
     */
    public function testBooleanQueryParameterIsSentAsText(string $mode): void
    {
        $this->respond(204);

        $result = $this->call($this->api($mode), 'revokeOAuth2ConsentSessions', $mode, 'alice', null, null, true);

        self::assertNull($result);
        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertSame(
            self::HOST . '/admin/oauth2/auth/sessions/consent?subject=alice&all=true',
            (string) $this->lastRequest()->getUri()
        );
    }

    /**
     * @dataProvider modes
     */
    public function testPatchClientSendsTheJsonPatch(string $mode): void
    {
        $this->respond(200, ['client_id' => 'abc', 'client_name' => 'Renamed']);
        $patch = [(new JsonPatch())->setOp('replace')->setPath('/client_name')->setValue('Renamed')];

        $result = $this->call($this->api($mode), 'patchOAuth2Client', $mode, 'abc', $patch);

        self::assertInstanceOf(OAuth2Client::class, $result);
        self::assertSame('PATCH', $this->lastRequest()->getMethod());
        self::assertSame(
            [['op' => 'replace', 'path' => '/client_name', 'value' => 'Renamed']],
            $this->lastRequestJson()
        );
    }

    /**
     * @dataProvider modes
     */
    public function testAuthorizeReturnsTheDeclaredRedirect(string $mode): void
    {
        $this->respond(302, null, ['Location' => 'https://login.test/?login_challenge=abc']);

        [$result, $status, $headers] = $this->call($this->api($mode), 'oAuth2AuthorizeWithHttpInfo', $mode);

        self::assertNull($result);
        self::assertSame(302, $status);
        self::assertSame(['https://login.test/?login_challenge=abc'], $headers['Location']);
        self::assertCount(1, $this->history);
    }
}
