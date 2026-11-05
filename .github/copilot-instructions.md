## What this project is

A PrestaShop module (`jtlconnector`) that synchronises shop data with JTL-Wawi ERP via the `jtl/connector` core framework. Distributed as a ZIP for manual install in PrestaShop 1.7+.

**Critical detail:** Composer's `vendor-dir` is `./lib/`, not `./vendor/`. Anything that normally lives at `vendor/bin/<tool>` is at `lib/bin/<tool>` here — most importantly `phing` (`lib/bin/phing`).

## Commands

```bash
# Install dependencies. jtl/connector requires `^6.0.1` (the released CO-3709 security fix).
composer install

# Code style check (PHPCS standard JtlConnector, scope: src controllers index.php jtlconnector.php)
composer phpcs

# Auto-fix code style
composer phpcs:fix

# Static analysis (PHPStan max level, config: phpstan.neon)
composer phpstan

# Both static checks
composer analyse

# Build the distribution ZIP for a given version (scoped, see "Build system"; needs composer on PATH and github.com access on the first run).
# The release build needs the production dependencies only, so reinstall the dev dependencies afterwards.
composer install --no-dev
php ./lib/bin/phing release -Dversion=<version>
composer install

# Smoke-test a built ZIP
php .github/scripts/smoke-test-zip.php jtl_connector_prestashop_<version>.zip

# Local one-shot build of jtl_connector_prestashop_<build-config.yaml version>.zip (production deps from composer.lock,
# ZIP, then dev deps again, also on failure; overwrites a ZIP of that name)
./build.sh
```

PHPUnit tests live in `tests/` (`composer test`, config `phpunit.xml`); PrestaShop classes are stubbed in `tests/Support/PrestaShopStubs.php`.

CI uses `ghcr.io/jtl-software/connector-utils-ci-docker/php/cli:8.3` containers (matrix in `check.yaml`).

## Architecture

### Request flow

PrestaShop loads the module class `JTLConnector` from `jtlconnector.php` whenever it lists modules, and for install/configure. JTL-Wawi calls the connector via the front controller at `controllers/front/api.php` (`index.php?fc=module&module=jtlconnector&controller=api`), which boots `src/Connector.php` (implements `Jtl\Connector\Core\Connector\ConnectorInterface`). The connector wires up a PHP-DI container, registers the per-entity controllers, and dispatches the JTL pull/push/delete operations through the framework's RPC layer.

### Entry points

| File | Role |
|---|---|
| `jtlconnector.php` | PrestaShop module class — install/uninstall/configure UI, module metadata read from `build-config.yaml`. Also defines the `CONNECTOR_DIR` constant pointing at the module install dir. |
| `src/Connector.php` | Connector implementation: container wiring, controller registry, `TokenValidator`, `PrimaryKeyMapper` for ID mapping. |
| `controllers/front/api.php` | PrestaShop front controller — HTTP entry point for the JTL-Wawi RPC calls. |

### Controllers

`src/Controller/` contains one controller per JTL entity (Category, Customer, CustomerOrder, DeliveryNote, GlobalData, Image, Manufacturer, Payment, Product, ProductPrice, ProductStockLevel, Specific, StatusChange). All extend `AbstractController`, `ProductController` via `ProductPriceController`. Pull/push/delete is the standard JTL-Connector framework pattern.

### ID mapping

`src/Mapper/PrimaryKeyMapper.php` (implements `Jtl\Connector\Core\Mapper\PrimaryKeyMapperInterface`) maps between JTL identity types and PrestaShop primary keys. Storage is PrestaShop's own database: `jtl_connector_link_*` tables (`endpoint_id` primary key, `host_id`), accessed via `\Db`. There is no type column: customers and customer groups share `jtl_connector_link_customer` and all image types share `jtl_connector_link_image`, so ids of those types can collide and `clear()` of one of them clears the shared table. These tables are the single source of truth for cross-system identity, so don't drop or migrate them casually. `db/connector.s3db` holds no mappings: the release ZIP ships it empty because `install()` requires it to be writable (and the `sqlite3` PHP extension to be loaded); jtl/connector 6 keeps its SQLite session store under `var/`.

### Plugins / third-party integrations

Third-party connector plugins are dropped into `plugins/` by the merchant; the release ZIP ships only `plugins/.htaccess` and `plugins/index.php`; apart from those and `.gitkeep`, the directory is gitignored. jtl/connector's `Application::loadPlugins()` registers its own PSR-0 `ClassLoader` for `plugins/` and instantiates every `<Namespace>/Bootstrap.php` that implements `Jtl\Connector\Core\Plugin\PluginInterface`. The `psr-0` entry in `composer.json` only matters for the dev install: the release ZIP's autoloader is classmap-authoritative and dumped while `plugins/` is empty. In the release ZIP, plugins must reference the bundled vendor classes by their prefixed names (`JtlConnectorPrestaVendor\DI\Container`, `JtlConnectorPrestaVendor\Symfony\Component\EventDispatcher\…`, `JtlConnectorPrestaVendor\Psr\Log\…`, …); only `Jtl\Connector\*` and PrestaShop's own classes keep their names (see "Build system").

### Upgrade scripts

`upgrade/install-<version>.php` files run during PrestaShop module upgrades, version-keyed. When changing the module schema, add a new `install-<new-version>.php` and bump `build-config.yaml`'s `version`.

### Auth

`src/Auth/TokenValidator.php` implements the JTL-framework `TokenValidatorInterface` (constant-time comparison). The shared secret is the PrestaShop configuration value `jtlconnector_pass`: generated randomly on install, editable on the module's configuration page (minimum 8 characters).

## Build system

The release ZIP is produced by **phing** (`lib/bin/phing`, configured in `build.xml`):

