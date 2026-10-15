<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Backend tests do not depend on a developer's compiled asset directory.
        $this->withoutVite();

        // Domain tests isolate their local authorization rules from the SSO transport.
        // Auth integration tests explicitly re-enable this middleware.
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSsoEligibility::class);
    }
}
