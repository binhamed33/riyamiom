<?php

namespace App\Models;

use App\Traits\Encryptable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes, Encryptable;

    protected $fillable = [
        'name',
        'type',
        'phone',
        'email',
        'address',
        'national_id',
        'company_name',
        'user_id',
    ];

    protected $encryptable = [
        'phone',
        'email',
        'address',
        'national_id',
        'company_name',
    ];

    /**
     * البصمة تُحسب لحظة الإسناد، قبل أن يشفّر Encryptable القيمة.
     *
     * الحارس على «enc:» ضروري: سمة التشفير تُعيد إسناد القيمة مشفَّرةً
     * قبل الحفظ، ولولا هذا الشرط لحُسبت البصمة من النص المشفَّر فتتغيّر
     * كل حفظ ويصير الفهرس بلا فائدة.
     */
    public function setNationalIdAttribute(?string $value): void
    {
        $this->attributes['national_id'] = $value;

        if ($value === null || !str_starts_with($value, 'enc:')) {
            $this->attributes['national_id_hash'] = self::hashNationalId($value);
        }
    }

    /**
     * بصمة حتمية مفتاحها مفتاح التطبيق — تخصّ هذا المكتب وحده،
     * ولا تُعكَس إلى رقم الهوية.
     */
    public static function hashNationalId(?string $value): ?string
    {
        $normalized = self::normalizeNationalId($value);

        return $normalized === '' ? null : hash_hmac('sha256', $normalized, config('app.key'));
    }

    /** توحيد الأرقام العربية-الهندية والفواصل قبل أي مقارنة */
    public static function normalizeNationalId(?string $value): string
    {
        $western = strtr(trim((string) $value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return trim(preg_replace('/[\s\-]+/', '', $western) ?? '');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }

    public static function getEncryptedFields(): array
    {
        return (new static)->encryptable ?? [];
    }
}
