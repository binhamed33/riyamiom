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

            // هجرةُ «الباب الواحد» تشغّل إشعاراتِ الموكّل لكل مكتبٍ
            // حقيقي — وهنا تُطفأ قاعدةً: ألفُ اختبارٍ ينشئ قضايا
            // وجلساتٍ لغاياته هو، ولو بقيت مشغّلةً لنبت في كلٍّ منها
            // قناةُ إشعاراتٍ جانبية تكسر عدَّ طوابيره. ومن يختبر
            // الإشعاراتِ نفسَها يشغّلها صراحةً كما في اختباراتها.
            \App\Models\Setting::set(\App\Support\ClientEvents::KEY_MASTER, '0', \App\Support\ClientEvents::GROUP);
        }
    }
}
