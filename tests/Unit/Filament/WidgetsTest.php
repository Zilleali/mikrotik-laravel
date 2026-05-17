<?php

use ZillEAli\MikrotikLaravel\Filament\Widgets\ActiveSessionsWidget;
use ZillEAli\MikrotikLaravel\Filament\Widgets\BandwidthChartWidget;
use ZillEAli\MikrotikLaravel\Filament\Widgets\InterfaceTableWidget;
use ZillEAli\MikrotikLaravel\Filament\Widgets\RouterHealthWidget;

// ─── Class existence ──────────────────────────────────────────

it('ActiveSessionsWidget class exists', function () {
    expect(class_exists(ActiveSessionsWidget::class))->toBeTrue();
});

it('BandwidthChartWidget class exists', function () {
    expect(class_exists(BandwidthChartWidget::class))->toBeTrue();
});

it('RouterHealthWidget class exists', function () {
    expect(class_exists(RouterHealthWidget::class))->toBeTrue();
});

it('InterfaceTableWidget class exists', function () {
    expect(class_exists(InterfaceTableWidget::class))->toBeTrue();
});

// ─── Headings ─────────────────────────────────────────────────

it('ActiveSessionsWidget has correct heading', function () {
    $widget = new ActiveSessionsWidget();
    expect($widget->heading)->toBe('Active Sessions');
});

it('RouterHealthWidget has correct heading', function () {
    $widget = new RouterHealthWidget();
    expect($widget->heading)->toBe('Router Health');
});

it('BandwidthChartWidget has correct heading', function () {
    $widget = new BandwidthChartWidget();
    expect($widget->heading)->toBe('Bandwidth — ether1 (TX / RX)');
});

it('InterfaceTableWidget has correct heading', function () {
    $widget = new InterfaceTableWidget();
    expect($widget->heading)->toBe('Interfaces');
});

// ─── Polling intervals ────────────────────────────────────────

it('ActiveSessionsWidget polling is 30s', function () {
    $widget = new ActiveSessionsWidget();
    expect($widget->pollingInterval)->toBe('30s');
});

it('RouterHealthWidget polling is 60s', function () {
    $widget = new RouterHealthWidget();
    expect($widget->pollingInterval)->toBe('60s');
});

it('BandwidthChartWidget polling is 30s', function () {
    $widget = new BandwidthChartWidget();
    expect($widget->pollingInterval)->toBe('30s');
});

it('InterfaceTableWidget polling is 60s', function () {
    $widget = new InterfaceTableWidget();
    expect($widget->pollingInterval)->toBe('60s');
});

// ─── Interface ────────────────────────────────────────────────

it('BandwidthChartWidget default interface is ether1', function () {
    $widget = new BandwidthChartWidget();
    expect($widget->interface)->toBe('ether1');
});

it('BandwidthChartWidget returns line chart type', function () {
    $widget = new BandwidthChartWidget();
    expect($widget->getType())->toBe('line');
});
