## What this project is

A PrestaShop module (`jtlconnector`) that synchronises shop data with JTL-Wawi ERP via the `jtl/connector` core framework. Distributed as a ZIP for manual install in PrestaShop 1.7+.

**Critical detail:** Composer's `vendor-dir` is `./lib/`, not `./vendor/`. Anything that normally lives at `vendor/bin/<tool>` is at `lib/bin/<tool>` here — most importantly `phing` (`lib/bin/phing`).

## Commands

```bash
# Install dependencies. jtl/connector requires `^6.0.1` (the released CO-3709 security fix). connector-core
# 6.0.1 is not published yet (see connector-core PR #52), so composer install cannot resolve the dependency
# and CI fails until it is released.
composer install

# Code style check (PHPCS standard JtlConnector, scope: src controllers index.php jtlconnector.php)
composer phpcs

# Auto-fix code style
composer phpcs:fix

# Static analysis (PHPStan max level, config: phpstan.neon)
composer phpstan

# Both static checks
composer analyse

# Build the distribution ZIP for a given version (scoped, see "Build system"; needs composer on PATH and github.com access on the first run)
php ./lib/bin/phing release -Dversion=<version>

# Smoke-test a built ZIP
php .github/scripts/smoke-test-zip.php jtl_connector_prestashop_<version>.zip

# Local one-shot build (production deps, ZIP, then restore dev deps)
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

`src/Controller/` contains one controller per JTL entity (Category, Customer, CustomerOrder, DeliveryNote, GlobalData, Image, Manufacturer, Payment, Product). All extend `AbstractController`. Pull/push/delete is the standard JTL-Connector framework pattern.

### ID mapping

`src/Mapper/PrimaryKeyMapper.php` (implements `Jtl\Connector\Core\Mapper\PrimaryKeyMapperInterface`) maps between JTL identity types and PrestaShop primary keys. Storage is **SQLite** at `db/connector.s3db` — created on install, fixed permissions `0777`, the `sqlite3` PHP extension is required at install time. The DB file is the single source of truth for cross-system identity, so don't drop or migrate it casually.

### Plugins / third-party integrations

Third-party connector plugins are dropped into `plugins/` by the merchant; the release ZIP ships only `plugins/.htaccess` and `plugins/index.php`, and the directory is gitignored (`plugins/**` in `.gitignore`). jtl/connector's `Application::loadPlugins()` registers its own PSR-0 `ClassLoader` for `plugins/` and instantiates every `<Namespace>/Bootstrap.php` that implements `Jtl\Connector\Core\Plugin\PluginInterface`. The `psr-0` entry in `composer.json` only matters for the dev install: the release ZIP's autoloader is classmap-authoritative and dumped while `plugins/` is empty. In the release ZIP, plugins must reference the bundled vendor classes by their prefixed names (`JtlConnectorPrestaVendor\DI\Container`, `JtlConnectorPrestaVendor\Symfony\Component\EventDispatcher\…`, `JtlConnectorPrestaVendor\Psr\Log\…`, …); only `Jtl\Connector\*` and PrestaShop's own classes keep their names (see "Build system").

### Upgrade scripts

`upgrade/install-<version>.php` files run during PrestaShop module upgrades, version-keyed. When changing the module schema, add a new `install-<new-version>.php` and bump `build-config.yaml`'s `version`.

### Auth

`src/Auth/TokenValidator.php` implements the JTL-framework `TokenValidatorInterface`. The shared secret comes from `config/config.json` (gitignored runtime config).

## Build system

The release ZIP is produced by **phing** (`lib/bin/phing`, configured in `build.xml`):

1. `composer install --no-dev` populates `lib/` with production-only deps.
2. `phing release` (default target chain: `php-scoper` → `scope` → `build` → `package` → `release`):
   - `php-scoper` downloads the pinned PHP-Scoper PHAR into `build/tools/` and verifies its SHA-256.
   - `scope` prefixes `src/`, `controllers/`, `upgrade/`, `jtlconnector.php` and `lib/` (config: `scoper.inc.php`) into `build/scoped/`, then dumps a classmap-authoritative autoloader there. The Composer autoload `files` identifiers are rewritten to `JtlConnectorPrestaVendor_<md5>`; otherwise PrestaShop (or another module) loading the same package unscoped would keep the prefixed functions from being defined. Finally `.github/scripts/check-scoped-classmap.php` fails the build if any shipped class was dropped from the scoped autoloader by an over-broad finder exclusion (it compares the scoped classmap against the unscoped authoritative one, ignoring the build-only `phing`).
   - `build` copies the scoped PHP files plus the remaining runtime files into `dist/jtlconnector/` and sets perms; `package` zips into `jtl_connector_prestashop_<version>.zip`; `release` removes the scratch dirs.
3. `build-config.yaml` provides the `version` and `zipname` properties to `build.xml`.

Build prerequisites besides PHP 8.3: `composer` on `PATH` (for `dump-autoload`) and HTTPS access to github.com on the first run (PHAR download; a PHAR already in `build/tools/php-scoper-<version>.phar` is reused if its checksum matches).

**Why scoping:** PrestaShop registers its own Composer autoloader (Symfony, Monolog, psr/log, Doctrine, …) before any module code runs, so unprefixed copies in `lib/` would never be loaded — and jtl/connector 6 needs newer majors than any PrestaShop ships. In the ZIP all bundled vendor namespaces live under `JtlConnectorPrestaVendor\`; the global namespace (PrestaShop's legacy API), `PrestaShop\`, `PrestaShopBundle\`, `Jtl\Connector\` (core + this module), `Composer\` and `Symfony\Polyfill\` keep their names. Only the release build is scoped; tests and static analysis run against the unscoped `lib/`.

Code that refers to a vendor class only *by string* (e.g. `sprintf('Monolog\Formatter\%sFormatter', …)`) is invisible to the scoper: those strings are prefixed by the `$stringClassNames` list in `scoper.inc.php`, and the build fails if a listed file or string is gone or if any other string literal still starts with a prefixed namespace. So don't build vendor class names from strings in module code, and when a dependency update breaks the build there, update that list. `jtlconnector.php`'s constructor must not use bundled classes at all: PrestaShop constructs the module whenever it lists modules, also on unsupported PHP versions.

CI builds the ZIP on every push (job `build-zip` in `.github/workflows/check.yaml`, via `.github/scripts/build-zip.sh`), runs `.github/scripts/smoke-test-zip.php` against it (constructs `JTLConnector` and checks that no bundled class gets loaded, sends auth/identify/features through `controllers/front/api.php`, and asserts the scoped core still rejects unauthenticated access — wrong token, no session, an unknown session id and a seeded-but-unauthenticated session, i.e. the CO-3709 fix — and does not log the plaintext session id; the few PrestaShop classes on these paths are stubbed) and uploads it as an artifact for tags.

## Coding standards

- `declare(strict_types=1)` everywhere
- PHPStan at **max level**, config in `./phpstan.neon`
- PHPCS standard `JtlConnector` (from `jtl/connector-cq` via Slevomat rules)
- PHP `>= 8.3` per `composer.json` (required by jtl/connector 6, which uses typed class constants); CI runs on `8.3`

## CI/CD

Workflows in `.github/workflows/`:

| File | Trigger | Purpose |
|---|---|---|
| `check.yaml` | every push (branches and tags) | actionlint + shellcheck, PHPCS + PHPStan + PHPUnit on PHP 8.3, scoped phing ZIP build (artifact uploaded for tags) |
| `auto-draft-pr.yaml` | push to feature branch | Auto-creates draft PR |
| `update-changelog.yaml` | release published | Calls `jtl-software/changelog-extractor` reusable workflow |
| `close-issue.yaml` | daily cron | Closes inactive issues |
