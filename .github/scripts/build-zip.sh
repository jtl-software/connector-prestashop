#!/usr/bin/env bash
#
# Build the PrestaShop connector deployment ZIP via phing release.
#
# Usage: build-zip.sh <version>
#
# Assumes the current working directory is the repository root.
# composer.json sets vendor-dir to ./lib/, so phing is at ./lib/bin/phing
# after composer install.

set -euo pipefail

VERSION="${1:?missing version}"

# Container runs as root while the workspace is bind-mounted from a different
# UID on the host runner. Without this, any git-backed composer install
# fails with "fatal: detected dubious ownership" (CVE-2022-24765).
git config --global --add safe.directory '*'

composer install --no-dev --no-progress --no-interaction

php ./lib/bin/phing release -Dversion="$VERSION"
