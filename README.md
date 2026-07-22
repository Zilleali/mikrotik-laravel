# zilleali/mikrotik-laravel

[![Tests](https://github.com/Zilleali/mikrotik-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/Zilleali/mikrotik-laravel/actions)
[![Packagist](https://img.shields.io/packagist/v/zilleali/mikrotik-laravel)](https://packagist.org/packages/zilleali/mikrotik-laravel)
[![PHP](https://img.shields.io/packagist/php-v/zilleali/mikrotik-laravel)](https://packagist.org/packages/zilleali/mikrotik-laravel)
[![License](https://img.shields.io/github/license/Zilleali/mikrotik-laravel)](LICENSE)
[![MTCNA](https://img.shields.io/badge/MTCNA-Certified-009AC7)](https://zilleali.com)
[![Wiki](https://img.shields.io/badge/Wiki-Documentation-blue)](https://github.com/Zilleali/mikrotik-laravel/wiki)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen)](https://phpstan.org)
![Visitors](https://api.visitorbadge.io/api/visitors?path=https%3A%2F%2Fgithub.com%2FZilleali%2Fmikrotik-laravel&label=Visitors&labelColor=%23555555&countColor=%231D9E75&style=flat)

> **MikroTik RouterOS API for Laravel** — Manage PPPoE, Hotspot, Queues, Firewall & System health from any Laravel application. Built by an [MTCNA-certified](https://zilleali.com) ISP engineer.

---

## Documentation

📖 **[Full Documentation — GitHub Wiki](https://github.com/Zilleali/mikrotik-laravel/wiki)**

| Page | Link |
| --- | --- |
| Getting Started | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/Getting-Started) |
| Managers Reference | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/Managers-Reference) |
| Configuration | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/Configuration) |
| Multi-Router Setup | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/Multi-Router-Setup) |
| SSL Setup | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/SSL-Setup) |
| Testing with MikrotikFake | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/Testing) |
| Laravel Pulse Integration | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/Pulse-Integration) |
| Changelog | [→ Read](https://github.com/Zilleali/mikrotik-laravel/wiki/Changelog) |

---

## Features

- **PPPoE Management** — secrets, profiles, sessions, bulk operations
- **Hotspot Management** — users, profiles, active hosts, voucher generation
- **Queue Management** — simple/tree queues, bulk bandwidth limits
- **Firewall Management** — filter rules, NAT, mangle, address lists
- **System Management** — resources, health, logs, ping, reboot
- **Interface, DHCP, Wireless, IP Pool, RADIUS, VPN, Bridge** — full CRUD
- **Diagnostics** — health check, API latency, ping, connection info *(v1.7.0)*
- **SSH Config Export** — full/section export, diff configs *(v1.7.0)*
- **Generator Streaming** — lazy row-by-row iteration for large datasets *(v1.7.0)*
- **MikrotikFake** — drop-in test helper, zero manual mocking *(v1.7.0)*
- **Laravel Pulse Card** — router health dashboard widget *(v1.7.0)*
- **SSL Connection** — TLS encrypted API (port 8729)
- **Multi-Router Support** — manage multiple routers from one app
- **Caching, Retry, Rate Limiting** — production-ready reliability
- **Laravel Events** — SessionCreated, SessionDisconnected, RouterUnreachable
- **Artisan Commands** — mikrotik:ping, mikrotik:sync, mikrotik:monitor

---

## Available Managers

| Manager | Facade Method | Since |
| --- | --- | --- |
| PppoeManager | `MikroTik::pppoe()` | v1.0.0 |
| HotspotManager | `MikroTik::hotspot()` | v1.0.0 |
| QueueManager | `MikroTik::queue()` | v1.0.0 |
| FirewallManager | `MikroTik::firewall()` | v1.0.0 |
| SystemManager | `MikroTik::system()` | v1.0.0 |
| InterfaceManager | `MikroTik::interfaces()` | v1.0.0 |
| DhcpManager | `MikroTik::dhcp()` | v1.0.0 |
| WirelessManager | `MikroTik::wireless()` | v1.0.0 |
| IpPoolManager | `MikroTik::ipPool()` | v1.0.0 |
| RadiusManager | `MikroTik::radius()` | v1.0.0 |
| RouterUserManager | `MikroTik::routerUsers()` | v1.0.0 |
| VpnManager | `MikroTik::vpn()` | v1.0.0 |
| BridgeManager | `MikroTik::bridge()` | v1.1.0 |
| IpAddressManager | `MikroTik::ipAddress()` | v1.2.0 |
| ArpManager | `MikroTik::arp()` | v1.2.0 |
| DnsManager | `MikroTik::dns()` | v1.2.0 |
| RouteManager | `MikroTik::routes()` | v1.2.0 |
| NtpManager | `MikroTik::ntp()` | v1.2.0 |
| ScriptManager | `MikroTik::scripts()` | v1.2.0 |
| SyslogManager | `MikroTik::syslog()` | v1.2.0 |
| SessionMonitor | `MikroTik::sessionMonitor()` | v1.2.0 |
| UsageTracker | `MikroTik::usageTracker()` | v1.2.0 |
| DiagnosticsManager | `MikroTik::diagnostics()` | v1.7.0 |
| ExportManager | `MikroTik::export()` | v1.7.0 |

---

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 \| ^13.0 |
| RouterOS | 6.43+ \| 7.x |
| MikroTik API | Port 8728 (plain) or 8729 (SSL) |

Optional: `laravel/pulse: ^1.0` for the Pulse card — `spatie/ssh: ^1.8` for ExportManager.

---

## Installation

```bash
composer require zilleali/mikrotik-laravel
```

```bash
php artisan vendor:publish --tag=mikrotik-config
```

Add to `.env`:

```env
MIKROTIK_HOST=192.168.88.1
MIKROTIK_PORT=8728
MIKROTIK_USER=admin
MIKROTIK_PASS=your_password
```

---

## Quick Start

```php
use ZillEAli\MikrotikLaravel\Facades\MikroTik;

// PPPoE
$sessions = MikroTik::pppoe()->getActiveSessions();
MikroTik::pppoe()->disableSecret('ali-home');

// Hotspot
$hosts = MikroTik::hotspot()->getActiveHosts();

// System
$cpu = MikroTik::system()->getCpuLoad();

// Queue
MikroTik::queue()->setLimit('ali-home', '10M', '10M');

// Multi-router
MikroTik::router('branch')->pppoe()->getActiveSessions();

// Streaming (v1.7.0)
foreach (MikroTik::pppoe()->streamSecrets() as $secret) {
    // process one row at a time — no memory spike
}

// Testing (v1.7.0)
$fake = MikrotikFake::fake(['/ppp/secret/print' => [['name' => 'ali-home']]]);
$fake->assertQueried('/ppp/secret/print');
```

---

## Contributing

1. Fork the repo
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Write tests first — red then green
4. Submit a PR to the `develop` branch

---

## License

MIT — [Zill E Ali](https://zilleali.com)
