# zilleali/mikrotik-laravel

[![Tests](https://github.com/Zilleali/mikrotik-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/Zilleali/mikrotik-laravel/actions)
[![Packagist](https://img.shields.io/packagist/v/zilleali/mikrotik-laravel)](https://packagist.org/packages/zilleali/mikrotik-laravel)
[![PHP](https://img.shields.io/packagist/php-v/zilleali/mikrotik-laravel)](https://packagist.org/packages/zilleali/mikrotik-laravel)
[![License](https://img.shields.io/packagist/l/zilleali/mikrotik-laravel)](https://packagist.org/packages/zilleali/mikrotik-laravel)
[![MTCNA](https://img.shields.io/badge/MTCNA-Certified-009AC7)](https://zilleali.com)

## MikroTik RouterOS API for Laravel

Manage PPPoE, Hotspot, Queues, Interfaces & System health
from any Laravel application. Built by an MTCNA-certified
ISP engineer — zilleali.com

## Installation

```bash
composer require zilleali/mikrotik-laravel
php artisan vendor:publish --tag=mikrotik-config
```

## Quick usage

```php
use ZillEAli\MikrotikLaravel\Facades\MikroTik;

$sessions = MikroTik::pppoe()->getActiveSessions();
MikroTik::pppoe()->kickSession('ali-home');
$health   = MikroTik::system()->getResources();
```
