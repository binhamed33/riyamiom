<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\CaseActivity;
use App\Models\CaseChecklistItem;
use App\Models\CaseFolder;
use App\Models\CaseReminder;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use App\Models\HrAttendance;
use App\Models\HrBonus;
use App\Models\HrLeave;
use App\Models\HrLeaveType;
use App\Models\HrPerformance;
use App\Models\HrSalary;
use App\Models\LegalCase;
use App\Models\Notification;
use App\Models\Session as CourtSession;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Support\ClientPortal;
use App\Support\Notify;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * مكتبٌ عُمانيٌّ كامل للعرض — على الموقع التجريبي وحده.
 *
 * ═══ لماذا ═══
 *
 * العرضُ على مكتبٍ فارغ لا يبيع: لوحةٌ بلا أرقام، وتقويمٌ بلا جلسات،
 * وبوابةُ موكّلٍ بلا قضية. هذا الأمر يبني مكتباً يُشبه مكتباً يعمل منذ
 * شهور: طاقمٌ بأدواره، وموكّلون أفراداً وشركات، وقضايا بأنواعها
 * وأحوالها، وجلساتٌ مضت وأخرى قادمة، ومهامٌ وتذكيرات، ومستنداتٌ PDF
 * حقيقية في مجلداتها، وأتعابٌ وفواتيرُ ومصروفات، وحضورٌ وإجازات.
 *
 * ═══ الحارس ═══
 *
 * يعمل حين يُكتب له نطاقُ الموقع حرفياً ويطابق APP_URL — لا افتراضَ
 * ولا تخمين. ومكتبُ الوالد مرفوضٌ بالاسم مهما كُتب. ولا يحذف الأمرُ
 * شيئاً ولا يعدّل قائماً: الموجودُ يُترك، والناقصُ يُضاف، والتشغيلُ
 * الثاني لا يكرّر (كلُّ كيانٍ يُعرف بمفتاحٍ طبيعي).
 *
 * ═══ ولا إشعارَ يخرج ═══
 *
 * البذرُ يجري بلا أحداث Eloquent: المراقبون الذين يُرسلون للموكّلين
 * لا يستيقظون، فلا واتساب ولا بريد يذهب إلى أرقامٍ وعناوينَ وهمية.
 * الخطُّ الزمني للقضايا يُكتب هنا يدوياً بدلاً منهم.
 *
 * ولا كلمةَ مرورٍ في الكود: تُمرَّر أو تُولَّد وتُطبع مرّةً واحدة.
 */
class DemoOfficeSeed extends Command
{
    protected $signature = 'office:demo-seed
        {--site= : نطاق الموقع التجريبي كما في APP_URL — يُكتب حرفياً}
        {--password= : كلمة مرور المستخدمين الجدد (تُولَّد إن تُركت)}
        {--my-phone= : هاتف العارض — يُمنح لموكّل البطل ليرى الإشعارات بنفسه}
        {--cases=18 : عدد القضايا}';

    protected $description = 'يبذر مكتباً تجريبياً كاملاً مترابط البيانات — على النطاق المكتوب له فقط';

    private const PROTECTED = ['office.riyami.om'];

    private const BRAND = [
        'office_name' => 'مكتب النبراس للمحاماة والاستشارات القانونية',
        'office_phone' => '24601234',
        'office_email' => 'info@alnibras-law.om',
        'office_address' => 'مسقط — الخوير، شارع الخوير، بناية رقم 12، الطابق الثالث',
    ];

    /** @var array<int, User> */
    private array $staff = [];

    /** @var array<int, Client> */
    private array $clients = [];

    private ?User $admin = null;

    private int $docSerial = 0;

    private bool $pdfWarned = false;

    private array $counts = [];

    public function handle(): int
    {
        $site = strtolower(trim((string) $this->option('site')));
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($site === '') {
            $this->error('اكتب نطاق الموقع التجريبي حرفياً: --site=testrer.riyami.om');

            return self::FAILURE;
        }

        if (in_array($site, self::PROTECTED, true) || in_array($host, self::PROTECTED, true)) {
            $this->error('مرفوض: هذا مكتبٌ محميّ — بياناتُ إنتاج لا تُلمس.');

            return self::FAILURE;
        }

        if ($host === '' || $site !== $host) {
            $this->error("مرفوض: --site ({$site}) لا يطابق نطاق هذا المكتب (" . ($host ?: 'بلا APP_URL') . ').');

            return self::FAILURE;
        }

        $cases = max(1, min((int) $this->option('cases'), count($this->caseBook())));
        $password = (string) ($this->option('password') ?: Str::password(12, true, true, false));

        // الحاوية تعيد استعمال كائن الأمر نفسه في العملية الواحدة
        // (الاختبارات، أو أمرٌ ينادي أمراً) — فالطاقم كان يتراكم بين تشغيلين
        $this->staff = [];
        $this->clients = [];
        $this->counts = [];
        $this->docSerial = 0;
        $this->admin = null;

        // عشوائيةٌ ثابتة: تشغيلان يعطيان المكتبَ نفسَه
        mt_srand(20260902);

        $this->line('');
        $this->line('<options=bold>بذر مكتب العرض على ' . $host . '</>');

        $created = Model::withoutEvents(function () use ($password, $cases) {
            return DB::transaction(function () use ($password, $cases) {
                $this->seedBrand();
                $createdUsers = $this->seedStaff($password);
                $this->seedClients();
                $this->seedCases($cases);
                $this->seedExpenses();
                $this->seedHr();
                $this->seedNotices();

                return $createdUsers;
            });
        });

        $this->summary($created, $password);

        return self::SUCCESS;
    }

    // ------------------------------------------------------------ الهوية

    private function seedBrand(): void
    {
        $current = (string) Setting::get('office_name', '');

        // الاسمُ الوهميّ وحده يُستبدل — اسمٌ اختاره صاحبُ الموقع يُترك
        if ($current === '' || preg_match('/تجريب|test|demo/iu', $current)) {
            foreach (self::BRAND as $key => $value) {
                Setting::set($key, $value, 'office');
            }
            $this->info('الهوية: ' . self::BRAND['office_name']);
        }

        foreach ([
            ClientPortal::KEY_ENABLED, ClientPortal::KEY_SHOW_SESSIONS, ClientPortal::KEY_SHOW_TIMELINE,
            ClientPortal::KEY_SHOW_DOCUMENTS, ClientPortal::KEY_SHOW_LAWYER, ClientPortal::KEY_SHOW_ACCOUNTING,
        ] as $key) {
            Setting::set($key, '1', 'client_portal');
        }
    }

