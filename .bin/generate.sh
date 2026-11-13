#!/usr/bin/env bash
# Regenerate the PSR-18 PHP client from an Ory Hydra OpenAPI spec.
# Usage: .bin/generate.sh <hydra-version>   (e.g. v26.2.0)
# The generator version is pinned in openapitools.json.
set -euo pipefail

version="${1:?usage: $0 <hydra-version, e.g. v26.2.0>}"
root="$(cd "$(dirname "$0")/.." && pwd)"
cli="@openapitools/openapi-generator-cli@2.25.2"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

curl -fsSL -o "$tmp/spec.json" \
  "https://raw.githubusercontent.com/ory/sdk/master/spec/hydra/${version}.json"
# Ory leaves info.version empty in its specs and fills it at generation time.
jq --arg v "$version" '.info.version = $v' "$tmp/spec.json" > "$tmp/spec.versioned.json"

cd "$root"
npx --yes "$cli" generate \
  -i "$tmp/spec.versioned.json" \
  -g php \
  -t "$root/templates" \
  --global-property apiTests=false,modelTests=false \
  -o "$tmp/out" \
  -c "$root/openapi-config.yaml"

# Generated in a scratch dir so that files removed from the spec disappear; test/ is hand-written.
rm -f "$tmp/out/git_push.sh" "$tmp/out/.travis.yml"
rsync -a --exclude .openapi-generator-ignore --exclude .gitignore "$tmp/out/" "$root/"
for dir in lib docs; do
  rsync -a --delete "$tmp/out/$dir/" "$root/$dir/"
done

jq '.homepage = "https://github.com/scottbass3/hydra-client-php-psr-18"
  | .license = "Apache-2.0"
  | .authors = [
      {"name": "Ory Corp", "homepage": "https://www.ory.sh"},
      {"name": "Scottbass", "email": "scottbass41@gmail.com"}
    ]
  | .config["allow-plugins"]["php-http/discovery"] = true
  | .require["guzzlehttp/promises"] = "^2.0"' \
  "$root/composer.json" > "$tmp/composer.json"
mv "$tmp/composer.json" "$root/composer.json"

sed -i '\#<directory>\./test/Model</directory>#d' "$root/phpunit.xml.dist"

# The generated README assumes a VCS repository, but the package is published on Packagist.
php_min="$(jq -r '.require.php' "$root/composer.json" | sed 's/^\^//')"
PHP_MIN="$php_min" perl -0777 -pe '
  s/^PHP [\d.]+ and later\./PHP $ENV{PHP_MIN} and later./m
    or die "README: PHP requirement line not found\n";
  s/To install the bindings via \[Composer\].*?Then run `composer install`\n/Install the package from [Packagist](https:\/\/packagist.org\/packages\/scottbass3\/hydra-client-php-psr-18):\n\n```bash\ncomposer require scottbass3\/hydra-client-php-psr-18\n```\n/s
    or die "README: Composer installation section not found\n";
  s/^## About this package$/## Updating\n\nTo regenerate this client for a new Hydra version, see [UPDATING.md](UPDATING.md).\n\n## About this package/m
    or die "README: About section not found\n";
' "$root/README.md" > "$tmp/README.md"
mv "$tmp/README.md" "$root/README.md"

echo "Generated client for Hydra ${version}. Run 'composer update' and the tests next."
