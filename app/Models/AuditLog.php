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

        return match ($this->action) {
            self::ACTION_CREATE => __('app.audit_created' . $f, ['what' => $noun]) . $quoted,
            self::ACTION_UPDATE => __('app.audit_updated' . $f, ['what' => $noun]) . $quoted,
            self::ACTION_DELETE => __('app.audit_deleted' . $f, ['what' => $noun]) . $quoted,
            self::ACTION_LOGIN => __('app.audit_login'),
            self::ACTION_LOGOUT => __('app.audit_logout'),
            'attendance_close' => __('app.audit_attendance_close', ['who' => $this->employeeName()]) . $this->closedByReason(),
            'attendance_correct' => __('app.audit_attendance_correct', ['who' => $this->employeeName()]),
            'attendance_add' => __('app.audit_attendance_add', ['who' => $this->employeeName()]),
            'attendance_cancel' => __('app.audit_attendance_cancel', ['who' => $this->employeeName()]),
            default => trim(str_replace('_', ' ', (string) $this->action) . ' ' . $noun . $quoted),
        };
    }

    /** من فعل: المستخدمُ أو «النظام» حين يكون الفاعلُ مكنسةً مجدوَلة. */
    public function actorName(): string
    {
        return $this->user?->name ?? __('app.system');
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

    /** حقولٌ لا يقرؤها أحد: مفاتيحُ وطوابعُ يضعها الإطار نفسُه. */
    private const NOISE_FIELDS = ['id', 'created_at', 'updated_at', 'deleted_at'];

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

        foreach (['status' => 'app.status_', 'priority' => 'app.priority_'] as $name => $prefix) {
            if ($field === $name && \Illuminate\Support\Facades\Lang::has($prefix . $value)) {
                return __($prefix . $value);
            }
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
        $values = $this->new_values;

        // أوامرُ النسخ الاحتياطي تكتب JSON نصّاً فوق صبّ «array» فيعود نصّاً
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : ['description' => $values];
        }

        foreach ((array) $values as $field => $value) {
            if (in_array($field, self::NOISE_FIELDS, true)) {
                continue;
            }

            $rows[] = ['label' => self::fieldLabel((string) $field), 'value' => self::formatValue((string) $field, $value)];
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