1. `composer install --no-dev` populates `lib/` with production-only deps.
2. `phing release` (default target chain: `check-production-install` + `php-scoper` → `scope` → `build` → `package` → `release`):
   - `check-production-install` fails the build right away if `lib/` is a dev install (step 1 was skipped).
   - `php-scoper` downloads the pinned PHP-Scoper PHAR into `build/tools/` and verifies its SHA-256.
   - `scope` prefixes `src/`, `controllers/`, `upgrade/`, `jtlconnector.php` and `lib/` (config: `scoper.inc.php`) into `build/scoped/`, then dumps a classmap-authoritative autoloader there. The Composer autoload `files` identifiers are rewritten to `JtlConnectorPrestaVendor_<md5>`; otherwise PrestaShop (or another module) loading the same package unscoped would keep the prefixed functions from being defined. Finally `.github/scripts/check-scoped-classmap.php` fails the build if any shipped class was dropped from the scoped autoloader by an over-broad finder exclusion (it compares the scoped classmap against the unscoped authoritative one, ignoring the build-only `phing`).
   - `build` copies the scoped PHP files plus the remaining runtime files into `dist/jtlconnector/` and sets perms. Every directory with runtime data (`config/`, `db/`, `plugins/`, `tmp/`, `var/`) ships a `Deny from all` `.htaccess` and an `index.php`, which also reach existing installs on upgrade. The legacy `logs/` dir is not shipped; `src/Utils/LegacyLogDir.php` moves it to `var/log` on install and in `upgrade/install-3.0.0.php`. `package` zips into `jtl_connector_prestashop_<version>.zip` and sets the mode of every entry to its `dist/` mode (`.github/scripts/fix-zip-modes.php`; phing 2 stores every directory as 0777); `release` removes the scratch dirs.
3. `build-config.yaml` provides the `version` and `zipname` properties to `build.xml`.

Build prerequisites besides PHP 8.3: `composer` on `PATH` (for `dump-autoload`) and HTTPS access to github.com on the first run (PHAR download; a PHAR already in `build/tools/php-scoper-<version>.phar` is reused if its checksum matches).

**Why scoping:** PrestaShop registers its own Composer autoloader (Symfony, Monolog, psr/log, Doctrine, …) before any module code runs, so unprefixed copies in `lib/` would never be loaded — and jtl/connector 6 needs newer majors than any PrestaShop ships. In the ZIP all bundled vendor namespaces live under `JtlConnectorPrestaVendor\`; the global namespace (PrestaShop's legacy API), `PrestaShop\`, `PrestaShopBundle\`, `Jtl\Connector\` (core + this module), `Composer\` and `Symfony\Polyfill\` keep their names. Only the release build is scoped; tests and static analysis run against the unscoped `lib/`.

Code that refers to a vendor class only *by string* (e.g. `sprintf('Monolog\Formatter\%sFormatter', …)`) is invisible to the scoper: those strings are prefixed by the `$stringClassNames` list in `scoper.inc.php`, and the build fails if a listed file or string is gone or if any other string literal still starts with a prefixed namespace. So don't build vendor class names from strings in module code, and when a dependency update breaks the build there, update that list. `jtlconnector.php`'s constructor must not use bundled classes at all: PrestaShop constructs the module whenever it lists modules, also on unsupported PHP versions.

CI builds the ZIP on every push (job `build-zip` in `.github/workflows/check.yaml`, via `.github/scripts/build-zip.sh`), runs `.github/scripts/smoke-test-zip.php` against it and uploads it as an artifact for tags. The smoke test checks the ZIP itself (nothing world-writable except the runtime dirs and `db/connector.s3db`, a `Deny from all` `.htaccess` and an `index.php` in every runtime dir, no legacy `logs/`), constructs `JTLConnector` and checks that no bundled class gets loaded, runs `upgrade/install-3.0.0.php` on a copy with a legacy `logs/` dir (and checks it removes the manufacturer link without endpoint that 2.x saved), and sends auth/identify/features through `controllers/front/api.php` (`jtlauth` once in the POST body and once in the query string). It asserts the scoped core still rejects unauthenticated access — wrong token, no session, an unknown session id and a seeded-but-unauthenticated session, i.e. the CO-3709 fix. It runs that RPC sequence with a fresh install's defaults (no debug lines may be logged) and again with debug logging on (debug lines must be logged), then checks that no log contains a plaintext session id or a submitted token. The few PrestaShop classes on these paths are stubbed.

## Coding standards

- `declare(strict_types=1)` everywhere
- PHPStan at **max level**, config in `./phpstan.neon`
- PHPCS standard `JtlConnector` (from `jtl/connector-cq` via Slevomat rules)
- PHP `>= 8.3` per `composer.json` (required by jtl/connector 6, which uses typed class constants); CI runs on `8.3`. **Exception:** `jtlconnector.php`, `src/Utils/LegacyLogDir.php` and `upgrade/install-3.0.0.php` must stay parseable on PHP 7.1, because PrestaShop loads the module class (and runs `install()`, which reports the unsupported version) on any PHP version: no newer syntax there (e.g. `catch (\Throwable $e)` keeps its variable).

## CI/CD

Workflows in `.github/workflows/`:

| File | Trigger | Purpose |
|---|---|---|
| `check.yaml` | every push (branches and tags) | actionlint + shellcheck, PHPCS + PHPStan + PHPUnit on PHP 8.3, scoped phing ZIP build (artifact uploaded for tags) |
| `auto-draft-pr.yaml` | push to feature branch | Auto-creates draft PR |
| `update-changelog.yaml` | release published | Calls `jtl-software/changelog-extractor` reusable workflow |
| `close-issue.yaml` | daily cron | Closes inactive issues |
