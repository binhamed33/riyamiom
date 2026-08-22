<?php

use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * تعبئة قائمة الأنواع أول مرة.
 *
 * تُضاف الأنواع المدمجة، ثم — وهذا هو المهم — كل قيمة يحملها مستند
 * قائم فعلاً، حتى لا يختفي نوع من القائمة وتحته مستندات. لا يُعدَّل
 * ولا يُحذف أي مستند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_types')) {
            return;
        }

        try {
            $sort = 10;
            foreach (DocumentType::defaults() as $name) {
                DocumentType::firstOrCreate(
                    ['name' => $name],
                    ['is_active' => true, 'sort' => $sort, 'is_builtin' => true]
                );
                $sort += 10;
            }

            // أنواع يستعملها المكتب فعلاً وليست ضمن المدمجة
            if (Schema::hasColumn('documents', 'doc_type')) {
                Document::query()
                    ->whereNotNull('doc_type')
                    ->where('doc_type', '!=', '')
                    ->distinct()
                    ->pluck('doc_type')
                    ->each(function ($name) {
                        DocumentType::firstOrCreate(
                            ['name' => $name],
                            ['is_active' => true, 'sort' => 500, 'is_builtin' => false]
                        );
                    });
            }
        } catch (\Throwable $e) {
            logger()->warning('document types seed skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // لا تراجع: الجدول نفسه يُحذف في هجرته، ولا مستند تغيّر
    }
};
