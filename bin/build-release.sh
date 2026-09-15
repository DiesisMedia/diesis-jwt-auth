#!/usr/bin/env bash

set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
version=${1:-}

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: $0 <major.minor.patch>" >&2
  exit 1
fi

plugin_version=$(sed -n 's/^ \* Version: //p' "$repo_root/diesis-jwt-auth.php")

if [[ "$plugin_version" != "$version" ]]; then
  echo "Plugin version $plugin_version does not match requested version $version." >&2
  exit 1
fi

temporary_root=$(mktemp -d)
package_root="$temporary_root/diesis-jwt-auth"
artifact="$repo_root/dist/diesis-jwt-auth-$version.zip"

mkdir -p "$package_root" "$repo_root/dist"

# Only files git tracks go in, so local scratch files never ship. Agent docs
# and development tooling are tracked but stay out of the package.
git -C "$repo_root" ls-files -z -- . \
  ':!.github' \
  ':!.gitignore' \
  ':!.wordpress-org' \
  ':!AGENTS.md' \
  ':!bin/build-release.sh' \
  ':!docs' \
  ':!phpcs.xml.dist' \
  ':!phpstan.neon' \
  ':!phpunit.xml.dist' \
  ':!tests' \
  | rsync -a --files-from=- --from0 "$repo_root/" "$package_root/"

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
  zip -qr "$artifact" diesis-jwt-auth
)

echo "$artifact"