    // ------------------------------------------------------------ الطاقم

    /** @return array<int, string> البُرُد التي أُنشئت الآن */
    private function seedStaff(string $password): array
    {
        $roster = [
            ['سعيد بن حمد الهنائي', 'saeed@alnibras-law.om', 'admin', '92110045'],
            ['مريم بنت سالم البلوشية', 'maryam@alnibras-law.om', 'lawyer', '92110046'],
            ['خالد بن ناصر العبري', 'khalid@alnibras-law.om', 'lawyer', '92110047'],
            ['أحمد بن سعيد المعمري', 'ahmed@alnibras-law.om', 'staff', '92110048'],
            ['فاطمة بنت علي الحارثية', 'fatma@alnibras-law.om', 'staff', '92110049'],
            ['يوسف بن خميس الرواحي', 'yousuf@alnibras-law.om', 'staff', '92110050'],
        ];

        $created = [];

        foreach ($roster as [$name, $email, $role, $phone]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'phone' => $phone,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            if ($user->wasRecentlyCreated) {
                $created[] = $email;
            }

            $this->staff[] = $user;
        }

        $this->admin = $this->staff[0];
        $this->counts['users'] = count($created);

        return $created;
    }

    private function lawyer(int $i): User
    {
        return $this->staff[1 + ($i % 2)];
    }

    // ---------------------------------------------------------- الموكّلون

    private function seedClients(): void
    {
        $myPhone = preg_replace('/\D+/', '', (string) $this->option('my-phone')) ?: null;

        $book = [
            ['راشد بن سعيد الحبسي', 'individual', $myPhone ?: '99410231', '11834562', 'مسقط — العامرات', 'rashid.habsi@example.com'],
            ['نورة بنت خلفان السيابية', 'individual', '99512804', '12098431', 'مسقط — بوشر', null],
            ['شركة الخليج للمقاولات ش.م.م', 'company', '24512300', '1187754', 'مسقط — غلا الصناعية', 'legal@gulf-contracting.example.com', 'شركة الخليج للمقاولات ش.م.م'],
            ['حمد بن سالم الرواحي', 'individual', '92306711', '10455873', 'نزوى — فرق', null],
            ['مؤسسة الواحة للتجارة', 'company', '26840120', '1256341', 'صحار — الحي التجاري', null, 'مؤسسة الواحة للتجارة'],
            ['عائشة بنت محمد الزدجالية', 'individual', '99870132', '13320654', 'مسقط — الموالح', 'aisha.z@example.com'],
            ['سالم بن علي المقبالي', 'individual', '95233478', '10987213', 'الرستاق — الحزم', null],
            ['شركة مسقط للتطوير العقاري ش.م.ع.م', 'company', '24699870', '1301122', 'مسقط — القرم', 'info@mct-dev.example.com', 'شركة مسقط للتطوير العقاري ش.م.ع.م'],
            ['خالد بن عبدالله البادي', 'individual', '96150089', '11290734', 'صلالة — السعادة', null],
            ['مريم بنت راشد الكندية', 'individual', '99027465', '12755390', 'مسقط — الخوض', null],
            ['شركة الأفق للخدمات اللوجستية ش.م.م', 'company', '24710455', '1412207', 'مسقط — ميناء السلطان قابوس', null, 'شركة الأفق للخدمات اللوجستية ش.م.م'],
            ['ناصر بن حمود الشكيلي', 'individual', '92845610', '10112987', 'إبراء — المضيرب', null],
            ['هدى بنت سعيد الوهيبية', 'individual', '99633021', '13501278', 'مسقط — السيب', 'huda.w@example.com'],
            ['عبدالعزيز بن سيف النبهاني', 'individual', '95778034', '11683425', 'بهلاء — الحمراء', null],
        ];

        $new = 0;

        foreach ($book as $i => $row) {
            // الهويّةُ مشفَّرةٌ في القاعدة، فلا تُطابَق نصّاً بل ببصمتها —
            // وإلا أنشأ كلُّ تشغيلٍ الموكّلين أنفسَهم من جديد
            $client = Client::query()->where('national_id_hash', Client::hashNationalId($row[3]))->first();
            $fresh = $client === null;

            $client ??= Client::create([
                'name' => $row[0],
                'type' => $row[1],
                'phone' => $row[2],
                'address' => $row[4],
                'email' => $row[5] ?? null,
                'company_name' => $row[6] ?? null,
                'national_id' => $row[3],
                'user_id' => $this->lawyer($i)->id,
            ]);

            // هاتفُ العارض يُثبَّت على البطل ولو كان موجوداً من تشغيلٍ سابق
            if ($i === 0 && $myPhone && !$fresh && !str_contains((string) $client->phone, $myPhone)) {
                $client->update(['phone' => $myPhone]);
            }

            $new += $fresh ? 1 : 0;
            $this->clients[] = $client;
        }

        $this->counts['clients'] = $new;
    }

    // ------------------------------------------------------------ القضايا

