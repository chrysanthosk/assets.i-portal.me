<?php

namespace Tests;

use App\Support\Portal;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Static / array caches must not leak between tests
        Portal::flush();
        \Illuminate\Support\Facades\Cache::flush();
    }
}
