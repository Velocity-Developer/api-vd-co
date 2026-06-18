<?php

use Illuminate\Database\Schema\Builder;

test('app service provider sets migration default string length for legacy mysql compatibility', function () {
    expect(Builder::$defaultStringLength)->toBe(191);
});
