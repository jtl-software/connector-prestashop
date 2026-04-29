#!/usr/bin/env bash
#
# Set up the CI environment for the check workflow: install project and
# tooling packages.
#
# Assumes the current working directory is the repository root.
# All composer dependencies in this repo are public on Packagist, so no
# composer-auth setup is needed.

set -euo pipefail

# Container runs as root while the workspace is bind-mounted from a different
# UID on the host runner. Without this, any git-backed composer install
# fails with "fatal: detected dubious ownership" (CVE-2022-24765).
git config --global --add safe.directory '*'

composer install --no-progress --no-interaction
composer global require "staabm/annotate-pull-request-from-checkstyle:^1.8"
