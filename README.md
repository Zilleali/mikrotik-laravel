# zilleali/mikrotik-laravel

[![Tests](https://github.com/Zilleali/mikrotik-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/Zilleali/mikrotik-laravel/actions)
[![Packagist](https://img.shields.io/packagist/v/zilleali/mikrotik-laravel)](https://packagist.org/packages/zilleali/mikrotik-laravel)
[![PHP](https://img.shields.io/packagist/php-v/zilleali/mikrotik-laravel)](https://packagist.org/packages/zilleali/mikrotik-laravel)
[![License](https://img.shields.io/github/license/Zilleali/mikrotik-laravel)](https://github.com/Zilleali/mikrotik-laravel/blob/main/LICENSE)
[![MTCNA](https://img.shields.io/badge/MTCNA-Certified-009AC7)](https://zilleali.com)
![Visitors](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fapi.visitorbadge.io%2Fapi%2Fvisitors%3Fpath%3Dhttps%3A%2F%2Fgithub.com%2FZilleali%2Fmikrotik-laravel&query=%24.visitors&label=visitors&color=1D9E75)

> **MikroTik RouterOS API for Laravel** — Manage PPPoE, Hotspot, Queues, Firewall & System health from any Laravel application. Built by an [MTCNA-certified](https://zilleali.com) ISP engineer.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [PPPoE Manager](#pppoe-manager)
- [Hotspot Manager](#hotspot-manager)
- [Queue Manager](#queue-manager)
- [Firewall Manager](#firewall-manager)
- [System Manager](#system-manager)
- [Multi-Router Setup](#multi-router-setup)
- [Events](#events)
- [Filament Integration](#filament-integration)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)

---

## Features

- **PPPoE Management** — secrets, profiles, active sessions, bulk operations
- **Hotspot Management** — users, profiles, active hosts, voucher generation
- **Queue Management** — simple queues, tree queues, bulk bandwidth limits
- **Firewall Management** — filter rules, NAT, mangle, address lists
- **System Management** — resources, health, logs, ping, reboot
- **Multi-Router Support** — manage multiple routers from one app
- **Laravel Facade** — clean `MikroTik::pppoe()->getSecrets()` syntax
- **Auto-disconnect** — socket cleanup via destructor, no leaks
- **RouterOS v6 + v7** — supports both plain and MD5 challenge-response login
- **Filament v3 Widgets** — drop-in dashboard widgets _(coming in v0.3.0)_

## Available Managers

| Manager | Facade Method | Description |
|---|---|---|
| PppoeManager | `MikroTik::pppoe()` | PPPoE secrets, profiles, sessions |
| HotspotManager | `MikroTik::hotspot()` | Hotspot users, vouchers, active hosts |
| QueueManager | `MikroTik::queue()` | Simple/tree queues, bulk limits |
| FirewallManager | `MikroTik::firewall()` | Filter, NAT, mangle, address lists |
| SystemManager | `MikroTik::system()` | Resources, health, logs, ping |
| InterfaceManager | `MikroTik::interfaces()` | Interfaces, traffic, VLANs |
| DhcpManager | `MikroTik::dhcp()` | Leases, servers |
| WirelessManager | `MikroTik::wireless()` | Registration table, access list |
| IpPoolManager | `MikroTik::ipPool()` | IP pools, used addresses |
| RadiusManager | `MikroTik::radius()` | RADIUS servers, incoming CoA |
| RouterUserManager | `MikroTik::routerUsers()` | Router users, groups, sessions |
| VpnManager | `MikroTik::vpn()` | WireGuard, L2TP, PPTP |

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 |
| RouterOS | 6.43+ \| 7.x |
| MikroTik API | Port 8728 (plain) or 8729 (SSL) |

> Make sure the **API service is enabled** on your MikroTik router:
> `IP → Services → api → enabled`

---

## Installation

```bash
composer require zilleali/mikrotik-laravel
```

Publish the config file:

```bash
php artisan vendor:publish --tag=mikrotik-config
```

---

## Configuration

Edit `config/mikrotik.php`:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Default Router Connection
    |--------------------------------------------------------------------------
    | Used when no specific router is selected via MikroTik::router('name')
    */

    'default' => env('MIKROTIK_HOST', '192.168.88.1'),

    'host'     => env('MIKROTIK_HOST',    '192.168.88.1'),
    'port'     => env('MIKROTIK_PORT',    8728),
    'username' => env('MIKROTIK_USER',    'admin'),
    'password' => env('MIKROTIK_PASS',    ''),
    'timeout'  => env('MIKROTIK_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Multiple Routers
    |--------------------------------------------------------------------------
    | Define named routers for multi-site ISP setups.
    | Access via: MikroTik::router('branch')->pppoe()->getSessions()
    */

    'routers' => [
        'main' => [
            'host'     => env('MIKROTIK_MAIN_HOST', '192.168.88.1'),
            'port'     => env('MIKROTIK_MAIN_PORT', 8728),
            'username' => env('MIKROTIK_MAIN_USER', 'admin'),
            'password' => env('MIKROTIK_MAIN_PASS', ''),
            'timeout'  => 10,
        ],
        'branch' => [
            'host'     => env('MIKROTIK_BRANCH_HOST', '10.0.0.1'),
            'port'     => env('MIKROTIK_BRANCH_PORT', 8728),
            'username' => env('MIKROTIK_BRANCH_USER', 'admin'),
            'password' => env('MIKROTIK_BRANCH_PASS', ''),
            'timeout'  => 10,
        ],
    ],

];
```

Add to your `.env`:

```env
MIKROTIK_HOST=192.168.88.1
MIKROTIK_PORT=8728
MIKROTIK_USER=admin
MIKROTIK_PASS=your_password
MIKROTIK_TIMEOUT=10
```

---

## Quick Start

```php
use ZillEAli\MikrotikLaravel\Facades\MikroTik;

// PPPoE — get all active sessions
$sessions = MikroTik::pppoe()->getActiveSessions();

// Hotspot — get active hosts count
$count = count(MikroTik::hotspot()->getActiveHosts());

// System — get CPU load
$cpu = MikroTik::system()->getCpuLoad();

// Queue — set bandwidth limit
MikroTik::queue()->setLimit('ali-home', '10M', '10M');

// Firewall — block an IP
MikroTik::firewall()->addToAddressList('1.2.3.4', 'blocked');
```

---

## PPPoE Manager

### Get secrets (users)

```php
// All secrets
$secrets = MikroTik::pppoe()->getSecrets();

// Single secret by name
$secret = MikroTik::pppoe()->getSecret('ali-home');

// Find by IP address
$user = MikroTik::pppoe()->getSecretByIp('10.0.0.45');
```

### Create / update / delete

```php
// Create new PPPoE user
MikroTik::pppoe()->createSecret([
    'name'     => 'ali-home',
    'password' => 'pass123',
    'service'  => 'pppoe',
    'profile'  => '10mbps',
    'comment'  => 'Ali House Connection',
]);

// Update user
MikroTik::pppoe()->updateSecret('ali-home', [
    'password' => 'newpass',
    'profile'  => '20mbps',
]);

// Delete user
MikroTik::pppoe()->deleteSecret('ali-home');
```

### Enable / disable

```php
MikroTik::pppoe()->enableSecret('ali-home');
MikroTik::pppoe()->disableSecret('ali-home');

// Bulk operations
MikroTik::pppoe()->bulkEnable(['user1', 'user2', 'user3']);
MikroTik::pppoe()->bulkDisable(['user1', 'user2']);
```

### Active sessions

```php
// Get all active sessions
$sessions = MikroTik::pppoe()->getActiveSessions();

// Kick (disconnect) a user
MikroTik::pppoe()->kickSession('ali-home');

// Bulk kick
MikroTik::pppoe()->bulkKick(['user1', 'user2']);
```

### Profiles

```php
// Get all profiles
$profiles = MikroTik::pppoe()->getProfiles();

// Create a profile
MikroTik::pppoe()->createProfile([
    'name'             => '20mbps',
    'rate-limit'       => '20M/20M',
    'session-timeout'  => '30d',
]);
```

---

## Hotspot Manager

### Users

```php
// Get all hotspot users
$users = MikroTik::hotspot()->getUsers();

// Get single user
$user = MikroTik::hotspot()->getUser('guest001');

// Create user
MikroTik::hotspot()->createUser([
    'name'     => 'guest001',
    'password' => 'pass123',
    'profile'  => 'default',
    'comment'  => '1 hour voucher',
]);

// Update user
MikroTik::hotspot()->updateUser('guest001', [
    'profile' => 'premium',
]);

// Delete user
MikroTik::hotspot()->deleteUser('guest001');

// Enable / disable
MikroTik::hotspot()->enableUser('guest001');
MikroTik::hotspot()->disableUser('guest001');
```

### Active hosts

```php
// Get all active hotspot sessions
$hosts = MikroTik::hotspot()->getActiveHosts();

// Kick a host
MikroTik::hotspot()->kickHost('guest001');
```

### Voucher generation

```php
// Generate 10 vouchers with default profile
$vouchers = MikroTik::hotspot()->generateVouchers(10);

// With custom prefix and profile
$vouchers = MikroTik::hotspot()->generateVouchers(
    count:   20,
    profile: 'premium',
    prefix:  'VIP',
);

// Each voucher:
// ['name' => 'VIP3F8A2C', 'password' => 'A1B2C3D4', 'profile' => 'premium']

// Print vouchers or export to PDF
foreach ($vouchers as $voucher) {
    echo "User: {$voucher['name']} | Pass: {$voucher['password']}";
}
```

---

## Queue Manager

### Simple queues

```php
// Get all queues
$queues = MikroTik::queue()->getSimpleQueues();

// Get single queue
$queue = MikroTik::queue()->getSimpleQueue('ali-home');

// Create queue
MikroTik::queue()->createSimpleQueue([
    'name'      => 'ali-home',
    'target'    => '10.0.0.45/32',
    'max-limit' => '10M/10M',
    'comment'   => 'Ali House',
]);

// Update queue
MikroTik::queue()->updateQueue('ali-home', [
    'max-limit' => '20M/20M',
]);

// Delete queue
MikroTik::queue()->deleteQueue('ali-home');

// Enable / disable
MikroTik::queue()->enableQueue('ali-home');
MikroTik::queue()->disableQueue('ali-home');
```

### Bandwidth shortcuts

```php
// Set upload/download limit quickly
MikroTik::queue()->setLimit('ali-home', '10M', '10M');

// Bulk set limits for multiple users
MikroTik::queue()->bulkSetLimit([
    ['name' => 'ali-home',   'ul' => '10M', 'dl' => '10M'],
    ['name' => 'zain-fiber', 'ul' => '20M', 'dl' => '20M'],
    ['name' => 'shop-001',   'ul' => '5M',  'dl' => '5M'],
]);
```

### Tree queues

```php
// Get HTB tree queues
$treeQueues = MikroTik::queue()->getTreeQueues();

// Create tree queue
MikroTik::queue()->createTreeQueue([
    'name'      => 'isp-parent',
    'parent'    => 'global',
    'max-limit' => '100M',
]);
```

---

## Firewall Manager

### Filter rules

```php
// Get all filter rules
$rules = MikroTik::firewall()->getFilterRules();

// Add filter rule
MikroTik::firewall()->addFilterRule([
    'chain'       => 'input',
    'action'      => 'drop',
    'src-address' => '1.2.3.4',
    'comment'     => 'block attacker',
]);
```

### NAT rules

```php
// Get NAT rules
$nat = MikroTik::firewall()->getNatRules();

// Add masquerade rule
MikroTik::firewall()->addNatRule([
    'chain'         => 'srcnat',
    'action'        => 'masquerade',
    'out-interface' => 'ether1',
]);
```

### Mangle rules

```php
// Get mangle rules
$mangle = MikroTik::firewall()->getMangleRules();

// Add mangle rule
MikroTik::firewall()->addMangleRule([
    'chain'            => 'prerouting',
    'action'           => 'mark-connection',
    'new-connection-mark' => 'isp1',
]);
```

### Address lists

```php
// Get all address lists
$lists = MikroTik::firewall()->getAddressLists();

// Get specific list
$blocked = MikroTik::firewall()->getAddressList('blocked');

// Add IP to list
MikroTik::firewall()->addToAddressList('1.2.3.4', 'blocked', 'spam IP');

// Remove IP from list
MikroTik::firewall()->removeFromAddressList('1.2.3.4', 'blocked');

// Check if IP is in list
if (MikroTik::firewall()->isIpInList('1.2.3.4', 'blocked')) {
    echo 'IP is blocked';
}
```

---

## System Manager

### Resources

```php
// Full resource info
$resources = MikroTik::system()->getResources();
// returns: cpu-load, free-memory, total-memory, uptime, version, board-name

// Shortcuts
$cpu    = MikroTik::system()->getCpuLoad();     // int: 0-100
$uptime = MikroTik::system()->getUptime();      // string: "14d6h30m"
$ram    = MikroTik::system()->getFreeMemory();  // int: bytes
$ver    = MikroTik::system()->getVersion();     // string: "7.14.3"
```

### Health

```php
// Hardware health (supported routers only)
$health = MikroTik::system()->getHealth();
// returns: temperature, voltage, fan-speed

$temp = MikroTik::system()->getTemperature(); // int: Celsius or null
```

### Identity & logs

```php
// Router hostname
$name = MikroTik::system()->getIdentity();  // "Main-Router"

// Set identity
MikroTik::system()->setIdentity('Main-Router');

// Get logs (all)
$logs = MikroTik::system()->getLogs();

// Get last 20 logs
$logs = MikroTik::system()->getLogs(20);

// Filter by topic
$pppoe = MikroTik::system()->getLogsByTopic('pppoe', limit: 10);
```

### Ping & reboot

```php
// Ping from the router
$result = MikroTik::system()->ping('8.8.8.8', count: 4);

// Check reachability
if (MikroTik::system()->isReachable('8.8.8.8')) {
    echo 'Internet is up';
}

// Reboot router (WARNING: disconnects all sessions)
MikroTik::system()->reboot();
```

---

## Multi-Router Setup

Manage multiple MikroTik routers from a single Laravel application:

```php
// Default router (from .env)
MikroTik::pppoe()->getActiveSessions();

// Named router
MikroTik::router('branch')->pppoe()->getActiveSessions();
MikroTik::router('main')->system()->getCpuLoad();

// Loop through all routers
$routers = ['main', 'branch'];

foreach ($routers as $router) {
    $sessions = MikroTik::router($router)->pppoe()->getActiveSessions();
    echo "{$router}: " . count($sessions) . " sessions";
}
```

---

## Events

The package dispatches Laravel events you can listen to:

```php
// EventServiceProvider or using #[AsEventListener]
use ZillEAli\MikrotikLaravel\Events\SessionCreated;
use ZillEAli\MikrotikLaravel\Events\SessionDisconnected;
use ZillEAli\MikrotikLaravel\Events\RouterUnreachable;

// Listen for new PPPoE session
Event::listen(SessionCreated::class, function ($event) {
    Log::info("New session: {$event->username} @ {$event->ip}");
});

// Listen for disconnection
Event::listen(SessionDisconnected::class, function ($event) {
    Log::info("Disconnected: {$event->username}");
});

// Listen for router going offline
Event::listen(RouterUnreachable::class, function ($event) {
    // send alert to NOC team
    Notification::send($noc, new RouterDownNotification($event->host));
});
```

---

## Filament Integration

> Available in **v0.3.0** — coming soon.

Register widgets in your Filament panel provider:

```php
use ZillEAli\MikrotikLaravel\Filament\Widgets\ActiveSessionsWidget;
use ZillEAli\MikrotikLaravel\Filament\Widgets\BandwidthChartWidget;
use ZillEAli\MikrotikLaravel\Filament\Widgets\RouterHealthWidget;
use ZillEAli\MikrotikLaravel\Filament\Widgets\InterfaceTableWidget;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            ActiveSessionsWidget::class,   // live PPPoE + hotspot count
            BandwidthChartWidget::class,   // TX/RX line chart
            RouterHealthWidget::class,     // CPU, RAM, temp bars
            InterfaceTableWidget::class,   // interface up/down table
        ]);
}
```

---

## Testing

Run the test suite:

```bash
composer test
```

Or with Pest directly:

```bash
./vendor/bin/pest --no-coverage
```

The package uses **mock RouterOS clients** for all tests — no real router required. All managers are tested with:

- CRUD operations
- Bulk operations
- Edge cases (empty results, not found)
- Exception handling

---

## Changelog

### v0.1.0 — Initial Release

- `RouterosClient` — TCP socket, length encoding, login (v6 + v7)
- `PppoeManager` — secrets, profiles, sessions, bulk ops
- `HotspotManager` — users, profiles, active hosts, vouchers
- `QueueManager` — simple queues, tree queues, bulk limits
- `FirewallManager` — filter, NAT, mangle, address lists
- `SystemManager` — resources, health, logs, ping, reboot
- CI/CD — GitHub Actions (PHP 8.3 / Laravel 12)
- Packagist published

---

## Contributing

Contributions are welcome. Please:

1. Fork the repo
2. Create a feature branch (`git checkout -b feature/wireless-manager`)
3. Write tests first — red then green
4. Submit a PR to `develop` branch

---

## License

MIT — [Zill E Ali](https://zilleali.com)
