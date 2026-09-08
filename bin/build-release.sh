#!/usr/bin/env bash

set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
version=${1:-}

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: $0 <major.minor.patch>" >&2
  exit 1
fi

plugin_version=$(sed -n 's/^ \* Version: //p' "$repo_root/diesis-wp-jwt-auth.php")

if [[ "$plugin_version" != "$version" ]]; then
  echo "Plugin version $plugin_version does not match requested version $version." >&2
  exit 1
fi

temporary_root=$(mktemp -d)
package_root="$temporary_root/diesis-wp-jwt-auth"
artifact="$repo_root/dist/diesis-wp-jwt-auth-$version.zip"

mkdir -p "$package_root" "$repo_root/dist"

rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.gitignore' \
  --exclude '.phpunit.cache' \
  --exclude 'bin/build-release.sh' \
  --exclude 'dist' \
  --exclude 'phpcs.xml.dist' \
  --exclude 'phpstan.neon' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'tests' \
  --exclude 'vendor' \
  --exclude 'vendor-prefixed' \
  "$repo_root/" "$package_root/"

# Composer's post-install hook runs bin/strauss.sh, which prefixes
# firebase/php-jwt into vendor-prefixed/ and removes the unprefixed copy.
composer install \
  --working-dir="$package_root" \
  --no-dev \
  --no-interaction \
  --no-progress

composer dump-autoload \
  --working-dir="$package_root" \
  --no-dev \
  --classmap-authoritative

# Build tooling and the development-only alias map do not ship.
rm -rf "$package_root/bin"
rm -f "$package_root/vendor/composer/autoload_aliases.php"

if grep -rq "^namespace Firebase" "$package_root/vendor" "$package_root/vendor-prefixed"; then
  echo "Unprefixed Firebase\\JWT namespace found in the package." >&2
  exit 1
fi

# zip appends to an existing archive, so start from a clean file.
rm -f "$artifact"

(
  cd "$temporary_root"
  zip -qr "$artifact" diesis-wp-jwt-auth
)

echo "$artifact"
