<?php

namespace Tests;

use App\Support\Portal;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views must not depend on a Vite build (CI runs the suite without one)
        $this->withoutVite();

        // Static / array caches must not leak between tests
        Portal::flush();
        Cache::flush();
    }
}