    /**
     * دفترُ القضايا: كلُّ قضية بعنوانها وخصمها ومحكمتها وحالها.
     *
     * الحقول: [رقم المحكمة، رقم الملف، النوع، العنوان، الوصف، المحكمة،
     * الخصم، صفة الخصم، نوع الخصم، محامي الخصم، الحالة، الأولوية،
     * الموكّل (فهرس)، عمر الملف بالأيام]
     */
    private function caseBook(): array
    {
        return [
            ['م/2026/1187', '1041', 'مدني', 'مطالبة بقيمة أعمال مقاولات متأخرة', 'مطالبة بمبلغ 48,500 ر.ع قيمة أعمال منجزة بموجب عقد مقاولة مؤرخ في 2025/03/12، امتنع المدّعى عليه عن سدادها رغم الإنذار.', 'المحكمة الابتدائية بمسقط — الدائرة المدنية', 'شركة الرمال الذهبية للتجارة', 'defendant', 'company', 'المحامي/ حمود الخروصي', 'active', 'high', 2, 96],
            ['ع/2026/207', '1042', 'عمالي', 'فصل تعسفي ومستحقات نهاية خدمة', 'الموكّل عمل مديراً للمبيعات ثماني سنوات وأُنهيت خدمته دون إنذار أو مبرر، ويطالب بتعويض الفصل ومكافأة نهاية الخدمة وبدل الإجازات.', 'دائرة تسوية المنازعات العمالية — وزارة العمل', 'شركة الشرق الأوسط للتقنية ش.م.م', 'defendant', 'company', null, 'active', 'medium', 0, 74],
            ['ش/2026/94', '1043', 'أحوال شخصية', 'حضانة ونفقة صغار', 'دعوى إثبات حضانة ثلاثة أطفال وتقدير نفقة شهرية شاملة السكن والتعليم بعد الطلاق.', 'المحكمة الابتدائية بمسقط — دائرة الأحوال الشخصية', 'سالم بن خميس المعولي', 'defendant', 'individual', 'المحامية/ عزّة الفارسية', 'active', 'high', 1, 58],
            ['ت/2026/311', '1044', 'تجاري', 'نزاع بين شركاء على أرباح موزّعة', 'خلاف حول توزيع أرباح السنة المالية 2025 ومطالبة بندب خبير حسابي لفحص الدفاتر.', 'المحكمة الابتدائية بمسقط — الدائرة التجارية', 'أحمد بن سعيد الجابري', 'defendant', 'individual', null, 'pending', 'medium', 4, 40],
            ['ج/2026/562', '1045', 'جزائي', 'بلاغ إصدار شيك بدون رصيد', 'شيك بمبلغ 12,000 ر.ع أُعيد لعدم كفاية الرصيد، والموكّل هو المستفيد ويطالب بالحق المدني بجانب الدعوى الجزائية.', 'المحكمة الابتدائية بمسقط — الدائرة الجزائية', 'ماجد بن علي الحارثي', 'defendant', 'individual', 'المحامي/ سيف البوسعيدي', 'active', 'urgent', 6, 33],
            ['ي/2026/143', '1046', 'إيجارات', 'إخلاء عين مؤجرة لعدم سداد الأجرة', 'المستأجر تخلّف عن سداد أجرة ستة أشهر لمحل تجاري بالخوير، والمطلوب الإخلاء والسداد.', 'لجنة فض المنازعات الإيجارية — بلدية مسقط', 'محلات النخيل للأقمشة', 'defendant', 'institution', null, 'adjudicated', 'medium', 7, 150],
            ['إ/2026/58', '1047', 'اداري', 'طعن في قرار إنهاء عقد إداري', 'طعن على قرار جهة حكومية بإنهاء عقد توريد قبل أجله والمطالبة بالتعويض عن الأضرار.', 'محكمة القضاء الإداري', 'الجهة الحكومية المتعاقدة', 'defendant', 'government', null, 'active', 'high', 10, 121],
            ['م/2026/1302', '1048', 'مدني', 'تعويض عن أضرار حادث مروري', 'مطالبة بتعويض عن إصابة جسدية وأضرار مادية لمركبة نتيجة حادث ثبت خطأ الطرف الآخر فيه.', 'المحكمة الابتدائية بصحار', 'شركة التأمين الوطنية', 'defendant', 'company', 'المحامي/ طلال الشيزاوي', 'won', 'medium', 3, 210],
            ['ت/2026/288', '1049', 'تجاري', 'تنفيذ عقد توريد معدات', 'المدّعى عليه سلّم معدات مخالفة للمواصفات المتفق عليها، والمطلوب فسخ العقد واسترداد الدفعة المقدمة.', 'المحكمة الابتدائية بمسقط — الدائرة التجارية', 'شركة الأنوار للمعدات الصناعية', 'defendant', 'company', null, 'active', 'medium', 2, 65],
            ['ش/2026/121', '1050', 'أحوال شخصية', 'قسمة تركة', 'دعوى قسمة تركة عقارية بين الورثة وتصفية نصيب كل وارث وفق الإعلام الشرعي.', 'المحكمة الابتدائية بنزوى', 'ورثة المرحوم سعيد الرواحي', 'defendant', 'individual', null, 'active', 'low', 3, 88],
            ['ع/2026/244', '1051', 'عمالي', 'مطالبة بأجور متأخرة', 'ثلاثة أشهر أجور متأخرة وبدل ساعات إضافية لم تُصرف رغم مطالبات متكررة.', 'دائرة تسوية المنازعات العمالية — وزارة العمل', 'مؤسسة البناء الحديث', 'defendant', 'institution', null, 'closed', 'low', 8, 170],
            ['م/2026/1415', '1052', 'مدني', 'إثبات ملكية أرض سكنية', 'نزاع على ملكية قطعة أرض سكنية بالسيب وتضارب في مستندات التملّك، والمطلوب إثبات الملكية للموكّلة.', 'المحكمة الابتدائية بمسقط — الدائرة المدنية', 'فهد بن حمد السعدي', 'defendant', 'individual', 'المحامي/ منذر الهاشمي', 'active', 'high', 12, 47],
            ['تن/2026/77', '1053', 'تنفيذ مدني', 'تنفيذ حكم نهائي بمبلغ مالي', 'طلب تنفيذ حكم بات بإلزام المنفَّذ ضده بسداد 21,300 ر.ع مع الفوائد القانونية والمصاريف.', 'دائرة التنفيذ — المحكمة الابتدائية بمسقط', 'شركة الرمال الذهبية للتجارة', 'defendant', 'company', null, 'active', 'medium', 2, 29],
            ['ج/2026/610', '1054', 'جزائي', 'دفاع في اتهام بالإهمال الوظيفي', 'الدفاع عن الموكّل في تهمة الإهمال المسبب لضرر، مع طلب ندب خبير فني لإثبات انتفاء الخطأ.', 'المحكمة الابتدائية بمسقط — الدائرة الجزائية', 'الادعاء العام', 'plaintiff', 'government', null, 'pending', 'urgent', 11, 18],
            ['ي/2026/198', '1055', 'إيجارات', 'مطالبة بإعادة مبلغ التأمين', 'المؤجر امتنع عن ردّ مبلغ التأمين بعد انتهاء العقد وتسليم الشقة بحالة سليمة.', 'لجنة فض المنازعات الإيجارية — بلدية مسقط', 'خليفة بن ناصر الغافري', 'defendant', 'individual', null, 'won', 'low', 9, 130],
            ['ت/2026/402', '1056', 'تجاري', 'منازعة بوليصة شحن وبضاعة تالفة', 'وصلت شحنة تالفة بسبب سوء التخزين في الميناء، والمطلوب إلزام الناقل بالتعويض عن قيمة البضاعة.', 'المحكمة الابتدائية بمسقط — الدائرة التجارية', 'شركة البحر الأبيض للشحن', 'defendant', 'company', 'المحامي/ راشد المنذري', 'active', 'medium', 10, 22],
            ['م/2026/1510', '1057', 'مدني', 'مطالبة بأتعاب استشارة هندسية', 'عقد استشارات هندسية نُفّذت مراحله كاملة وامتنع العميل عن سداد الدفعة الأخيرة.', 'المحكمة الابتدائية بمسقط — الدائرة المدنية', 'شركة الديار للاستثمار', 'defendant', 'company', null, 'fees_pending', 'medium', 13, 200],
            ['ق/2026/31', '1058', 'قضاء مستعجل', 'طلب وقف تنفيذ وإثبات حالة', 'طلب مستعجل لإثبات حالة أعمال بناء مخالفة على الحدّ المشترك ووقفها لحين الفصل في الموضوع.', 'المحكمة الابتدائية بمسقط — القضاء المستعجل', 'يعقوب بن سالم العامري', 'defendant', 'individual', null, 'active', 'urgent', 5, 9],
        ];
    }

