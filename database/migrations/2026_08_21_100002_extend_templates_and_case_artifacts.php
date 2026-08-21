<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * القوالب الذكية: توسيع قوالب القضايا (قائمة تحقق، مجلدات، تذكيرات،
 * حالة افتراضية) + جداول عناصر القضية الناتجة عنها.
 * أعمدة nullable وجداول جديدة فقط — القوالب والقضايا القائمة لا تتأثر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_templates', function (Blueprint $table) {
            $table->json('checklist')->nullable()->after('items');   // [{title}]
            $table->json('folders')->nullable()->after('checklist'); // [{name}]
            $table->json('reminders')->nullable()->after('folders'); // [{title, days_offset, target}]
            $table->string('default_status')->nullable()->after('reminders');
            $table->boolean('is_active')->default(true)->after('default_status');
            $table->unsignedInteger('usage_count')->default(0)->after('is_active');
            $table->unsignedBigInteger('created_by')->nullable()->after('usage_count');
        });

        Schema::create('case_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedBigInteger('done_by')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('case_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('case_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->string('title');
            $table->timestamp('remind_at');
            $table->string('target')->default('lawyer'); // lawyer | manager | both
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['remind_at', 'notified_at']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('case_folder_id')->nullable()->after('case_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('case_folder_id');
        });
        Schema::dropIfExists('case_reminders');
        Schema::dropIfExists('case_folders');
        Schema::dropIfExists('case_checklist_items');
        Schema::table('case_templates', function (Blueprint $table) {
            $table->dropColumn(['checklist', 'folders', 'reminders', 'default_status', 'is_active', 'usage_count', 'created_by']);
        });
    }
};
