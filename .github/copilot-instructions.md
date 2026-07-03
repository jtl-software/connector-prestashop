## What this project is

A PrestaShop module (`jtlconnector`) that synchronises shop data with JTL-Wawi ERP via the `jtl/connector` core framework. Distributed as a ZIP for manual install in PrestaShop 1.7+.

**Critical detail:** Composer's `vendor-dir` is `./lib/`, not `./vendor/`. Anything that normally lives at `vendor/bin/<tool>` is at `lib/bin/<tool>` here — most importantly `phing` (`lib/bin/phing`).

## Commands

```bash
# Install dependencies (no GitHub auth required — all deps are public on Packagist)
composer install

# Code style check (PHPCS standard JtlConnector, scope: src controllers index.php jtlconnector.php)
composer phpcs

# Auto-fix code style
composer phpcs:fix

# Static analysis (PHPStan max level, config: phpstan.neon)
composer phpstan

# Both static checks
composer analyse

# Build the distribution ZIP for a given version
php ./lib/bin/phing release -Dversion=<version>

# Local one-shot build (production deps, ZIP, then restore dev deps)
./build.sh
```

There is no PHPUnit setup in this repo — no `composer tests`, no `phpunit/phpunit` in require-dev.

CI uses `ghcr.io/jtl-software/connector-utils-ci-docker/php/cli:<8.1|8.2|8.3>` containers (matrix in `check.yaml`).

## Architecture

### Request flow

PrestaShop loads the module class `JTLConnector` from `jtlconnector.php` on install/configure. JTL-Wawi calls the connector via the front controller at `controllers/front/index.php`, which boots `src/Connector.php` (implements `Jtl\Connector\Core\Connector\ConnectorInterface`). The connector wires up a PHP-DI container, registers the per-entity controllers, and dispatches the JTL pull/push/delete operations through the framework's RPC layer.

### Entry points

| File | Role |
|---|---|
| `jtlconnector.php` | PrestaShop module class — install/uninstall/configure UI, module metadata read from `build-config.yaml`. Also defines the `CONNECTOR_DIR` constant pointing at the module install dir. |
| `src/Connector.php` | Connector implementation: container wiring, controller registry, `TokenValidator`, `PrimaryKeyMapper` for ID mapping. |
| `controllers/front/index.php` | PrestaShop front controller — HTTP entry point for the JTL-Wawi RPC calls. |

### Controllers

`src/Controller/` contains one controller per JTL entity (Category, Customer, CustomerOrder, DeliveryNote, GlobalData, Image, Manufacturer, Payment, Product). All extend `AbstractController`. Pull/push/delete is the standard JTL-Connector framework pattern.

### ID mapping

`src/Mapper/PrimaryKeyMapper.php` (implements `Jtl\Connector\Core\Mapper\PrimaryKeyMapperInterface`) maps between JTL identity types and PrestaShop primary keys. Storage is **SQLite** at `db/connector.s3db` — created on install, fixed permissions `0777`, the `sqlite3` PHP extension is required at install time. The DB file is the single source of truth for cross-system identity, so don't drop or migrate it casually.

### Plugins / third-party integrations

`plugins/` is autoloaded with `psr-0` (root namespace) so vendored third-party plugin integrations can drop in there without composer.json changes. The directory itself is gitignored (`plugins/**` in `.gitignore`); production builds populate it via the build pipeline.

### Upgrade scripts

`upgrade/install-<version>.php` files run during PrestaShop module upgrades, version-keyed. When changing the module schema, add a new `install-<new-version>.php` and bump `build-config.yaml`'s `version`.

### Auth

`src/Auth/TokenValidator.php` implements the JTL-framework `TokenValidatorInterface`. The shared secret comes from `config/config.json` (gitignored runtime config).

## Build system

The release ZIP is produced by **phing** (`lib/bin/phing`, configured in `build.xml`):

1. `composer install --no-dev` populates `lib/` with production-only deps.
2. `phing release` (default target chain: `build` → `package` → `release`) copies the runtime files into `dist/jtlconnector/`, sets perms, zips into `jtl_connector_prestashop_<version>.zip`, and removes the `dist/` scratch.
3. `build-config.yaml` provides the `version` and `zipname` properties to `build.xml`.

CI does this in `.github/workflows/build-zip.yaml` (on tag push) via `.github/scripts/build-zip.sh`.

## Coding standards

- `declare(strict_types=1)` everywhere
- PHPStan at **max level**, config in `./phpstan.neon`
- PHPCS standard `JtlConnector` (from `jtl/connector-cq` via Slevomat rules)
- PHP `>= 8.1` per `composer.json`; CI matrices `8.1`, `8.2`, `8.3`

## CI/CD

Workflows in `.github/workflows/`:

| File | Trigger | Purpose |
|---|---|---|
| `check.yaml` | push master/main, PR | PHPCS + PHPStan in PHP 8.1/8.2/8.3 matrix |
| `build-zip.yaml` | tag push | Phing ZIP build |
| `auto-draft-pr.yaml` | push to feature branch | Auto-creates draft PR |
| `lint-actions.yaml` | workflow file changes | actionlint |
| `lint-scripts.yaml` | `.github/scripts/**` changes | shellcheck |
| `update-changelog.yaml` | release published | Calls `jtl-software/changelog-extractor` reusable workflow |
| `close-issue.yaml` | daily cron | Closes inactive issues |
