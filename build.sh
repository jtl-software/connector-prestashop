#!/usr/bin/env bash
#
# Local one-shot build: installs the production dependencies from composer.lock (never rewrites it), builds the
# release ZIP with phing, then reinstalls the dev dependencies - also when the build fails, which then exits non-zero.

set -euo pipefail

# composer and phing work on the current directory: always build the checkout this script belongs to
cd "$(dirname "${BASH_SOURCE[0]}")"

# phing copies a few thousand files; raise the open-file limit where allowed, but don't fail the build if it isn't
ulimit -n 100000 2>/dev/null || true

trap 'composer install --no-interaction' EXIT

composer install --no-dev --no-interaction
php ./lib/bin/phing release
