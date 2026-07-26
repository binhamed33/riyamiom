<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cases table - frequently filtered columns
        Schema::table('cases', function (Blueprint $table) {
            $table->index('status');
            $table->index('priority');
            $table->index('next_date');
            $table->index('opened_at');
            $table->index('created_at');
            $table->index(['lawyer_id', 'status']);
            $table->index(['lawyer_id', 'created_at']);
        });

        // court_sessions table - frequently filtered/sorted columns
        Schema::table('court_sessions', function (Blueprint $table) {
            $table->index('date');
            $table->index('status');
            $table->index(['case_id', 'date']);
        });

        // tasks table - frequently filtered columns
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('status');
            $table->index('due_date');
            $table->index('priority');
            $table->index(['assigned_to', 'status']);
            $table->index(['assigned_to', 'due_date']);
        });

        // documents table - access control lookups
        Schema::table('documents', function (Blueprint $table) {
            $table->index('access_level');
            $table->index(['case_id', 'access_level']);
            $table->index('uploaded_by');
        });

        // audit_logs table - audit trail lookups
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('action');
            $table->index('model_type');
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['next_date']);
            $table->dropIndex(['opened_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['lawyer_id', 'status']);
            $table->dropIndex(['lawyer_id', 'created_at']);
        });

        Schema::table('court_sessions', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['case_id', 'date']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['assigned_to', 'due_date']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['access_level']);
            $table->dropIndex(['case_id', 'access_level']);
            $table->dropIndex(['uploaded_by']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropIndex(['model_type']);
            $table->dropIndex(['model_type', 'model_id']);
        });
    }
};
