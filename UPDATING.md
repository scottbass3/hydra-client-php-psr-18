# Updating this client

This package is generated from the Ory Hydra OpenAPI spec with openapi-generator (`php` generator, `psr-18` library),
plus a few template overrides. It is **not** synced with the upstream `ory/hydra-client-php` repository:
merging its `autogen:` commits would replace this client with Ory's Guzzle client.

## Requirements

PHP 8.1+, Composer, Node.js (`npx`), Java, `curl`, `jq`, `rsync` and `perl`.

## What is generated and what is not

| Path | Origin |
|---|---|
| `lib/`, `docs/`, `README.md`, `composer.json`, `phpunit.xml.dist`, `.openapi-generator/` | Generated: do not edit by hand, changes are lost on the next run |
| `openapi-config.yaml` | Generator options (namespace, package name, `psr-18` library, Ory's options) |
| `openapitools.json` | Pinned generator version |
| `templates/` | Overrides of the generator templates, see [templates/README.md](templates/README.md) |
| `test/` | Hand-written tests |
| `.gitignore` | Maintained by hand (the generator's copy is skipped) |
| `.bin/generate.sh` | Generation script; also rewrites the parts of `composer.json` and `README.md` listed in it |

## Update to a new Hydra version

1. Find the available spec versions:

   ```bash
   gh api repos/ory/sdk/contents/spec/hydra --jq '.[].name' | sort -V
   ```

2. Regenerate the client, for example for `v26.2.0`:

   ```bash
   .bin/generate.sh v26.2.0
   ```

3. Update the dependencies and run the tests:

   ```bash
   composer update
   ```

   ```bash
   vendor/bin/phpunit
   ```

4. Review what changed in the API. Removed or renamed operations, parameters or models are breaking changes:

   ```bash
   git diff --stat -- lib/Api lib/Model
   ```

   ```bash
   git diff -- docs/Api
   ```

5. Commit, then tag and push. Packagist publishes the tag through the GitHub webhook.
   Use a major version when the diff contains a breaking change, a minor version otherwise:

   ```bash
   git tag v2.1.0
   ```

   ```bash
   git push origin master v2.1.0
   ```

6. On a major version, update the `scottbass3/hydra-client-php-psr-18` constraint in `scottbass3/ory-hydra-bundle`.

## Update the generator

1. Pin the new version (this rewrites `openapitools.json`):

   ```bash
   npx @openapitools/openapi-generator-cli@2.25.2 version-manager set 7.18.0
   ```

2. Compare the new upstream templates with the previous ones and re-apply the changes listed in
   [templates/README.md](templates/README.md) to the files in `templates/`. Update the version mentioned there.

3. Regenerate the current Hydra version and run the tests, as in steps 2 to 4 above.
   The tests cover the behaviour added by the template overrides; a failure usually means an override was not carried over.
