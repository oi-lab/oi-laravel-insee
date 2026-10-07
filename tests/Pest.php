<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use OiLab\OiLaravelInsee\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(function () {
        // The limiter lives in the cache: start every test with an empty one,
        // a frozen clock, and sleeps that only move that clock.
        Cache::flush();
        Carbon::setTestNow('2026-10-07 10:00:00');
        Sleep::fake(syncWithCarbon: true);
    })
    ->afterEach(function () {
        Sleep::fake(false);
        Carbon::setTestNow();
    })
    ->in('Unit', 'Feature');
