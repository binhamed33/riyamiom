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

        return match ($this->action) {
            self::ACTION_CREATE => __('app.audit_created', ['what' => $noun]) . $quoted,
            self::ACTION_UPDATE => __('app.audit_updated', ['what' => $noun]) . $quoted,
            self::ACTION_DELETE => __('app.audit_deleted', ['what' => $noun]) . $quoted,
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
