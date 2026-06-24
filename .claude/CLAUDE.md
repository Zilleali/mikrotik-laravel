# mikrotik-laravel — Claude Project Instructions

## Author
Zill E Ali — MTCNA certified ISP engineer, Pakistan.
GitHub: Zilleali | Email: zilleali1245@gmail.com | Website: zilleali.com

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
```
feat(logging): add MikrotikLogger centralized logging
```

Wrong format:
```
feat(logging): add MikrotikLogger centralized logging

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
```

### 2. Branch strategy — NEVER commit directly to main
- `main` — releases only, tag + release here
- `develop` — integration, all PRs merge here
- `feature/*` — new features
- `fix/*` — bug fixes
- All PRs target `develop`, never `main`
- Squash merge for feature/* PRs

### 3. Commit message format
Follow Conventional Commits:
```
feat(scope): description
fix(scope): description
docs(scope): description
test(scope): description
refactor(scope): description
```

Scopes: `pppoe`, `hotspot`, `queue`, `firewall`, `system`, `logging`, `validation`, `ci`, `docs`, `exceptions`, `connections`

---

## Package Overview

**Package:** `zilleali/mikrotik-laravel`
**Packagist:** packagist.org/packages/zilleali/mikrotik-laravel
**GitHub:** github.com/Zilleali/mikrotik-laravel
**Current stable:** v1.4.0
**In progress:** v1.5.0 (feature/logging branch)

---

## Tech Stack

- PHP ^8.2
- Laravel ^11.0|^12.0|^13.0
- Pest v3 (tests)
- PHPStan level 5 (zero errors required)
- PHP CS Fixer
- Orchestra Testbench

---

## Directory Structure

```
src/
├── MikrotikManager.php          — central manager
├── MikrotikServiceProvider.php  — service provider
├── Facades/MikroTik.php         — static facade
├── Connections/
│   ├── RouterosClient.php       — TCP socket
│   ├── RouterosClientSSL.php    — TLS port 8729
│   └── ConnectionPool.php       — persistent connections
├── Services/                    — 22 managers
├── Support/
│   ├── CachingProxy.php         — TTL caching
│   ├── RateLimiter.php          — API throttle
│   └── MikrotikLogger.php       — centralized logging (v1.5.0)
├── Events/                      — 4 events
├── Exceptions/
│   ├── ConnectionException.php  — factory methods
│   └── ApiException.php         — factory methods
└── Commands/                    — 3 artisan commands
config/mikrotik.php
```

---

## 22 Service Managers

| Facade | Manager | Since |
|--------|---------|-------|
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

---

## Common Pitfalls

- `RateLimiter` — `new RateLimiter()` directly. NOT a facade method.
- `MikroTik::ipPool()` — singular, not `ipPools()`
- `Filament/Widgets/` — data provider classes only, NOT real Filament widgets
- Tests are mock-based — no real router required
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
```

---

## CI Pipeline

File: `.github/workflows/ci.yml`
Jobs:
- `test` — PHP 8.2/8.3 × Laravel 11/12
- `test-laravel13` — PHP 8.3 × Laravel 13
- `phpstan` — level 5

All jobs use `--no-security-blocking`.
`setup-php` pinned to: `7c071dfe9dc99bdf297fa79cb49ea005b9fcadbc`

---

## Open Issues — v1.5.0 Milestone

| # | Type | Status |
|---|------|--------|
| #25 | Feature: Comprehensive exceptions in managers | Open |
| #24 | Bug: Silent failures on resource not found | Open |
| #23 | Bug: Missing .id validation in API responses | Open |
| #22 | Feature: Request validation in managers | Open |
| #21 | Bug: Missing logging | In Progress (feature/logging) |

---

## Before Every PR

```bash
./vendor/bin/phpstan analyse src --level=5
./vendor/bin/pest --no-coverage
```

Both must pass with zero errors.
