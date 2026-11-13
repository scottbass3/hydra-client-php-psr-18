# Template overrides

These templates replace the ones shipped with openapi-generator **7.17.0** (`php` generator, `psr-18` library).
When upgrading the generator (`openapitools.json`), extract the new upstream versions and re-apply the changes below:

```bash
unzip -o openapi-generator-cli-<version>.jar 'php/Configuration.mustache' 'php/libraries/psr-18/*' -d /tmp/upstream
```

## `libraries/psr-18/api.mustache`

- Every status code declared in the spec is returned as its model (`default` included), through one
  `{operationId}HandleResponse()` method shared by the sync and async paths. Declared responses without a
  body (204, 302) return `null`; status and headers stay available through `*WithHttpInfo()`.
- An `ApiException` is only thrown on network errors and on undeclared non-2xx statuses.
- No default plugins: `ErrorPlugin` would turn declared 4xx/5xx into exceptions, and `RedirectPlugin` would
  follow the 302 declared by `oAuth2Authorize`, `performOAuth2DeviceVerificationFlow` and `revokeOidcSession`.
  `HttpException` is still mapped when a caller adds `ErrorPlugin` back.
- Async methods return a `GuzzleHttp\Promise\PromiseInterface`: HTTPlug promises may only carry responses,
  which broke with emulated async clients. The rejection handler no longer requires an `HttpException`.
- Form fields use the spec name (`{{baseName}}`) instead of the PHP variable name (`grant_type`, not `grantType`).
- An empty access token no longer sends `Authorization: Bearer ` (which overwrote basic auth).
- PHPDoc `@return` lists every declared response, with `null` for empty bodies.

## `libraries/psr-18/api_doc.mustache`

- "Return type" lists the model returned for each declared status code.

## `Configuration.mustache`

- Booleans are sent as `true`/`false` in query strings: Hydra compares them to `"true"`.