    private function seedCases(int $limit): void
    {
        $new = 0;

        foreach (array_slice($this->caseBook(), 0, $limit) as $i => $row) {
            [$courtNo, $fileNo, $type, $title, $desc, $court, $opponent, $oppRole, $oppType, $oppLawyer, $status, $priority, $clientIdx, $age] = $row;

            $client = $this->clients[$clientIdx];
            $lawyer = $this->lawyer($i);
            $opened = now()->subDays($age)->setTime(9, 30);

            $case = LegalCase::firstOrCreate(['case_number' => $courtNo], [
                'office_case_number' => $fileNo,
                'case_type' => $type,
                'type' => $type,
                'title' => $title,
                'description' => $desc,
                'court' => $court,
                'opponent' => $opponent,
                'opponent_role' => $oppRole,
                'opponent_type' => $oppType,
                'opponent_lawyer' => $oppLawyer,
                'status' => $status,
                'priority' => $priority,
                'client_id' => $client->id,
                'lawyer_id' => $lawyer->id,
                'created_by' => $this->admin->id,
                'opened_at' => $opened,
            ]);

            if (!$case->wasRecentlyCreated) {
                continue;
            }

            $new++;
            $this->fillCase($case, $client, $lawyer, $opened, $status, $type, $i);
        }

        $this->counts['cases'] = $new;
    }

