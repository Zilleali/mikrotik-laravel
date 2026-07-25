# mikrotik-laravel — Claude Project Instructions

## Author

Zill E Ali — MTCNA certified ISP engineer, Pakistan.
GitHub: Zilleali | Email: <zilleali1245@gmail.com> | Website: <https://zilleali.com>

---

## CRITICAL RULES — Read before every action

### 1. NEVER add Co-Authored-By to commits

Every commit must be authored by Zill E Ali only.
NEVER add any of these trailers:

- `Co-Authored-By: Claude`
- `Co-Authored-By: claude`
- `Co-Authored-By: assistant`
- Any AI attribution line of any kind

Correct format:

```text
feat(logging): add MikrotikLogger centralized logging
```

Wrong format:

```text
feat(logging): add MikrotikLogger centralized logging

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
```

### 2. NEVER push to GitHub without asking first

Commits are fine. `git push` is NOT — always show what will be pushed
(branch + commits) and wait for explicit approval ("yes", "push", "go").
This applies to every push: branches, tags, and force-pushes.
Same rule for anything that publishes: creating releases, editing the Wiki.

### 3. Branch strategy — NEVER commit directly to main

- `main` — releases only, tag + release here
- `develop` — integration, all PRs merge here
- `feature/*` — new features
- `fix/*` — bug fixes
- All PRs target `develop`, never `main`
- Squash merge for feature/* PRs
- Only exception: docs-only fixes on `main` (README/CHANGELOG), and only
  when Zill explicitly approves it in that conversation
- Before starting work, run `git fetch` and check local branches are not
  behind origin — PRs are merged in the GitHub UI, so local gets stale

### 4. Commit message format

Follow Conventional Commits:

```text
feat(scope): description
fix(scope): description
docs(scope): description
test(scope): description
refactor(scope): description
chore(scope): description
```

Scopes: `pppoe`, `hotspot`, `queue`, `firewall`, `system`, `logging`,
`validation`, `ci`, `docs`, `exceptions`, `connections`, `testing`,
`pulse`, `diagnostics`, `export`, `support`, `deps`

---

## Package Overview

**Package:** `zilleali/mikrotik-laravel`
**Purpose:** MikroTik RouterOS API client for Laravel — lets ISPs manage
PPPoE, Hotspot, Queues, Firewall and router health from a Laravel app.
Built for real ISP operations (billing suspensions, session monitoring,
bandwidth control), not just generic API wrapping.
**Namespace:** `ZillEAli\MikrotikLaravel`
**Packagist:** <https://packagist.org/packages/zilleali/mikrotik-laravel>
**GitHub:** <https://github.com/Zilleali/mikrotik-laravel>
**Docs:** <https://github.com/Zilleali/mikrotik-laravel/wiki> — the Wiki is
the canonical documentation; README is a short landing page only
**Current stable:** v1.7.0 (2026-07-04) — verify with
`git tag --sort=-v:refname` before stating a version anywhere
**In progress:** nothing — next milestone not yet decided

---

## Tech Stack

- PHP ^8.2
- Laravel ^11.0 | ^12.0 | ^13.0 (`illuminate/support`)
- `spatie/ssh` ^1.8 — required, used by ExportManager
- `laravel/pulse` ^1.0 — optional (require-dev + suggest), Pulse card only
- Pest v3 (tests) — 489 tests, all mock-based, no real router needed
- PHPStan level 5 (zero errors required)
- PHP CS Fixer
- Orchestra Testbench

---

## Directory Structure

```text
src/
├── MikrotikManager.php          — central manager, one method per service
├── MikrotikServiceProvider.php  — service provider (registers Pulse card
│                                  only if laravel/pulse is installed)
├── Facades/MikroTik.php         — static facade
├── Connections/
│   ├── RouterosClient.php       — TCP socket + queryStream() Generator
│   ├── RouterosClientSSL.php    — TLS port 8729
│   └── ConnectionPool.php       — persistent connections
├── Services/                    — 24 managers (see table below)
├── Support/
│   ├── CachingProxy.php         — TTL caching
│   ├── RateLimiter.php          — API throttle
│   ├── MikrotikLogger.php       — centralized logging
│   ├── HasValidation.php        — input validation trait
│   └── HasIdValidation.php      — .id response validation trait
├── Testing/
│   ├── MikrotikFake.php         — public test double, swaps container binding
│   └── FakeRouterosClient.php   — internal fake socket, no TCP opened
├── Pulse/
│   ├── Recorders/RouterHealthRecorder.php — CPU/memory/latency/uptime beats
│   └── Livewire/RouterHealthCard.php      — dashboard card
├── Events/                      — SessionCreated, SessionDisconnected,
│                                  RouterConnected, RouterUnreachable
├── Exceptions/
│   ├── ConnectionException.php  — factory methods
│   ├── ApiException.php         — factory methods
│   ├── ValidationException.php
│   ├── ResourceNotFoundException.php
│   └── InvalidRouterResponseException.php
├── Filament/Widgets/            — data provider classes, NOT real widgets
├── Http/                        — empty scaffolding (Controllers, Middleware)
└── Commands/                    — mikrotik:ping, mikrotik:sync, mikrotik:monitor
resources/views/pulse/router-health.blade.php
config/mikrotik.php
tests/Unit/                      — mirrors src/ structure
```

---

## 24 Service Managers

| Facade | Manager | Since |
| --- | --- | --- |
| `MikroTik::pppoe()` | PppoeManager | v1.0.0 |
| `MikroTik::hotspot()` | HotspotManager | v1.0.0 |
| `MikroTik::queue()` | QueueManager | v1.0.0 |
| `MikroTik::firewall()` | FirewallManager | v1.0.0 |
| `MikroTik::system()` | SystemManager | v1.0.0 |
| `MikroTik::interfaces()` | InterfaceManager | v1.0.0 |
| `MikroTik::dhcp()` | DhcpManager | v1.0.0 |
| `MikroTik::wireless()` | WirelessManager | v1.0.0 |
| `MikroTik::ipPool()` | IpPoolManager | v1.0.0 |
| `MikroTik::radius()` | RadiusManager | v1.0.0 |
| `MikroTik::routerUsers()` | RouterUserManager | v1.0.0 |
| `MikroTik::vpn()` | VpnManager | v1.0.0 |
| `MikroTik::bridge()` | BridgeManager | v1.1.0 |
| `MikroTik::ipAddress()` | IpAddressManager | v1.2.0 |
| `MikroTik::arp()` | ArpManager | v1.2.0 |
| `MikroTik::dns()` | DnsManager | v1.2.0 |
| `MikroTik::routes()` | RouteManager | v1.2.0 |
| `MikroTik::ntp()` | NtpManager | v1.2.0 |
| `MikroTik::scripts()` | ScriptManager | v1.2.0 |
| `MikroTik::syslog()` | SyslogManager | v1.2.0 |
| `MikroTik::sessionMonitor()` | SessionMonitor | v1.2.0 |
| `MikroTik::usageTracker()` | UsageTracker | v1.2.0 |
| `MikroTik::diagnostics()` | DiagnosticsManager | v1.7.0 |
| `MikroTik::export()` | ExportManager | v1.7.0 |

Adding a new manager touches ALL of these:

1. `src/Services/XManager.php` — use `HasValidation`/`HasIdValidation`
   traits and `MikrotikLogger` like existing managers
2. `MikrotikManager.php` — add the accessor method + import
3. `Facades/MikroTik.php` — add the `@method` docblock line
4. `tests/Unit/Services/XManagerTest.php` — mock-based Pest tests
5. README managers table + CHANGELOG entry + Wiki page

---

## Code Conventions

- Every manager method that writes validates input first (via
  `HasValidation`) and logs via `MikrotikLogger`; destructive operations
  (reboot, delete, kick) log at `critical`
- `getX($name)` methods throw `ResourceNotFoundException` when nothing is
  found — never return null silently
- Docblocks on every public method, `@package` + `@author Zill E Ali`
  header block on every class
- Array shapes documented with PHPStan-style annotations
  (`list<array<string, string>>`) — required to keep level 5 clean
- Large result sets get a `streamX()` Generator variant alongside `getX()`
- Tests use anonymous-class client mocks or `MikrotikFake`; never open a
  real socket in tests

---

## Common Pitfalls

- `RateLimiter` — `new RateLimiter()` directly. NOT a facade method.
- `MikroTik::ipPool()` — singular, not `ipPools()`
- `Filament/Widgets/` — data provider classes only, NOT real Filament widgets
- `MikrotikFake::fake()` swaps `app('mikrotik')` binding — it extends
  `MikrotikManager` and is `final`; its client records queries for
  `assertQueried()` / `assertNotQueried()` / `assertQueryCount()`
- `resolveAndResetRouter()` MUST be called inside any `getClient()`
  override — it drains the `router('name')` state between calls
- Pulse classes must stay behind `class_exists()` guards in the service
  provider — laravel/pulse is optional
- ExportManager uses SSH (spatie/ssh), not the RouterOS API — needs
  SSH enabled on the router + `MIKROTIK_SSH_KEY`
- Tests are mock-based — no real router required; full suite takes ~2 min
- PHPStan level 5 must pass before every PR

---

## Config Env Vars

```env
MIKROTIK_HOST=192.168.88.1
MIKROTIK_PORT=8728
MIKROTIK_USER=admin
MIKROTIK_PASS=
MIKROTIK_TIMEOUT=10
MIKROTIK_SSL=false
MIKROTIK_SSL_VERIFY=false
MIKROTIK_RETRY_ATTEMPTS=3
MIKROTIK_RETRY_DELAY=1000
MIKROTIK_LOG_ENABLED=true
MIKROTIK_LOG_CHANNEL=stack
MIKROTIK_LOG_LEVEL=info
MIKROTIK_SOCKET_TIMEOUT=30
MIKROTIK_SOCKET_BLOCKING=true
MIKROTIK_THROW_TIMEOUT_EXCEPTION=true
MIKROTIK_SSH_PORT=22
MIKROTIK_SSH_KEY=~/.ssh/id_rsa
MIKROTIK_SSH_TIMEOUT=30
```

---

## CI Pipeline

File: `.github/workflows/ci.yml`
Jobs:

- `test` — matrix: PHP 8.2×L11, 8.3×L11, 8.3×L12
- `test-laravel13` — PHP 8.3 × Laravel 13 (removes pest-plugin-laravel first)
- `phpstan` — level 5

All composer steps use `--no-security-blocking`.
`setup-php` pinned to commit hash `7c071dfe9dc99bdf297fa79cb49ea005b9fcadbc`
for supply-chain safety — keep it pinned, never switch back to a tag.

---

## Documentation Rules

- **Wiki is canonical** — detailed usage, examples, and manager references
  live at <https://github.com/Zilleali/mikrotik-laravel/wiki>
- **README is a landing page** (~170 lines): badges, Wiki links table,
  features list, managers table, requirements, install, quick start.
  Do NOT re-add detailed per-manager sections to it.
- **CHANGELOG** follows Keep a Changelog: `## [X.Y.Z] — YYYY-MM-DD`,
  grouped under `### Added` / `### Fixed` / `### Dependencies`,
  `####` sub-headings per feature area
- **Markdownlint**: blank line after every heading before a list
  (MD022/MD032); `.markdownlint.json` sets `MD024 siblings_only: true`
  so repeated `### Added` across versions is fine — don't rename headings
  to dodge MD024

---

## Release Process

1. Feature branch → PR to `develop` (squash merge)
2. `develop` → PR to `main`
3. Update CHANGELOG with the release date + README if features changed —
   BEFORE the main merge, so docs land in the tagged commit
4. Tag `vX.Y.Z` on the `main` merge commit — ASK before pushing the tag
5. Zill writes the GitHub Release title/notes himself in the UI
6. Packagist auto-syncs from the tag

---

## Definition of Done

Work is finished only when ALL of these pass:

```bash
composer analyse    # phpstan level 5 — zero errors
composer test       # pest, all ~489 tests green
```

Plus:

- New/changed public methods have tests and docblocks
- CHANGELOG entry added under the unreleased/current version
- README managers table updated if a manager was added
- Commit messages follow Conventional Commits, no AI attribution
- Nothing pushed to GitHub without asking Zill first
