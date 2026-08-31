<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // في الإنتاج، الأوامر التي تمسح قاعدة بيانات مكتبٍ حقيقي تُرفض
        // من الإطار نفسه قبل أن تعمل: migrate:fresh وdb:wipe ونظائرها.
        // بيانات المكتب ليست بيئة تجارب، وخطأ إنسان واحد لا يجوز أن
        // يمحو قضايا سنوات.
        \Illuminate\Support\Facades\DB::prohibitDestructiveCommands(
            $this->app->isProduction()
        );

        // المسار الزمني للقضية يُكتب من مراقبي النماذج لا من المتحكّمات:
        // للجلسة والمستند وحالة القضية أكثر من طريق كتابة، وتسجيلٌ في
        // متحكّم واحد يترك بقية الطرق صامتة. المراقب يمسك الكتابة نفسها.
        \App\Models\Session::observe(\App\Observers\SessionObserver::class);
        \App\Models\Document::observe(\App\Observers\DocumentObserver::class);
        \App\Models\LegalCase::observe(\App\Observers\LegalCaseObserver::class);

        // الفاتورة وحدها بلا مراقبٍ قائم — تُراقَب هنا لإشعار الموكّل
        \App\Models\FinanceInvoice::observe(\App\Observers\FinanceInvoiceObserver::class);

        config(['app.timezone' => 'Asia/Muscat']);
        date_default_timezone_set('Asia/Muscat');

        if ($this->app->environment('production') || request()->server('HTTP_X_FORWARDED_PROTO') === 'https' || request()->secure()) {
            URL::forceScheme('https');
        }
    }
}
