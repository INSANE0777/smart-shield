<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent any real HTTP requests in ALL tests
        Http::preventStrayRequests();

        // COMPLETELY disable alerts for all tests - no exceptions
        config([
            'alerts.enabled'              => false,
            'alerts.development.log_only' => true,
            'services.pushover.token'     => null,
            'services.pushover.user'      => null,
        ]);
    }
}
