<?php

use ZillEAli\MikrotikLaravel\Commands\MikrotikMonitor;
use ZillEAli\MikrotikLaravel\Commands\MikrotikPing;
use ZillEAli\MikrotikLaravel\Commands\MikrotikSync;

// ─── Command class existence ──────────────────────────────────

it('MikrotikPing command class exists', function () {
    expect(class_exists(MikrotikPing::class))->toBeTrue();
});

it('MikrotikSync command class exists', function () {
    expect(class_exists(MikrotikSync::class))->toBeTrue();
});

it('MikrotikMonitor command class exists', function () {
    expect(class_exists(MikrotikMonitor::class))->toBeTrue();
});

// ─── Command signatures ───────────────────────────────────────

it('MikrotikPing has correct signature', function () {
    $command = new MikrotikPing();

    $reflection = new ReflectionClass($command);
    $signature = $reflection->getProperty('signature');
    $signature->setAccessible(true);

    expect($signature->getValue($command))->toContain('mikrotik:ping');
});

it('MikrotikSync has correct signature', function () {
    $command = new MikrotikSync();

    $reflection = new ReflectionClass($command);
    $signature = $reflection->getProperty('signature');
    $signature->setAccessible(true);

    expect($signature->getValue($command))->toContain('mikrotik:sync');
});

it('MikrotikMonitor has correct signature', function () {
    $command = new MikrotikMonitor();

    $reflection = new ReflectionClass($command);
    $signature = $reflection->getProperty('signature');
    $signature->setAccessible(true);

    expect($signature->getValue($command))->toContain('mikrotik:monitor');
});

// ─── Command descriptions ─────────────────────────────────────

it('MikrotikPing has a description', function () {
    $command = new MikrotikPing();

    $reflection = new ReflectionClass($command);
    $description = $reflection->getProperty('description');
    $description->setAccessible(true);

    expect($description->getValue($command))->not->toBeEmpty();
});

it('MikrotikSync has a description', function () {
    $command = new MikrotikSync();

    $reflection = new ReflectionClass($command);
    $description = $reflection->getProperty('description');
    $description->setAccessible(true);

    expect($description->getValue($command))->not->toBeEmpty();
});

it('MikrotikMonitor has a description', function () {
    $command = new MikrotikMonitor();

    $reflection = new ReflectionClass($command);
    $description = $reflection->getProperty('description');
    $description->setAccessible(true);

    expect($description->getValue($command))->not->toBeEmpty();
});

// ─── Commands extend Laravel Command ──────────────────────────

it('MikrotikPing extends Illuminate Command', function () {
    expect(MikrotikPing::class)
        ->toExtend(\Illuminate\Console\Command::class);
});

it('MikrotikSync extends Illuminate Command', function () {
    expect(MikrotikSync::class)
        ->toExtend(\Illuminate\Console\Command::class);
});

it('MikrotikMonitor extends Illuminate Command', function () {
    expect(MikrotikMonitor::class)
        ->toExtend(\Illuminate\Console\Command::class);
});
