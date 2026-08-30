<?php

namespace Tests;

use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * The subscription gate fronts every authenticated route and locks out anyone
     * who is not a developer when no subscription is configured, so without this
     * every feature test would redirect to the expired page instead of rendering.
     * The system therefore starts licensed; subscription tests overwrite this with
     * whatever state they are actually exercising.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('settings')) {
            app(SubscriptionService::class)->activate(12);
        }
    }
}
