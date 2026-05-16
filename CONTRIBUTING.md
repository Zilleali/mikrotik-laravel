# Contributing to zilleali/mikrotik-laravel

Thank you for considering contributing! Here is everything you need to get started.

## Code of Conduct

This project follows the [Code of Conduct](CODE_OF_CONDUCT.md).
By participating, you are expected to uphold this standard.

## How to Contribute

### Reporting Bugs

Before opening a bug report:

- Check existing [issues](https://github.com/Zilleali/mikrotik-laravel/issues)
- Make sure you are on the latest version

When reporting, include:

- RouterOS version (`/system/resource/print` → version)
- PHP and Laravel version
- Package version
- Steps to reproduce
- Expected vs actual behavior

### Suggesting Features

Open an issue with the `enhancement` label. Describe:

- The use case — what problem does it solve?
- Which manager it belongs to (PPPoE, Hotspot, Queue, etc.)
- Example code showing how it would be used

### Submitting a Pull Request

1. Fork the repository
2. Create a feature branch from `develop`:
```bash
   git checkout develop
   git checkout -b feature/your-feature-name
```
3. Write tests first — red, then green
4. Make sure all tests pass:
```bash
   ./vendor/bin/pest --no-coverage
```
5. Make sure code style passes:
```bash
   ./vendor/bin/php-cs-fixer fix --dry-run --diff
```
6. Commit with a clear message:
```bash
   git commit -m "feat(pppoe): add getSecretByMac method"
```
7. Push and open a PR to `develop` — not `main`

## Commit Message Format

Follow this pattern:
- type(scope): short description
- feat(pppoe):     new feature in PPPoE manager
- fix(client):     bug fix in RouterosClient
- test(hotspot):   add tests for HotspotManager
- docs(readme):    update documentation
- chore(ci):       update GitHub Actions workflow
- refactor(queue): simplify bulkSetLimit logic

## Development Setup

```bash
git clone https://github.com/Zilleali/mikrotik-laravel.git
cd mikrotik-laravel
composer install
./vendor/bin/pest --no-coverage
```

No real MikroTik router needed — all tests use mock clients.

## Branch Strategy

| Branch | Purpose |
|---|---|
| `main` | Stable releases only |
| `develop` | Integration — all PRs go here |
| `feature/*` | New features |
| `fix/*` | Bug fixes |
| `release/*` | Release preparation |

## Testing Requirements

- Every new method must have at least one test
- Tests go in `tests/Unit/Services/`
- Use the mock client pattern — no real router needed
- All existing tests must still pass

## Code Style

This project uses PHP CS Fixer. Run before committing:

```bash
./vendor/bin/php-cs-fixer fix
```