    /** كلُّ ما يدور حول القضية: جلساتها ومهامها ومستنداتها وخطُّها الزمني ومالُها. */
    private function fillCase(LegalCase $case, Client $client, User $lawyer, Carbon $opened, string $status, string $type, int $i): void
    {
        $done = in_array($status, ['closed', 'won', 'lost', 'adjudicated'], true);
        $room = 'القاعة رقم ' . (1 + $i % 6) . ' — ' . Str::before($case->court, ' —');

        // ── الخط الزمني: الافتتاح
        $this->activity($case, $lawyer, CaseActivity::TYPE_NOTE, 'فتح الملف', 'استلام المستندات من الموكّل وتوقيع الوكالة وفتح ملف القضية.', $opened);
        $this->activity($case, $this->staff[3], CaseActivity::TYPE_CALL, 'اتصال بالموكّل', 'تأكيد بيانات التواصل وشرح خطوات الدعوى والمستندات المطلوبة.', $opened->copy()->addDays(2)->setTime(11, 15));

        // ── الجلسات: ما مضى وما يأتي
        $pastCount = $done ? 3 : ($status === 'pending' ? 0 : 1 + $i % 2);
        $sessionDates = [];

        for ($s = 1; $s <= $pastCount; $s++) {
            $when = $opened->copy()->addDays(14 * $s + $i % 5)->setTime(9, 0);
            if ($when->isFuture()) {
                break;
            }
            $sessionDates[] = $when;

            $report = match ($s) {
                1 => 'حضر وكيل الموكّل وقدّم صحيفة الدعوى ومستنداتها، وطلب وكيل الخصم أجلاً للاطّلاع والرد.',
                2 => 'قدّم الخصم مذكرة الدفاع، وردّ وكيل الموكّل شفهياً وطلب أجلاً لتقديم مذكرة تعقيب.',
                default => 'قرّرت المحكمة حجز الدعوى للحكم بعد اكتمال المرافعة وتبادل المذكرات.',
            };

            CourtSession::create([
                'case_id' => $case->id,
                'date' => $when,
                'location' => $room,
                'status' => ($s === 2 && $i % 4 === 1) ? 'postponed' : 'completed',
                'notes' => 'جلسة رقم ' . $s,
                'report' => $report,
            ]);

            $this->activity($case, $lawyer, CaseActivity::TYPE_SESSION, 'جلسة رقم ' . $s, $report, $when->copy()->addHours(2));
        }

        if (!$done) {
            $next = now()->addDays(2 + ($i * 3) % 13)->setTime(8, 30 + ($i % 2) * 30);
            $sessionDates[] = $next;

            CourtSession::create([
                'case_id' => $case->id,
                'date' => $next,
                'location' => $room,
                'status' => 'upcoming',
                'notes' => $pastCount === 0 ? 'الجلسة الأولى — نظر الدعوى' : 'جلسة المرافعة وتقديم المذكرات',
            ]);

            CaseReminder::create([
                'case_id' => $case->id,
                'title' => 'تجهيز ملف الجلسة القادمة — ' . $case->title,
                'remind_at' => $next->copy()->subDay()->setTime(16, 0),
                'target' => 'both',
            ]);
        }

        // ── المهام
        $taskBook = [
            ['إعداد مذكرة الدفاع وتقديمها للمحكمة', 'high', $lawyer, $done ? 'completed' : 'in_progress'],
            ['تجهيز حافظة المستندات وترقيمها', 'medium', $this->staff[3], 'completed'],
            ['متابعة إعلان الخصم بصحيفة الدعوى', 'medium', $this->staff[4], $done ? 'completed' : ($i % 3 === 0 ? 'completed' : 'pending')],
            ['التواصل مع الموكّل بشأن الرسوم المتبقية', 'low', $this->staff[5], $i % 2 === 0 ? 'pending' : 'completed'],
        ];

        foreach (array_slice($taskBook, 0, 2 + $i % 3) as $t => [$tTitle, $tPriority, $assignee, $tStatus]) {
            $due = $tStatus === 'completed'
                ? $opened->copy()->addDays(5 + $t * 4)
                : now()->addDays(($i + $t) % 9 - 2)->setTime(17, 0); // بعضها متأخر عمداً

            Task::create([
                'title' => $tTitle,
                'description' => 'ضمن قضية «' . $case->title . '» — ' . $case->case_number,
                'case_id' => $case->id,
                'assigned_to' => $assignee->id,
                'created_by' => $this->admin->id,
                'status' => $tStatus,
                'priority' => $tPriority,
                'due_date' => $due,
                'completed_at' => $tStatus === 'completed' ? $due->copy()->subDay()->setTime(14, 20) : null,
            ]);
        }

        // ── قائمة التحقق
        $checklist = ['توقيع الوكالة وتصديقها', 'سداد رسوم قيد الدعوى', 'إعلان الخصم', 'تقديم المستندات الأصلية', 'مراجعة الحكم وتقدير الاستئناف'];
        foreach ($checklist as $c => $item) {
            $isDone = $done || $c < 2 + $i % 2;
            CaseChecklistItem::create([
                'case_id' => $case->id,
                'title' => $item,
                'is_done' => $isDone,
                'done_by' => $isDone ? $this->staff[3]->id : null,
                'done_at' => $isDone ? $opened->copy()->addDays(3 + $c * 2) : null,
                'sort' => $c,
            ]);
        }

        // ── المجلدات والمستندات
        $folders = [];
        foreach (['المستندات الرسمية', 'المرافعات والمذكرات', 'المراسلات'] as $f => $name) {
            $folders[$f] = CaseFolder::create(['case_id' => $case->id, 'name' => $name, 'sort' => $f]);
        }

        $docs = [
            ['وكالة', 'وكالة قانونية — ' . $client->name, 0, true, $opened],
            ['صحيفة دعوى', 'صحيفة دعوى — ' . $case->case_number, 1, true, $opened->copy()->addDays(4)],
        ];
        if ($pastCount >= 1) {
            $docs[] = ['محضر جلسة', 'محضر الجلسة الأولى — ' . $case->case_number, 1, false, $sessionDates[0] ?? $opened->copy()->addDays(15)];
        }
        if ($pastCount >= 2) {
            $docs[] = ['مذكرة دفاع', 'مذكرة دفاع وتعقيب — ' . $case->title, 1, true, $sessionDates[1] ?? $opened->copy()->addDays(30)];
        }
        if ($done) {
            $docs[] = ['حكم', 'الحكم الصادر — ' . $case->case_number, 0, true, $sessionDates[2] ?? now()->subDays(10)];
        }
        if ($i % 3 === 0) {
            $docs[] = ['مراسلة', 'إنذار عدلي للخصم — ' . $case->opponent, 2, false, $opened->copy()->subDays(10)];
        }

        foreach ($docs as [$docType, $docTitle, $folderIdx, $visible, $docDate]) {
            $this->document($case, $client, $lawyer, $folders[$folderIdx], $docType, $docTitle, $visible, $docDate);
        }

        // ── الحالة الختامية في الخط الزمني
        if ($done) {
            $label = match ($status) {
                'won' => 'صدر الحكم لصالح الموكّل',
                'lost' => 'صدر الحكم ضد الموكّل — تُدرس فرص الاستئناف',
                'adjudicated' => 'صدر الحكم في الدعوى',
                default => 'أُغلق الملف بعد إتمام الإجراءات',
            };
            $this->activity($case, $lawyer, CaseActivity::TYPE_STATUS, 'تغيير الحالة', $label, now()->subDays(5 + $i));
        }

        // ── المال: أتعابٌ وفاتورة
        $fee = [100, 350, 500, 800, 1200, 1500, 2000, 2500][$i % 8] + 50 * ($i % 3);
        $feeStatus = ($done || $i % 3 === 0) ? 'paid' : 'unpaid';

        FinanceFee::create([
            'case_id' => $case->id,
            'fee_type' => 'أتعاب محاماة',
            'amount' => $fee,
            'status' => $feeStatus,
            'client_visible' => true,
            'date' => $opened->copy()->addDays(1),
            'description' => 'أتعاب الترافع في الدرجة الأولى — ' . $case->title,
            'user_id' => $this->staff[5]->id,
        ]);

        if ($i % 2 === 0) {
            FinanceFee::create([
                'case_id' => $case->id,
                'fee_type' => 'رسوم قضائية',
                'amount' => [25, 40, 60, 120][$i % 4],
                'status' => 'paid',
                'client_visible' => true,
                'date' => $opened->copy()->addDays(3),
                'description' => 'رسوم قيد الدعوى والإعلان',
                'user_id' => $this->staff[5]->id,
            ]);
        }

        $invoiceStatus = match (true) {
            $feeStatus === 'paid' => 'paid',
            $i % 4 === 1 => 'partial',
            default => 'unpaid',
        };
        $paid = match ($invoiceStatus) {
            'paid' => $fee,
            'partial' => round($fee / 2, 3),
            default => 0,
        };
        $issue = $opened->copy()->addDays(2);
        $due = $issue->copy()->addDays(30); // بعضها تجاوز أجله — يظهر «متأخرة» في اللوحة

        FinanceInvoice::create([
            'invoice_number' => 'INV-' . $issue->format('Y') . '-' . str_pad((string) ($i + 41), 4, '0', STR_PAD_LEFT),
            'client_id' => $client->id,
            'case_id' => $case->id,
            'amount' => $fee,
            'paid_amount' => $paid,
            'status' => $invoiceStatus,
            'client_visible' => true,
            'issue_date' => $issue,
            'due_date' => $due,
            'description' => 'أتعاب محاماة — ' . $case->title,
            'user_id' => $this->staff[5]->id,
        ]);

        if ($paid > 0) {
            FinanceTransaction::create([
                'type' => 'income',
                'category' => 'أتعاب محاماة',
                'amount' => $paid,
                'description' => 'سداد ' . ($invoiceStatus === 'partial' ? 'جزئي ' : '') . 'من ' . $client->name . ' — ' . $case->case_number,
                'date' => $issue->copy()->addDays($invoiceStatus === 'partial' ? 20 : 9),
                'payment_method' => $i % 2 === 0 ? 'تحويل بنكي' : 'نقد',
                'reference' => 'INV-' . $issue->format('Y') . '-' . str_pad((string) ($i + 41), 4, '0', STR_PAD_LEFT),
                'user_id' => $this->staff[5]->id,
            ]);

            $this->activity($case, $this->staff[5], CaseActivity::TYPE_PAYMENT, 'سداد أتعاب', 'استلام ' . number_format($paid, 3) . ' ر.ع من الموكّل.', $issue->copy()->addDays(9));
        }
    }

