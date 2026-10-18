<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';

    /**
     * ما جرى — جملةً تُقرأ: «أُنشئ موكّل «فلان»»، «أُقفل حضور أحمد — بلغ السقف».
     *
     * ═══ ما وقع ═══
     *
     * لوحةُ التحكّم كانت تعرض سطرَ التدقيق بـ$log->description — عمودٌ لا
     * وجود له — فظهرت في «النشاط الأخير» بطاقاتٌ فارغةٌ لا تحمل إلا
     * «منذ 46 دقيقة»: أربعُ إقفالاتِ حضورٍ من مكنسة السقف بلا كلمةٍ عمّا
     * جرى ولا لمن.
     */
    public function describe(): string
    {
        $noun = $this->modelNoun();
        $subject = $this->subjectLabel();
        $quoted = $subject !== '' ? ' «' . $subject . '»' : '';
        // كان الفعلُ مذكّراً أبداً: «أُنشئ جلسة» و«حُذف مهمّة» — فصار يتبع الاسم
        $f = $this->nounIsFeminine() ? '_f' : '';

        return match ($this->actionKey()) {
            self::ACTION_CREATE => __('app.audit_created' . $f, ['what' => $noun]) . $quoted,
            self::ACTION_UPDATE => __('app.audit_updated' . $f, ['what' => $noun]) . $quoted,
            self::ACTION_DELETE => __('app.audit_deleted' . $f, ['what' => $noun]) . $quoted,
            self::ACTION_LOGIN => __('app.audit_login'),
            self::ACTION_LOGOUT => __('app.audit_logout'),
            'attendance_close' => __('app.audit_attendance_close', ['who' => $this->employeeName()]) . $this->closedByReason(),
            'attendance_correct' => __('app.audit_attendance_correct', ['who' => $this->employeeName()]),
            'attendance_add' => __('app.audit_attendance_add', ['who' => $this->employeeName()]),
            'attendance_cancel' => __('app.audit_attendance_cancel', ['who' => $this->employeeName()]),
            // كانت تسقط إلى الفرع الأخير فتُقرأ «preview مستند «…»» و«client
            // portal link opened موكّل «…»» بمفتاحها الإنجليزيّ في لوحةٍ عربيّة
            'preview' => __('app.audit_previewed', ['what' => $noun]) . $quoted,
            'download' => __('app.audit_downloaded', ['what' => $noun]) . $quoted,
            'export' => __('app.audit_exported', ['what' => $this->exportLabel()]),
            'client_portal_link_opened' => __('app.audit_portal_link_opened', ['target' => $this->portalTargetLabel()]),
            'backup_created' => __('app.audit_backup_created'),
            'backup_failed' => __('app.audit_backup_failed'),
            'backup_restored' => __('app.audit_backup_restored'),
            'login_failed_locked' => __('app.audit_login_failed_locked'),
            // صفُّ «إعداد» يكتبه DeveloperSubscriptionController و‎new_values.action‎ فعلُه:
            // كان يُقرأ «أُنشئ إعداد» و«عُدِّل إعداد» بلا كلمةٍ عن الاشتراك
            'activate_subscription' => __('app.audit_subscription_activated'),
            'suspend_subscription' => __('app.audit_subscription_suspended'),
            'reactivate_subscription' => __('app.audit_subscription_reactivated'),
            // ما له اسمٌ في lang (واتساب، الأتمتة، الحضور…) يُقال باسمه؛ وما لا اسمَ
            // له بعدُ يبقى مقروءاً بمفتاحه واسمِ صنفه — لا سطرَ فارغاً أبداً
            default => \Illuminate\Support\Facades\Lang::has('app.audit_action_' . str_replace('.', '_', $this->actionKey()))
                ? trim($this->actionLabel() . $quoted)
                : trim(str_replace('_', ' ', (string) $this->action) . ' ' . $noun . $quoted),
        };
    }

    /** المواعيدُ تكتب الفعلَ بصيغة الماضي — وهو الفعلُ نفسُه. */
    private const ACTION_ALIASES = [
        'created' => self::ACTION_CREATE,
        'updated' => self::ACTION_UPDATE,
        'deleted' => self::ACTION_DELETE,
    ];

    /**
     * أفعالٌ تُكتب في ‎new_values.action‎ فوق «create/update» وهي الفعلُ الحقيقيّ:
     * التصديرُ والنسخُ والاسترجاعُ (بلا صنف)، وتفعيلُ الاشتراك وإيقافُه وإعادتُه
     * (على Setting).
     *
     * قائمةٌ صريحة لا «كلُّ ما بلا صنف»: كان الشرطُ ‎model_type === null‎ فبقيت
     * صفوفُ الاشتراك «أُنشئ إعداد» وفعلُها مخفيٌّ من التفاصيل؛ ومرشّحُ «الإجراء»
     * يطابق هذه القائمةَ نفسَها في SQL (scopeActionIs) فيتّفق مع الشارة.
     */
    public const INNER_ACTIONS = [
        'export', 'backup_created', 'backup_restored',
        'activate_subscription', 'suspend_subscription', 'reactivate_subscription',
    ];

    /**
     * الفعلُ كما يُفهم لا كما كُتب.
     *
     * المواعيدُ تكتب «created/updated/deleted» لا «create/update/delete»،
     * والتصديرُ والاسترجاعُ يكتبان «create/update» وفعلَهما الحقيقيّ في
     * ‎new_values.action‎ — فكان التصديرُ يُقرأ «أُنشئ سجل» والموعدُ
     * «created موعد».
     */
    public function actionKey(): string
    {
        $action = $this->storedActionKey();

        $inner = $this->newValues()['action'] ?? null;
        if (in_array($action, [self::ACTION_CREATE, self::ACTION_UPDATE], true) && in_array($inner, self::INNER_ACTIONS, true)) {
            return $inner;
        }

        return $action;
    }

    /** العمودُ بعد توحيد صيغة الماضي — قبل النظر في ‎new_values.action‎. */
    private function storedActionKey(): string
    {
        return self::ACTION_ALIASES[(string) $this->action] ?? (string) $this->action;
    }

    /**
     * مرشّحُ «الإجراء» بالمعنى الذي تعرضه الشارة (actionKey) لا بالعمود الخام.
     *
     * كان المرشّحُ ‎where('action', …)‎ والشارةُ actionKey(): «إنشاء» يُرجع صفوفاً
     * شارتُها «تصدير» و«نسخ احتياطي»، ويُسقط المواعيدَ المكتوبة «created» وشارتُها
     * «إنشاء». فالمرادفاتُ هنا، وما أخذ اسمَه من ‎new_values.action‎ يُطلب باسمه.
     */
    public function scopeActionIs($query, string $key)
    {
        $stored = array_merge([$key], array_keys(self::ACTION_ALIASES, $key, true));
        $outer = [self::ACTION_CREATE, 'created', self::ACTION_UPDATE, 'updated'];

        if (in_array($key, self::INNER_ACTIONS, true)) {
            return $query->where(fn ($q) => $q->whereIn('action', $stored)
                ->orWhere(fn ($qq) => $qq->whereIn('action', $outer)->where('new_values->action', $key)));
        }

        $query->whereIn('action', $stored);

        if (in_array($key, [self::ACTION_CREATE, self::ACTION_UPDATE], true)) {
            // ‏whereNull أوّلاً: ‎NOT IN‎ على قيمةٍ غائبة NULL فيُسقط الصفَّ العاديّ معها
            $query->where(fn ($q) => $q->whereNull('new_values->action')
                ->orWhereNotIn('new_values->action', self::INNER_ACTIONS));
        }

        return $query;
    }

    /**
     * اسمُ الفعل لشارة سجلّ العمليات: «معاينة» لا «preview»، و«فتح رابط
     * البوابة» لا «client_portal_link_opened».
     */
    public function actionLabel(): string
    {
        $key = 'app.audit_action_' . str_replace('.', '_', $this->actionKey());

        return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : (string) $this->action;
    }

    /**
     * من فعل: المستخدمُ أو «النظام» حين يكون الفاعلُ مكنسةً مجدوَلة.
     *
     * ورابطُ البوابة يفتحه الموكّلُ نفسُه لا النظام: كان يُنسب إلى «نظام»
     * فيقرأ المدير أنّ النظام فتح رابطاً أُرسل إلى موكّل.
     */
    public function actorName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        if ($this->isPortalLinkRow()) {
            // محمَّلٌ سلفاً لصفحةٍ كاملة (primeActorNames)، وإلا فاستعلامٌ لهذا الصفّ وحده
            $name = $this->portalClient?->name;
            if ($name) {
                return $name;
            }
        }

        return __('app.system');
    }

    private function isPortalLinkRow(): bool
    {
        return $this->action === 'client_portal_link_opened' && $this->model_type === Client::class && (bool) $this->model_id;
    }

    /** الموكّلُ الذي فتح رابطَ البوابة — يُقرأ في actorName() لصفوف ذلك الرابط وحدها. */
    public function portalClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'model_id');
    }

    /**
     * أسماءُ موكّلي صفوف «فتح رابط البوابة» باستعلامٍ واحد.
     *
     * كان actorName() يسأل جدولَ الموكّلين لكلّ صفٍّ منها: صفحةُ سجلّ العمليات
     * بعد حملة تذكيرٍ بواتساب خمسةٌ وعشرون استعلاماً زائداً في كلّ عرض، ولوحةُ
     * التحكّم مثلُها في «النشاط الأخير». ولا يُحمَّل هذا بـ‎load()‎: ‏model_id
     * لصفوفٍ أخرى معرّفُ قضيةٍ أو مستند، فيجلب موكّلين لا شأنَ لهم.
     *
     * @param  \Illuminate\Support\Collection<int,self>  $logs
     */
    public static function primeActorNames(\Illuminate\Support\Collection $logs): void
    {
        $rows = $logs->filter(fn (self $log) => !$log->user_id && $log->isPortalLinkRow());

        if ($rows->isEmpty()) {
            return;
        }

        $clients = Client::query()->whereKey($rows->pluck('model_id')->unique()->values()->all())->get(['id', 'name'])->keyBy('id');

        $rows->each(fn (self $log) => $log->setRelation('portalClient', $clients->get($log->model_id)));
    }

    /**
     * القيمُ الجديدة مصفوفةً أبداً — بعضُ الكتّاب (البوابة، النسخ، واتساب)
     * يكتبون JSON نصّاً فوق صبّ «array» فيعود نصّاً.
     *
     * @return array<string,mixed>
     */
    private function newValues(): array
    {
        $values = $this->new_values;

        if (is_string($values)) {
            $decoded = json_decode($values, true);

            return is_array($decoded) ? $decoded : ['description' => $values];
        }

        return is_array($values) ? $values : [];
    }

    /** وجهةُ رابط البوابة بالعربيّة — «المستندات» لا «documents». */
    private function portalTargetLabel(): string
    {
        $target = (string) ($this->newValues()['target'] ?? 'home');
        $key = 'app.audit_portal_target_' . $target;

        return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : __('app.audit_portal_target_home');
    }

    /** ما صُدِّر: «القضايا» لا «cases». */
    private function exportLabel(): string
    {
        $type = (string) ($this->newValues()['type'] ?? 'all');
        $key = 'app.audit_export_' . $type;

        return \Illuminate\Support\Facades\Lang::has($key) ? __($key) : __('app.audit_export_all');
    }

    /** اسمُ الصنف بالعربيّة — وما لم يُعرف فباسم صنفه. */
    public function modelNoun(): string
    {
        $key = 'app.audit_model_' . strtolower(class_basename((string) $this->model_type));
        $label = __($key);

        return $label === $key ? class_basename((string) $this->model_type) : $label;
    }

    /**
     * الأسماءُ المؤنّثة في lang/ar (قضية، مهمّة، جلسة، أتمتة، فاتورة،
     * محادثة) — والإنجليزيّةُ لا تفرّق فمفاتيحُ ‎_f‎ فيها كغيرها.
     */
    private const FEMININE_NOUNS = ['legalcase', 'task', 'session', 'automation', 'financeinvoice', 'whatsappconversation'];

    private function nounIsFeminine(): bool
    {
        return in_array(strtolower(class_basename((string) $this->model_type)), self::FEMININE_NOUNS, true);
    }

    /**
     * حقولٌ لا يقرؤها أحد: مفاتيحُ وطوابعُ يضعها الإطار نفسُه.
     *
     * و‎file_path‎ مسارُ تخزينٍ داخليّ («documents/xyz.pdf») لا اسمُ الملفّ.
     * و‎action‎ ليست هنا: تُسقطها details() متى أخذتها الشارةُ وحدها — كانت
     * هنا لكلّ صفّ فاختفى من صفوف الاشتراك الحقلُ الوحيدُ الذي يقول ما جرى.
     */
    private const NOISE_FIELDS = ['id', 'created_at', 'updated_at', 'deleted_at', 'file_path'];

    /**
     * اسمُ الحقل كما يقرؤه الإنسان، من مفاتيح الترجمة القائمة.
     *
     * عمودُ «التفاصيل» في سجلّ العمليات كان يطبع المفتاحَ نفسَه:
     * «title:» و«case_id:» و«status: overdue» — لغةُ قاعدة البيانات لا لغةُ المكتب.
     */
    private const FIELD_LABELS = [
        'title' => 'app.title', 'name' => 'app.name', 'status' => 'app.status',
        'priority' => 'app.priority', 'court' => 'app.court', 'case_type' => 'app.case_type',
        'type' => 'app.type', 'notes' => 'app.notes', 'description' => 'app.description',
        'location' => 'app.location', 'date' => 'app.date', 'due_date' => 'app.due_date',
        'email' => 'app.email', 'phone' => 'app.phone', 'role' => 'app.role', 'amount' => 'app.amount',
        'case_number' => 'app.case_number', 'office_case_number' => 'app.office_case_number',
        'case_id' => 'app.case', 'client_id' => 'app.case_principal', 'lawyer_id' => 'app.case_lawyer',
        'user_id' => 'app.user', 'employee' => 'app.employee', 'employee_id' => 'app.employee',
        'opponent' => 'app.case_opponent', 'opponent_phone' => 'app.opponent_phone',
        'file_type' => 'app.file_type', 'report' => 'app.session_decision',
        'assigned_to' => 'app.audit_field_assigned_to', 'filename' => 'app.audit_field_filename',
        'reason' => 'app.audit_field_reason', 'is_active' => 'app.audit_field_is_active',
        // «target: home» في تفاصيل رابط البوابة، و«type: cases» في التصدير
        'target' => 'app.audit_field_target', 'size_mb' => 'app.audit_field_size_mb',
        'tables' => 'app.audit_field_tables', 'files' => 'app.audit_field_files',
        'client_visible' => 'app.visible_to_client', 'access_level' => 'app.document_access',
        // صفوفُ الاشتراك: «duration: 12» و«end_at: …» — ‏action حين لا تأخذها الشارة
        'action' => 'app.audit_action', 'duration' => 'app.audit_field_duration',
        'start_at' => 'app.audit_field_start_at', 'end_at' => 'app.audit_field_end_at',
    ];

    public static function fieldLabel(string $field): string
    {
        $key = self::FIELD_LABELS[$field] ?? null;

        return $key && is_string(__($key)) ? __($key) : $field;
    }

    /**
     * القيمةُ كما يقرؤها الإنسان: الحالةُ والأولويةُ بتسميتهما، والتاريخُ
     * بلا «T00:00:00.000000Z»، ونعم/لا بدل 1/0.
     *
     * والخطُّ الزمنيّ للقضية يمرّ من هنا أيضاً: كان يترجم الحالةَ وحدَها
     * فتظهر «الأولوية: high ← medium».
     */
    public static function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('app.yes') : __('app.no');
        }

        if (is_array($value)) {
            return mb_substr(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—', 0, 60);
        }

        $value = (string) $value;

        // ‏«الوجهة: home» — القيمةُ مفتاحٌ له اسمٌ عربيّ. («النوع: cases» في التصدير
        // تترجمه details(): ‏type حقلٌ عامّ — نوعُ موكّلٍ وقضيةٍ ونشاط — لا يُخمَّن هنا)
        if ($field === 'target' && \Illuminate\Support\Facades\Lang::has('app.audit_portal_target_' . $value)) {
            return __('app.audit_portal_target_' . $value);
        }

        // فعلٌ داخل القيم لم تأخذه الشارة — باسمه إن كان له اسم
        if ($field === 'action' && \Illuminate\Support\Facades\Lang::has('app.audit_action_' . str_replace('.', '_', $value))) {
            return __('app.audit_action_' . str_replace('.', '_', $value));
        }

        foreach (['status' => 'app.status_', 'priority' => 'app.priority_'] as $name => $prefix) {
            if ($field === $name && \Illuminate\Support\Facades\Lang::has($prefix . $value)) {
                return __($prefix . $value);
            }
        }

        // ‏«صلاحية الوصول: team» — القيمةُ باسمها في الواجهة
        if ($field === 'access_level' && in_array($value, ['all', 'team', 'private'], true)) {
            return __('app.access_' . ($value === 'all' ? 'public' : $value));
        }

        if ($field === 'is_active' && in_array($value, ['0', '1'], true)) {
            return $value === '1' ? __('app.yes') : __('app.no');
        }

        // «المحامي: 3 ← 5» لا يقول شيئاً: الرقمُ يُقرأ باسم صاحبه
        if (in_array($field, ['lawyer_id', 'assigned_to', 'employee_id', 'user_id'], true) && ctype_digit($value)) {
            return User::query()->whereKey((int) $value)->value('name') ?? $value;
        }

        // ‎toArray()‎ يكتب التاريخ بتوقيت UTC، فيُعاد إلى توقيت المكتب قبل العرض
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $value)) {
            $at = \Illuminate\Support\Carbon::parse($value)->setTimezone(config('app.timezone'));

            return $at->translatedFormat($at->format('H:i') === '00:00' ? 'j F Y' : 'j F Y H:i');
        }

        return mb_substr($value, 0, 60);
    }

    /**
     * ما تغيّر — أزواجاً من اسمٍ وقيمة لعمود «التفاصيل».
     *
     * @return array<int,array{label:string,value:string}>
     */
    public function details(): array
    {
        $rows = [];

        // أوامرُ النسخ الاحتياطي تكتب JSON نصّاً فوق صبّ «array» فيعود نصّاً
        $values = $this->newValues();
        $key = $this->actionKey();
        // ‏action قيلت في الشارة متى صارت هي الفعل (التصدير، الاشتراك…) — وإلا تبقى
        $actionInBadge = $key !== $this->storedActionKey();

        foreach ($values as $field => $value) {
            if (in_array($field, self::NOISE_FIELDS, true) || ($field === 'action' && $actionInBadge)) {
                continue;
            }

            // كان التصديرُ يُقرأ «النوع: cases» بمفتاحه الإنجليزيّ: الاسمُ نفسُه الذي في الجملة
            $shown = $field === 'type' && $key === 'export'
                ? $this->exportLabel()
                : self::formatValue((string) $field, $value);

            $rows[] = ['label' => self::fieldLabel((string) $field), 'value' => $shown];
        }

        return $rows;
    }

    /** عنوانُ الشيء: من القيم المسجَّلة، وإلا من السجلّ إن بقي. */
    private function subjectLabel(): string
    {
        foreach ([$this->new_values, $this->old_values] as $values) {
            foreach (['title', 'name', 'case_number', 'subject', 'filename'] as $field) {
                if (is_array($values) && !empty($values[$field]) && is_string($values[$field])) {
                    return mb_substr($values[$field], 0, 60);
                }
            }
        }

        $class = (string) $this->model_type;

        if ($this->model_id && $class !== '' && class_exists($class) && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
            try {
                $model = $class::query()->find($this->model_id);
            } catch (\Throwable) {
                $model = null;
            }

            foreach (['title', 'name', 'case_number'] as $field) {
                if ($model && !empty($model->{$field}) && is_string($model->{$field})) {
                    return mb_substr($model->{$field}, 0, 60);
                }
            }
        }

        return '';
    }

    private function employeeName(): string
    {
        $id = $this->old_values['employee_id'] ?? null;
        $name = $id ? \App\Models\User::query()->find($id)?->name : null;

        return $name ?: __('app.audit_employee');
    }

    private function closedByReason(): string
    {
        $reason = match ($this->new_values['closed_by'] ?? null) {
            'cap' => __('app.audit_closed_cap'),
            'seen' => __('app.audit_closed_seen'),
            'button' => __('app.audit_closed_button'),
            'manager' => __('app.audit_closed_manager'),
            default => null,
        };

        return $reason ? ' — ' . $reason : '';
    }

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'case_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
