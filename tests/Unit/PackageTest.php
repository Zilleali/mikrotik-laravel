<?php

it('loads service provider', function () {
    expect(true)->toBeTrue();
});

it('reads mikrotik config', function () {
    expect(config('mikrotik.port'))->toBe(8728);
});