    private function activity(LegalCase $case, User $user, string $type, string $title, string $content, Carbon $at): void
    {
        CaseActivity::create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'occurred_at' => $at,
        ]);
    }

    // ----------------------------------------------------------- المستندات

    /**
     * مستندٌ PDF حقيقيّ بالعربية على قرص المستندات نفسِه.
     *
     * ملفٌ يُفتح ويُعاين ويُنزَّل كما لو رفعه المكتب — لا صفٌّ يشير إلى
     * ملفٍ غائب. وإن تعذّر مولّد PDF (خطٌّ ناقص في بيئةٍ ما) يُكتب
     * نصٌّ بدلاً منه، فلا يسقط البذرُ كلُّه لمستند.
     */
    private function document(LegalCase $case, Client $client, User $lawyer, CaseFolder $folder, string $docType, string $title, bool $visible, Carbon $date): void
    {
        $this->docSerial++;
        $base = 'documents/demo/' . $case->office_case_number . '-' . $this->docSerial;

        try {
            $bytes = $this->pdf($case, $client, $lawyer, $docType, $title, $date);
            $rel = $base . '.pdf';
            $ext = 'pdf';
        } catch (\Throwable $e) {
            if (!$this->pdfWarned) {
                $this->warn('تعذّر توليد PDF — تُكتب المستندات نصّاً: ' . $e->getMessage());
                $this->pdfWarned = true;
            }
            $bytes = $title . "\n" . $case->case_number . "\n" . $this->body($docType, $case, $client);
            $rel = $base . '.txt';
            $ext = 'txt';
        }

        Storage::disk('private')->put($rel, $bytes);

        // نوعٌ من القائمة نفسِها التي يختار منها المكتب — لا نصٌّ يتيم
        $type = DocumentType::query()->where('name', $docType)->value('name') ?? $docType;

        Document::create([
            'case_id' => $case->id,
            'case_folder_id' => $folder->id,
            'uploaded_by' => $lawyer->id,
            'title' => $title,
            'doc_type' => $type,
            'doc_date' => $date,
            'file_path' => $rel,
            'file_type' => $ext,
            'file_size' => strlen($bytes),
            'access_level' => $visible ? Document::ACCESS_ALL : Document::ACCESS_TEAM,
            'client_visible' => $visible,
        ]);

        $this->activity($case, $lawyer, CaseActivity::TYPE_DOCUMENT, 'إضافة مستند', $title, $date->copy()->setTime(13, 5));
        $this->counts['documents'] = ($this->counts['documents'] ?? 0) + 1;
    }

    private function pdf(LegalCase $case, Client $client, User $lawyer, string $docType, string $title, Carbon $date): string
    {
        // ═══ ثرثرةُ mPDF ليست خطأً ═══
        //
        // يطلق mPDF تحذيراً على خطّ Cairo («contains MarkGlyphSets — Not
        // tested yet») ويُكمل ويُخرج ملفاً سليماً. لكنّ Laravel يحوّل كلَّ
        // تحذيرٍ إلى استثناء، فكان كلُّ مستندٍ يسقط إلى نصٍّ بلا سبب
        // ظاهر. يُكتم التحذيرُ أثناء التوليد وحده ثم يُعاد المعالج.
        set_error_handler(fn () => true, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED);

        try {
            return $this->render($case, $client, $lawyer, $docType, $title, $date);
        } finally {
            restore_error_handler();
        }
    }

    private function render(LegalCase $case, Client $client, User $lawyer, string $docType, string $title, Carbon $date): string
    {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'cairo',
            'fontDir' => [resource_path('fonts')],
            'fontdata' => [
                'cairo' => ['R' => 'Cairo-Regular.ttf', 'B' => 'Cairo-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'biDirectional' => true,
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 18,
            'margin_bottom' => 18,
        ]);
        $mpdf->autoArabic = true;
        $mpdf->SetDirectionality('rtl');

        $office = e((string) Setting::get('office_name', self::BRAND['office_name']));
        $paragraphs = collect(explode("\n", $this->body($docType, $case, $client)))
            ->map(fn ($p) => '<p style="line-height:1.9;margin:0 0 10px">' . e($p) . '</p>')
            ->implode('');

        $html = '<div style="font-family:cairo;font-size:12pt;color:#1f2937">'
            . '<div style="text-align:center;border-bottom:2px solid #b08d57;padding-bottom:8px;margin-bottom:16px">'
            . '<div style="font-size:16pt;font-weight:bold">' . $office . '</div>'
            . '<div style="font-size:10pt;color:#6b7280">سلطنة عُمان — مسقط</div></div>'
            . '<h1 style="font-size:15pt;text-align:center;margin:0 0 14px">' . e($title) . '</h1>'
            . '<table style="width:100%;border-collapse:collapse;font-size:10.5pt;margin-bottom:16px">'
            . '<tr><td style="padding:5px;border:1px solid #e5e7eb;width:25%;background:#f9fafb">رقم القضية</td><td style="padding:5px;border:1px solid #e5e7eb">' . e($case->case_number) . '</td>'
            . '<td style="padding:5px;border:1px solid #e5e7eb;width:25%;background:#f9fafb">رقم الملف</td><td style="padding:5px;border:1px solid #e5e7eb">' . e((string) $case->office_case_number) . '</td></tr>'
            . '<tr><td style="padding:5px;border:1px solid #e5e7eb;background:#f9fafb">الموكّل</td><td style="padding:5px;border:1px solid #e5e7eb">' . e($client->name) . '</td>'
            . '<td style="padding:5px;border:1px solid #e5e7eb;background:#f9fafb">الخصم</td><td style="padding:5px;border:1px solid #e5e7eb">' . e((string) $case->opponent) . '</td></tr>'
            . '<tr><td style="padding:5px;border:1px solid #e5e7eb;background:#f9fafb">المحكمة</td><td style="padding:5px;border:1px solid #e5e7eb">' . e((string) $case->court) . '</td>'
            . '<td style="padding:5px;border:1px solid #e5e7eb;background:#f9fafb">التاريخ</td><td style="padding:5px;border:1px solid #e5e7eb">' . $date->format('Y/m/d') . '</td></tr>'
            . '</table>'
            . $paragraphs
            . '<div style="margin-top:36px;display:flex"><div style="width:50%"></div>'
            . '<div style="text-align:center;width:50%">المحامي/ة<br><b>' . e($lawyer->name) . '</b><br><span style="color:#6b7280;font-size:10pt">' . $office . '</span></div></div>'
            . '<div style="margin-top:40px;text-align:center;font-size:8.5pt;color:#9ca3af">وثيقة تجريبية لأغراض العرض — لا تمثّل إجراءً قانونياً</div>'
            . '</div>';

        // ═══ بلا تشكيل ═══
        //
        // علامةُ شدّةٍ واحدة تُسقط mPDF مع Cairo باستثناءٍ صريح
        // («contains MarkGlyphSets — Not tested yet»: مسارُ العلامات
        // غير مدعوم). والوثيقةُ القانونية تُكتب بلا تشكيلٍ أصلاً، فيُجرَّد
        // النصُّ منه قبل التوليد ويبقى المعنى والشكل.
        $html = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $html) ?? $html;

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    private function body(string $docType, LegalCase $case, Client $client): string
    {
        return match ($docType) {
            'وكالة' => "أقرّ أنا الموقّع أدناه {$client->name} بأنني وكّلت مكتب النبراس للمحاماة والاستشارات القانونية، ممثلاً في محاميه، للحضور عني والترافع والمرافعة أمام جميع المحاكم بدرجاتها ولجان التحكيم والجهات الإدارية في الدعوى المقيدة برقم {$case->case_number}.\nوللوكيل تقديم المذكرات والمستندات والطعون، والإقرار والإنكار والصلح والتنازل، وقبض المبالغ وتوقيع المخالصات، وتوكيل الغير في كل ذلك أو بعضه.\nهذه الوكالة سارية إلى حين انتهاء الدعوى بحكم بات أو إلغائها كتابةً.",
            'صحيفة دعوى' => "السيد الفاضل/ رئيس المحكمة الموقرة\nتحية طيبة وبعد،\nأتقدّم بصفتي وكيلاً عن المدّعي {$client->name} بهذه الصحيفة في مواجهة المدّعى عليه {$case->opponent}.\nالوقائع: {$case->description}\nالأسانيد القانونية: تستند الدعوى إلى أحكام قانون المعاملات المدنية وقانون الإثبات وما استقرّ عليه قضاء المحكمة العليا في هذا الشأن.\nالطلبات: قبول الدعوى شكلاً، وفي الموضوع الحكم بما ورد في الطلبات الختامية مع إلزام المدّعى عليه بالرسوم والمصاريف وأتعاب المحاماة.",
            'محضر جلسة' => "انعقدت الجلسة في المحكمة المبيّنة أعلاه بحضور وكيل المدّعي ووكيل المدّعى عليه.\nقدّم وكيل المدّعي حافظة مستندات وطلب ضمّها، ولم يعترض وكيل المدّعى عليه وطلب أجلاً للاطّلاع والرد.\nقرّرت المحكمة التأجيل لجلسةٍ قادمة تُعلن للطرفين، مع التنبيه بتقديم المذكرات قبلها بأسبوع.",
            'مذكرة دفاع' => "مذكرة بدفاع وطلبات المدّعي {$client->name}\nأولاً: في الشكل — الدعوى مقدّمة في الميعاد ومستوفية شروطها.\nثانياً: في الموضوع — الثابت من المستندات المقدّمة أنّ {$case->description}\nثالثاً: الرد على دفاع الخصم — ما أثاره المدّعى عليه لا يستند إلى دليل، والمستندات المقدّمة قاطعة الدلالة.\nلذلك نلتمس الحكم بالطلبات الواردة في الصحيفة مع إلزام المدّعى عليه بالمصاريف.",
            'حكم' => "باسم جلالة السلطان\nبعد الاطّلاع على الأوراق وسماع المرافعة والمداولة قانوناً:\nحيث إنّ الدعوى استوفت أوضاعها الشكلية فهي مقبولة شكلاً.\nوحيث إنّه بالاطّلاع على المستندات والمذكرات تبيّن للمحكمة ما يلي: {$case->description}\nفلهذه الأسباب حكمت المحكمة بما هو مدوّن في منطوق الحكم، وألزمت الطرف الخاسر بالمصاريف ومقابل أتعاب المحاماة.",
            default => "السادة/ {$case->opponent} المحترمين\nبالإشارة إلى الموضوع أعلاه، نحيطكم علماً بأنّ موكّلنا {$client->name} كلّفنا بمخاطبتكم بشأن ما يلي: {$case->description}\nوعليه نمهلكم مدة خمسة عشر يوماً من تاريخه لتسوية الأمر ودياً، وإلا اضطررنا آسفين لاتخاذ الإجراءات القانونية أمام الجهات المختصة مع تحميلكم كافة الرسوم والمصاريف.",
        };
    }

    // ------------------------------------------------------------ المصروفات

    private function seedExpenses(): void
    {
        if (FinanceTransaction::where('category', 'إيجار المكتب')->exists()) {
            return;
        }

        $monthly = [
            ['إيجار المكتب', 650, 'تحويل بنكي'],
            ['رواتب وأجور', 3400, 'تحويل بنكي'],
            ['كهرباء وماء', 85, 'نقد'],
            ['اشتراكات وبرامج', 45, 'بطاقة'],
            ['قرطاسية ومطبوعات', 60, 'نقد'],
            ['وقود ومواصلات', 120, 'نقد'],
        ];

        for ($m = 5; $m >= 0; $m--) {
            $month = now()->subMonths($m)->startOfMonth();
            foreach ($monthly as $k => [$category, $amount, $method]) {
                FinanceTransaction::create([
                    'type' => 'expense',
                    'category' => $category,
                    'amount' => $amount + ($k === 2 ? ($m * 7) % 30 : 0),
                    'description' => $category . ' — شهر ' . $month->format('Y/m'),
                    'date' => $month->copy()->addDays(2 + $k),
                    'payment_method' => $method,
                    'reference' => null,
                    'user_id' => $this->staff[5]->id,
                ]);
            }

            // دخلٌ من استشاراتٍ خارج القضايا
            FinanceTransaction::create([
                'type' => 'income',
                'category' => 'استشارات قانونية',
                'amount' => 150 + ($m * 50) % 250,
                'description' => 'استشارات مكتبية — شهر ' . $month->format('Y/m'),
                'date' => $month->copy()->addDays(15),
                'payment_method' => 'نقد',
                'user_id' => $this->staff[5]->id,
            ]);
        }
    }

    // --------------------------------------------------------- الموارد البشرية

    private function seedHr(): void
    {
        $salaries = [1800, 1500, 1450, 750, 650, 800];

        foreach ($this->staff as $k => $user) {
            HrSalary::firstOrCreate(['employee_id' => $user->id], [
                'basic_salary' => $salaries[$k],
                'allowances' => (int) ($salaries[$k] * 0.15),
                'effective_from' => now()->startOfYear(),
                'note' => 'راتب أساسي وبدلات سكن ومواصلات',
                'updated_by' => $this->admin->id,
            ]);
        }

        if (HrAttendance::where('note', 'بيانات العرض')->exists()) {
            return;
        }

        // عشرون يومَ عملٍ ماضية (الأحد–الخميس) لكلّ موظف، واليومُ حاضرٌ لبعضهم
        $day = now('Asia/Muscat')->startOfDay();
        $workdays = [];
        while (count($workdays) < 21) {
            if (!in_array($day->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $workdays[] = $day->copy();
            }
            $day->subDay();
        }

        foreach ($this->staff as $k => $user) {
            foreach ($workdays as $d => $date) {
                $isToday = $d === 0;
                if ($isToday && $k % 3 === 2) {
                    continue; // لم يحضر بعد
                }
                if (!$isToday && ($d + $k) % 11 === 0) {
                    continue; // غيابٌ متفرّق
                }

                $in = $date->copy()->setTime(7, 40)->addMinutes(($d * 7 + $k * 11) % 50);
                $out = $isToday ? null : $in->copy()->addHours(7)->addMinutes(20 + ($d * 5 + $k * 3) % 70);

                HrAttendance::create([
                    'user_id' => $user->id,
                    'work_date' => $date->toDateString(),
                    'check_in_at' => $in,
                    'check_out_at' => $out,
                    'minutes' => $out ? (int) $in->diffInMinutes($out) : null,
                    'note' => 'بيانات العرض',
                    'status' => $out ? 'completed' : 'present',
                    'source' => 'manual',
                ]);
            }
        }

        $annual = HrLeaveType::query()->where('code', 'annual')->first();
        HrLeave::create([
            'employee_id' => $this->staff[4]->id,
            'type' => 'annual',
            'leave_type_id' => $annual?->id,
            'start_date' => now()->subDays(24)->toDateString(),
            'end_date' => now()->subDays(22)->toDateString(),
            'days' => 3,
            'reason' => 'ظروف عائلية',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);
        HrLeave::create([
            'employee_id' => $this->staff[3]->id,
            'type' => 'annual',
            'leave_type_id' => $annual?->id,
            'start_date' => now()->addDays(12)->toDateString(),
            'end_date' => now()->addDays(16)->toDateString(),
            'days' => 5,
            'reason' => 'إجازة سنوية',
            'status' => 'pending',
        ]);

        foreach ([[1, 5, 'التزام ممتاز بمواعيد الجلسات وجودة المذكرات'], [2, 4, 'أداء جيد — يُنصح بتطوير مهارات التفاوض']] as [$idx, $rating, $note]) {
            HrPerformance::create([
                'employee_id' => $this->staff[$idx]->id,
                'review_date' => now()->subDays(30)->toDateString(),
                'rating' => $rating,
                'notes' => $note,
                'reviewer_id' => $this->admin->id,
            ]);
        }

        HrBonus::create([
            'employee_id' => $this->staff[1]->id,
            'amount' => 200,
            'reason' => 'كسب قضية التعويض المروري',
            'date' => now()->subDays(20)->toDateString(),
            'given_by' => $this->admin->id,
        ]);
    }

    // ------------------------------------------------------------ الإعلانات

    private function seedNotices(): void
    {
        foreach ([
            'اجتماع فريق العمل الأسبوعي: الثلاثاء الساعة ٩:٠٠ صباحاً في قاعة الاجتماعات — يُرجى تحديث حالة القضايا قبله.',
            'تذكير: إرفاق محضر كل جلسة في ملف القضية في اليوم نفسه، وتحديث الخطّ الزمني للموكّل.',
        ] as $content) {
            Announcement::firstOrCreate(['content' => $content], ['created_by' => $this->admin->id]);
        }

        if (Notification::where('user_id', $this->admin->id)->exists()) {
            return;
        }

        // إشعاراتُ الموظّفين تُحفظ مفاتيحَ لا نصوصاً — فتُقرأ بلغة قارئها
        $next = CourtSession::query()->where('status', 'upcoming')->orderBy('date')->with('case')->first();
        if ($next?->case) {
            Notify::send($this->admin->id, 'app.notif_session_title', 'app.notif_session_body',
                ['case' => $next->case->title, 'date' => $next->date->format('Y/m/d')]);
            Notify::send($this->admin->id, 'app.notif_reminder_title', 'app.notif_reminder_body',
                ['title' => 'تجهيز ملف الجلسة القادمة', 'case' => $next->case->case_number], Notification::TYPE_WARNING);
        }

        $done = Task::query()->where('status', 'completed')->latest('completed_at')->first();
        if ($done) {
            Notify::send($this->admin->id, 'app.notif_task_done_title', 'app.notif_task_done_body', ['task' => $done->title]);
        }
    }

    // -------------------------------------------------------------- الخلاصة

    private function summary(array $createdUsers, string $password): void
    {
        $this->line('');
        $this->info(sprintf(
            'أُضيف الآن: %d مستخدماً · %d موكّلاً · %d قضية · %d مستنداً',
            $this->counts['users'] ?? 0,
            $this->counts['clients'] ?? 0,
            $this->counts['cases'] ?? 0,
            $this->counts['documents'] ?? 0,
        ));
        $this->line(sprintf(
            'الإجمالي في المكتب: %d مستخدماً · %d موكّلاً · %d قضية · %d جلسة · %d مهمة · %d مستنداً · %d فاتورة',
            User::count(), Client::count(), LegalCase::count(), CourtSession::count(),
            Task::count(), Document::count(), FinanceInvoice::count(),
        ));

        if ($createdUsers !== []) {
            $this->line('');
            $this->line('<options=bold>حسابات الطاقم الجديدة</> — كلمة المرور لها جميعاً: <fg=yellow>' . $password . '</>');
            foreach ($createdUsers as $email) {
                $this->line('  · ' . $email);
            }
            $this->line('<fg=gray>(تُطبع مرّةً واحدة ولا تُحفظ في أي ملف — غيّرها من الإعدادات متى شئت)</>');
        }

        $hero = $this->clients[0] ?? null;
        if ($hero) {
            $this->line('');
            $this->line('<options=bold>بوابة الموكّلين للعرض</> — الموكّل ' . $hero->name);
            $this->line('  رقم الهوية: <fg=yellow>' . $hero->national_id . '</>  ·  ثم آخر ثلاثة أرقام من هاتفه: <fg=yellow>' . substr(preg_replace('/\D+/', '', (string) $hero->phone), -3) . '</>');
        }

        $this->line('');
    }
}
