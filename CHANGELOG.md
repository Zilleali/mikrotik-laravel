# Changelog

All notable changes to `zilleali/mikrotik-laravel` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.1] — 2026-05-20

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
