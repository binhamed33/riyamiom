<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve an existing subscription into site-level settings before dropping the multi-tenant tables.
        if (Schema::hasTable('subscriptions')) {
            $sub = DB::table('subscriptions')->orderByDesc('id')->first();

            if ($sub) {
                $status = in_array($sub->status, ['active', 'suspended'], true) ? $sub->status : 'suspended';
                Setting::set('subscription_status', $status, 'subscription');
                Setting::set('subscription_duration', $sub->plan_duration_months, 'subscription');
                Setting::set('subscription_start_at', $sub->start_date, 'subscription');
                Setting::set('subscription_end_at', $sub->end_date, 'subscription');
                Setting::set('subscription_created_at', $sub->created_at, 'subscription');
                Setting::set('subscription_updated_at', now(), 'subscription');
            }
        }

        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');

        if (!Setting::get('subscription_status')) {
            Setting::set('subscription_status', 'none', 'subscription');
            Setting::set('subscription_duration', null, 'subscription');
            Setting::set('subscription_start_at', null, 'subscription');
            Setting::set('subscription_end_at', null, 'subscription');
            Setting::set('subscription_created_at', null, 'subscription');
            Setting::set('subscription_updated_at', null, 'subscription');
        }
    }

    public function down(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedTinyInteger('plan_duration_months')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'suspended', 'terminated', 'expired'])->default('active');
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }
};