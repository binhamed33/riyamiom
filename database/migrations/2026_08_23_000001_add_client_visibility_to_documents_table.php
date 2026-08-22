<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ما يراه العميل من مستندات القضية.
 *
 * access_level القائم (all|team|private) مستويات داخلية للفريق — ولا
 * واحد منها يعني «مسموح للعميل». فيُضاف علَم صريح افتراضه false:
 * لا يُعرض للعميل شيء إلا بقرار من المكتب.
 *
 * عمود جديد قابل للفراغ بقيمة افتراضية — لا يمسّ مستنداً قائماً ولا
 * يحذف بياناً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('documents', 'client_visible')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->boolean('client_visible')->default(false)->after('access_level');
                $table->index(['case_id', 'client_visible'], 'documents_case_client_visible_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'client_visible')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('documents_case_client_visible_index');
                $table->dropColumn('client_visible');
            });
        }
    }
};
