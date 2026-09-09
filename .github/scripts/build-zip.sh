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

# phing reads build-config.yaml via a property file loader that pulls in
# symfony/yaml — but symfony/yaml is composer-"replace"d (provided by the
# PrestaShop 9 host at runtime, not vendored), so it won't exist in lib/ once
# --no-dev deps are installed. Convert to .properties for phing instead.
sed 's/: /=/' build-config.yaml > build-config.properties

composer install --no-dev --no-progress --no-interaction

php ./lib/bin/phing release -Dversion="$VERSION"

rm -f build-config.properties
