# Changelog

All notable changes to `zilleali/mikrotik-laravel` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.7.0] — Unreleased

### Added

#### MikrotikFake — Test Helper

- `MikrotikFake::fake()` — swaps the Laravel container binding; no real router needed in tests
- `forCommand(command, rows)` — pre-load responses keyed by RouterOS command path
- `forStream(command, rows)` — pre-load rows for Generator-based streaming methods
- `assertQueried(command)` — assert a command was sent to the router
- `assertNotQueried(command)` — assert a command was NOT sent
- `assertQueryCount(int)` — assert total number of commands sent
- `recordedQueries()` — full ordered list of commands sent during the test
- `FakeRouterosClient` — internal fake socket client; no TCP connection opened

#### Laravel Pulse Card

- `RouterHealthRecorder` — records CPU%, memory%, API latency, uptime beats into Pulse storage
- `RouterHealthCard` — Livewire card rendering router health with color-coded bars
- Blade view `mikrotik-laravel::pulse.router-health` — dark/light theme aware
- Registered automatically when `laravel/pulse` is installed — zero config required
- Optional: add `laravel/pulse: ^1.0` to your app to enable
- Publish views: `php artisan vendor:publish --tag=mikrotik-pulse-views`

#### DiagnosticsManager — `MikroTik::diagnostics()`

- `isAlive()` — lightweight bool health check via `/system/identity/print`
- `ping()` — structured result: `{host, port, connected, alive, latency_ms, error}`
- `latency(samples)` — averaged N-sample API latency in milliseconds
- `getRaw(command, params)` — raw protocol words for debugging
- `connectionInfo()` — `{host, port, connected, protocol: 'API'|'API-SSL'}`

#### ExportManager — `MikroTik::export()` (SSH-based)

- `exportFull(flags)` — full `/export` text via SSH
- `exportSection(section, flags)` — section-specific export e.g. `/ip/firewall/filter`
- `parseConfig(raw)` — parses export text into `['section' => ['cmd', ...]]` map
- `diffConfigs(before, after)` — returns `{added, removed, sections_changed}`
- Requires: SSH service on router + `MIKROTIK_SSH_KEY` env var + `spatie/ssh` (auto-installed)

#### Generator Streaming

- `RouterosClient::queryStream()` — lazy row-by-row iteration via PHP Generator
- `PppoeManager::streamSecrets()` / `streamSessions()`
- `HotspotManager::streamUsers()` / `streamSessions()`
- `QueueManager::streamQueues()`
- `DhcpManager::streamLeases()`
- `SessionMonitor::streamActiveSessions()` — combined PPPoE + Hotspot with `type` key

#### Socket / Timeout Config

- `RouterosClient::configure()` — apply `socket_timeout`, `socket_blocking`, `throw_timeout_exception`
- `MIKROTIK_SOCKET_TIMEOUT` — socket read timeout (default 30s)
- `MIKROTIK_SOCKET_BLOCKING` — blocking mode toggle (default true)
- `MIKROTIK_THROW_TIMEOUT_EXCEPTION` — throw on timeout vs return empty (default true)
- Timeout detection in `decodeLength()` via `stream_get_meta_data()`

### Dependencies

- Added `spatie/ssh: ^1.8` to `require`
- Added `laravel/pulse: ^1.0` to `require-dev` and `suggest`

---

## [1.6.0] — 2026-06-24

### Added

- `MikrotikLogger` — centralized PSR-3 logging across all 22 service managers
- Structured log context: manager name, action, target, router name
- Log channel and level configurable via `MIKROTIK_LOG_CHANNEL` / `MIKROTIK_LOG_LEVEL`
- Log enable/disable toggle via `MIKROTIK_LOG_ENABLED`
- `MikrotikLogger::critical()` — dedicated log method for destructive operations (reboot, delete, kick)
- `MikrotikLogger::connection()` — connection lifecycle events (connect, disconnect, retry)

---

## [1.5.0] — 2026-06-10

### Added

#### Input Validation

