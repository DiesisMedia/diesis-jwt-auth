#!/usr/bin/env bash

# Runs Strauss (https://github.com/BrianHenryIE/strauss) from a pinned phar.
# Composer calls this after install and update so vendor-prefixed/ always
# matches composer.json's extra.strauss configuration. The phar is downloaded
# once and verified against the checksum below; bump both together.

set -euo pipefail

version=0.29.1
sha256=2141bf2cf5179bfceabf14dd647920abdfc7c3f1dfc702534d87e5994e5a3b48

bin_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
phar="$bin_dir/strauss.phar"

verify() {
  echo "$sha256  $phar" | shasum -a 256 -c - >/dev/null 2>&1
}

if [[ ! -f "$phar" ]] || ! verify; then
  curl -fsSL -o "$phar" "https://github.com/BrianHenryIE/strauss/releases/download/$version/strauss.phar"
  verify || { echo "strauss.phar checksum mismatch" >&2; rm -f "$phar"; exit 1; }
fi

exec php "$phar" "$@"
