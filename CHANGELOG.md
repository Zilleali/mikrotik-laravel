# Changelog

All notable changes to `zilleali/mikrotik-laravel` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] — 2026-05-16

### Added

#### Core

- `RouterosClient` — TCP socket client with RouterOS sentence protocol
- Variable-length encoding/decoding (1–5 bytes)
- RouterOS v6.43+ plain login + legacy MD5 challenge-response
- Auto-disconnect via destructor — no socket leaks
- Retry mechanism — configurable `retry_attempts` + `retry_delay`
- `MikrotikManager` — central manager with multi-router support
- `MikroTik` Facade — static access to all managers
- `CachingProxy` — transparent TTL caching with auto-invalidation on write

#### Managers (10 total)

- `PppoeManager` — secrets, profiles, sessions, bulk operations
- `HotspotManager` — users, profiles, active hosts, voucher generation
- `QueueManager` — simple/tree queues, bulk bandwidth limits
- `FirewallManager` — filter rules, NAT, mangle, address lists
- `SystemManager` — resources, health, logs, ping, reboot
- `InterfaceManager` — interfaces, traffic, VLANs, enable/disable
- `DhcpManager` — leases, servers, static lease conversion
- `WirelessManager` — registration table, access list, client count
- `IpPoolManager` — pools, used addresses
- `RadiusManager` — servers, incoming CoA config
- `RouterUserManager` — router users, groups, active sessions
- `VpnManager` — WireGuard peers, L2TP/PPTP sessions

#### Events

- `SessionCreated` — PPPoE/Hotspot session created
- `SessionDisconnected` — PPPoE/Hotspot session disconnected
- `RouterConnected` — successful router connection
- `RouterUnreachable` — router unreachable after retries

#### Artisan Commands

- `php artisan mikrotik:ping` — test router TCP connectivity
- `php artisan mikrotik:sync` — sync router data to Laravel cache
- `php artisan mikrotik:monitor` — real-time terminal health monitor

#### Infrastructure

- GitHub Actions CI — PHP 8.3 / Laravel 12
- PHP CS Fixer code style enforcement
- Packagist published — `composer require zilleali/mikrotik-laravel`
- Branch protection rules on `main`
- Issue templates — bug report, feature request, RouterOS integration
- Community standards — CODE_OF_CONDUCT, CONTRIBUTING, SECURITY, PR template

### Tests

- 70+ unit tests across all managers
- Mock-based testing — no real router required
- `TrackableClient` for cache hit/miss verification

---

## [0.1.0] — 2026-05-14

### Added

- Initial release
- `RouterosClient` core TCP client
- `PppoeManager` basic CRUD
- CI/CD pipeline setup
- Packagist submission