- All 18 writable service managers validate required fields before sending to router
- `ResourceNotFoundException` — thrown when `getX()` returns no result for a given name/ID
- `.id` validation in API responses — detects missing IDs from router replies
- Structured `ValidationException` messages with field names

#### Exception Improvements

- `ConnectionException::routerNotFound()` — named router not in config
- `ConnectionException::retriesExhausted()` — all retry attempts failed
- `ApiException::notFound()` — resource not found on router
- `ApiException::permissionDenied()` — API user lacks permission

---

## [1.4.0] — 2026-05-26

### Added

- PHPStan level 5 static analysis — zero errors
- `ConnectionException` factory methods — unreachable,
  authenticationFailed, timeout, routerNotFound, retriesExhausted
- `ApiException` factory methods — fromTrap, permissionDenied, notFound
- PHPStan CI job added to pipeline
- Facade `@method` docblocks fixed — all use statements added

### Fixed

- `MikrotikManager` — inline comments cleaned
- `MikroTik` Facade — missing use statements added

## [1.3.0] — 2026-05-20

### Fixed

- CI pipeline — pin `shivammathur/setup-php` to commit hash
  for supply chain attack prevention
- `.gitignore` — ensure `.php-cs-fixer.cache` excluded from git history

---

## [1.2.0] — 2026-05-19

### Added

- `IpAddressManager` — IP address CRUD, enable/disable, interface filter
- `ArpManager` — ARP table, static entries, MAC lookup, cache flush
- `DnsManager` — static entries, cache, server config, domain blocking
- `RouteManager` — static routes, default gateway, failover routes
- `NtpManager` — NTP client config, timezone, sync status
- `ScriptManager` — scripts CRUD, run script, schedulers
- `SyslogManager` — remote targets, rules, setupRemoteSyslog() one-call
- `SessionMonitor` — combined PPPoE+Hotspot sessions, isUserOnline(), getSummary()
- `UsageTracker` — per-user bandwidth, getTopUsers(), getTotalNetworkUsage()
- `RateLimiter` — API call throttling, per-second/minute limits

---

## [1.1.0] — 2026-05-18

### Added

#### SSL Connection

- `RouterosClientSSL` — TLS encrypted connection via port 8729
- Self-signed certificate support — ISP standard
- `verifyPeer` option for strict CA verification
- Auto-selected from config: `MIKROTIK_SSL=true`
- `getConnectionInfo()` — returns SSL status

#### Bridge Manager

- `getBridges()` / `getBridge($name)`
- `addBridge($data)` / `removeBridge($name)`
- `getBridgePorts()` / `getBridgePortsByBridge($bridge)`
- `addBridgePort($data)` / `removeBridgePort($interface)`
- `getPortCount($bridge)`
- `getBridgeHosts()` / `getBridgeHostsByBridge($bridge)`
- `getBridgeFilters()` / `addBridgeFilter($data)`

#### ConnectionPool

- Persistent `RouterosClient` connections keyed by router name
- `isAlive($name)` — health check before reuse
- `pruneDeadConnections()` — cleanup dropped connections
- `getAliveConnections()` — list active connections
- `flush()` — disconnect all
- `MikrotikManager` refactored to use pool instead of plain array

#### Widget Stubs

- Framework-agnostic widget data classes — no UI dependency
- `ActiveSessionsWidget` — PPPoE + Hotspot session data
- `RouterHealthWidget` — CPU, RAM, uptime, version data
- `BandwidthChartWidget` — TX/RX traffic data
- `InterfaceTableWidget` — interface status data

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

#### Managers (12 total)

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

- GitHub Actions CI — PHP 8.2/8.3 × Laravel 11/12
- PHP CS Fixer code style enforcement
- Packagist published — `composer require zilleali/mikrotik-laravel`
- Branch protection rules on `main`
- Issue templates — bug report, feature request, RouterOS integration
- Community standards — CODE_OF_CONDUCT, CONTRIBUTING, SECURITY, PR template

### Tests

- 70+ unit tests across all managers
- Mock-based testing — no real router required
- `TrackableClient` for cache hit/miss verification
