<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    const ACCESS_ALL = 'all';
    const ACCESS_TEAM = 'team';
    const ACCESS_PRIVATE = 'private';

    protected $fillable = [
        'case_id',
        'client_id',
        'case_folder_id',
        'uploaded_by',
        'title',
        'doc_type',
        'doc_date',
        'file_path',
        'file_type',
        'file_size',
        'access_level',
        'client_visible',
    ];

    /**
     * ما يراه هذا المستخدم من المستندات.
     *
     * ═══ لماذا نطاقٌ لا شرطٌ مكرّر ═══
     *
     * كانت قائمةُ المستندات تُصفّي بالصلاحية، وصفحةُ القضية وملفُّ
     * القضية PDF تُحمّلان `documents` بلا تصفيةٍ أصلاً. فموظّفٌ يفتح
     * قضيةً لم يعمل فيها يقرأ عناوينَ كلّ مستندٍ «خاصّ» رفعه محامٍ
     * آخر — «طلب طلاق»، «تقرير طبّي» — واسمَ رافعه وتاريخَه. المحتوى
     * كان محميّاً، والعنوانُ وحده يكفي.
     *
     * والقاعدةُ هنا لتُقرأ من موضعٍ واحد: من أضاف قارئاً ثالثاً غداً
     * يجده جاهزاً بدل أن ينسى الشرط.
     */
    public function scopeVisibleTo($query, ?\App\Models\User $user)
    {
        if (!$user) {
            return $query->where('access_level', 'all');
        }

        return $query->where(function ($q) use ($user) {
            $q->where(function ($qq) {
                $qq->where('access_level', 'all')->orWhereNull('access_level');
            });

            if ($user->isAdmin() || $user->isDeveloper() || $user->isLawyer() || $user->isStaff()) {
                $q->orWhere('access_level', 'team');
            }

            $q->orWhere(function ($qq) use ($user) {
                $qq->where('access_level', 'private')->where('uploaded_by', $user->id);
            });
        });
    }

    /** الشخصُ المنسوبُ إليه صراحةً — إن نُسب. */
    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
    }

    /**
     * صاحبُ المستند: المنسوبُ صراحةً، وإلا موكّلُ قضيّته.
     *
     * الترتيبُ مقصود: نسبةٌ كتبها إنسانٌ تعلو نسبةً استنتجها النظام.
     * ومن غيّر قضيةَ المستند لم يفقد الاسمَ الذي كتبه بيده.
     */
    public function ownerClient(): ?\App\Models\Client
    {
        return $this->client ?: $this->case?->client;
    }

    protected function casts(): array
    {
        return [
            'doc_date' => 'date',
            'client_visible' => 'boolean',
        ];
    }

    /** هل يجوز عرضه للعميل؟ شرطان معاً — والخاص لا يُعرض أبداً. */
    public function visibleToClient(): bool
    {
        return (bool) $this->client_visible && $this->access_level !== self::ACCESS_PRIVATE;
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CaseFolder::class, 'case_folder_id');
    }
}
