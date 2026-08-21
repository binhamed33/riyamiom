<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // بيئة الاختبار تفترض اشتراكاً فعالاً — سلوك حجب الاشتراك يُختبر
        // صراحةً في اختباراته الخاصة بتجاوز هذه القيم.
        if (Schema::hasTable('settings')) {
            \App\Models\Setting::set('subscription_status', 'active', 'subscription');
            \App\Models\Setting::set('subscription_end_at', now()->addYear()->toDateTimeString(), 'subscription');
        }
    }
}
