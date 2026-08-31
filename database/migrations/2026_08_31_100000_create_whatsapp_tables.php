<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول واتساب — كلُّها داخل قاعدة بيانات هذا المكتب وحده.
 *
 * ═══ لماذا لا يوجد عمود office_id ═══
 *
 * عزلُ المكاتب في مُداوَلة ليس صفّاً في جدولٍ مشترك: كلُّ مكتب نسخةُ
 * تطبيقٍ مستقلّة بقاعدة بياناتها ونطاقها ومفتاح تشفيرها. فمكتبٌ لا
 * يصل إلى بيانات آخر لأنّه لا يملك اتصالاً بقاعدته أصلاً — لا لأنّ
 * استعلاماً يُرشِّح له. وإضافةُ office_id هنا تُوهم بحمايةٍ ليست هي
 * الحماية القائمة، وتفتح بابَ خطأٍ يومَ يظنّ أحدٌ أنّ الترشيح كافٍ.
 *
 * ولذلك أيضاً: الرمز السرّي لواتساب يُخزَّن في settings مشفَّراً
 * بمفتاح هذا المكتب — فلو نُسخ الصفُّ إلى قاعدة مكتبٍ آخر لم يُفكّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        // جهات الاتصال — رقم واتساب واحد لا يتكرّر في هذا المكتب
        if (!Schema::hasTable('whatsapp_contacts')) {
            Schema::create('whatsapp_contacts', function (Blueprint $table) {
                $table->id();
                // wa_id كما يرسله واتساب: أرقام فقط بمفتاح الدولة بلا +
                $table->string('wa_id', 24)->unique();
                $table->string('profile_name', 120)->nullable();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

                // موافقةُ الموكّل على المراسلة ورفضُه لها. الرفض يُحترم
                // مهما قالت الإعدادات: من كتب «إيقاف» لا تصله رسالةُ
                // نظامٍ بعدها، وهو شرطُ Meta وأدبُ المهنة معاً.
                $table->timestamp('opted_in_at')->nullable();
                $table->timestamp('opted_out_at')->nullable();
                $table->timestamps();
            });
        }

        // المحادثات — واحدة لكل جهة اتصال
        if (!Schema::hasTable('whatsapp_conversations')) {
            Schema::create('whatsapp_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->unique()->constrained('whatsapp_contacts')->cascadeOnDelete();
                $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 12)->default('open');

                // آخر رسالةٍ واردة: منها تُحسب نافذة الأربع والعشرين ساعة
                // التي تسمح Meta فيها بالردّ الحرّ. خارجها لا يمرّ إلا
                // قالبٌ معتمَد — وحسابُها من عمودٍ محفوظ أدقُّ وأسرع من
                // مسح جدول الرسائل في كل إرسال.
                $table->timestamp('last_inbound_at')->nullable();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->unsignedInteger('unread_count')->default(0);

                // تحويلٌ إلى موظّف: يوقف الردّ الآلي لهذه المحادثة وحدها
                $table->timestamp('handoff_at')->nullable();
                $table->timestamps();
            });
        }

        // الرسائل
        if (!Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();

                // معرّف الرسالة عند Meta — فريدٌ، وهو مفتاح عدم التكرار
                // حين يُعيد Meta إرسال نفس الإشعار (وهو يفعل).
                $table->string('wamid', 160)->nullable()->unique();
                $table->string('direction', 8);             // in | out
                $table->string('type', 16)->default('text');
                $table->text('body')->nullable();

                $table->string('media_id', 160)->nullable();
                $table->string('media_mime', 120)->nullable();
                $table->string('media_name', 190)->nullable();
                $table->unsignedBigInteger('media_size')->nullable();
                // المستند إذا حُفظ في ملف القضية — الحذف هناك لا يحذف الرسالة
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

                $table->string('status', 12)->default('queued'); // queued|sent|delivered|read|failed
                $table->string('error_code', 32)->nullable();
                $table->string('error_title', 190)->nullable();

                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                // ملاحظةٌ داخلية: تعيش في نفس الخيط ولا تُرسَل أبداً
                $table->boolean('is_internal')->default(false);
                $table->string('template_name', 120)->nullable();

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'id']);
            });
        }

        // القوالب المعتمَدة من Meta — لا تُعتمد من عندنا
        if (!Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('language', 12)->default('ar');
                $table->string('category', 32)->nullable();
                $table->string('status', 16)->default('PENDING');
                $table->text('body')->nullable();
                $table->json('variables')->nullable();
                $table->string('meta_id', 64)->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['name', 'language']);
            });
        }

        // سجلّ أحداث الويبهوك — دفترُ «رأيتُ هذا الحدث» قبل معالجته
        if (!Schema::hasTable('whatsapp_webhook_events')) {
            Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
                $table->id();
                // مفتاحٌ مشتقّ من محتوى الحدث لا من زمن وصوله: الإعادة
                // تحمل نفس المفتاح فتُرفض عند الإدراج بدل أن تُنشئ رسالة
                // ثانية. القيد على قاعدة البيانات لا في الكود — سباقُ
                // عاملَين متزامنين لا يفلت منه.
                $table->string('event_key', 191)->unique();
                $table->string('kind', 24)->default('message');
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index('processed_at');
            });
        }
    }

    public function down(): void
    {
        // ترتيبُ الحذف عكسُ ترتيب الإنشاء — المفاتيح الأجنبية تمنع غيره
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_contacts');
    }
};
