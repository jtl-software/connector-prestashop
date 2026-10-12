# AGENTS.md

Orientation for AI agents working in this repository. Details live in the files linked below; this file only tells you where to look and what not to trip over.

## What this is

PrestaShop module `jtlconnector` that syncs shop data with JTL-Wawi via the `jtl/connector` core framework (PHP >= 8.1). Full architecture, request flow and CI overview: [.github/copilot-instructions.md](.github/copilot-instructions.md).

## Read first

- [CLAUDE.md](CLAUDE.md) — mandatory Definition of Done and review process (Copilot + Claude reviewers, zero findings required). Follow it before calling any task complete.
- [.github/copilot-instructions.md](.github/copilot-instructions.md) — architecture, entry points, build, coding standards.
- [changelog.md](changelog.md) — every fix needs a line under `## unreleased`, format `- CO-XXXX - fix ...`.

## Layout

| Path | Contents |
|---|---|
| `jtlconnector.php` | PrestaShop module class (install/configure) |
| `controllers/front/index.php` | HTTP entry point for JTL-Wawi RPC calls |
| `src/Connector.php` | Container wiring, controller registry |
| `src/Controller/` | One controller per JTL entity, all extend `AbstractController` |
| `src/Mapper/PrimaryKeyMapper.php` | Identity mapping, SQLite at `db/connector.s3db` — never drop or migrate casually |
| `tests/Unit`, `tests/Integration` | PHPUnit tests; PrestaShop classes are stubbed in `tests/Support/PrestaShopStubs.php` |
| `upgrade/install-<version>.php` | Version-keyed module upgrade scripts |
| `lib/` | Composer `vendor-dir` (not `vendor/`); includes the `jtl/connector` core. Do not edit |

## Commands

```bash
composer install
composer phpcs        # style (src controllers index.php jtlconnector.php)
composer phpcs:fix
composer phpstan      # max level
composer test         # PHPUnit (phpunit.xml)
lib/bin/phpunit -c phpunit.xml --filter <TestName>
```

Binaries are in `lib/bin/`, not `vendor/bin/`. PHPCS does not cover `tests/`.

## Conventions

- `declare(strict_types=1)` everywhere; PHPStan must stay at level max with no errors.
- Match the surrounding code style; run `phpcs` before finishing.
- Add or update a unit test for behaviour changes. A regression test should fail without the fix.
- Attribute types matter to JTL-Wawi: `TranslatableAttribute` defaults to `string` (shows up as Freitext). Set the type explicitly (`TYPE_INT`, `TYPE_BOOL`, ...) when the value is not free text.
- Pull (shop to Wawi) and push (Wawi to shop) are separate code paths; check both when changing shared data.

## Git

- Branches: `bugfix/CO-XXXX_short_description`; PRs target `develop`.
- Commit messages: `fix: CO-XXXX ...` / `docs: ...`.
- Pushing a feature branch triggers an auto-created draft PR (`auto-draft-pr.yaml`); check `gh pr list --head <branch>` before creating one manually.
- Do not commit the `*.zip` build artifacts in the repo root or `config/config.json`.
