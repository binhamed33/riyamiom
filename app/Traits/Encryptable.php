<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait Encryptable
{
    public static function bootEncryptable(): void
    {
        static::saving(function ($model) {
            $model->encryptAttributes();
        });

        static::saved(function ($model) {
            $model->decryptAttributes();
        });

        static::retrieved(function ($model) {
            $model->decryptAttributes();
        });
    }

    public function getEncryptable(): array
    {
        return property_exists($this, 'encryptable') ? $this->encryptable : [];
    }

    protected function encryptAttributes(): void
    {
        foreach ($this->getEncryptable() as $attribute) {
            $value = $this->getAttribute($attribute);
            if ($value && !str_starts_with($value, 'enc:')) {
                $this->setAttribute($attribute, 'enc:' . Crypt::encryptString($value));
            }
        }
    }

    protected function decryptAttributes(): void
    {
        foreach ($this->getEncryptable() as $attribute) {
            $value = $this->getAttribute($attribute);
            if ($value && str_starts_with($value, 'enc:')) {
                try {
                    $decrypted = Crypt::decryptString(substr($value, 4));
                    $this->attributes[$attribute] = $decrypted;
                } catch (\Exception $e) {
                    $this->attributes[$attribute] = $value;
                }
            }
        }
    }

    public function getRawEncryptedAttribute(string $attribute): ?string
    {
        $value = $this->getAttribute($attribute);
        if ($value && str_starts_with($value, 'enc:')) {
            return substr($value, 4);
        }
        return $value;
    }


}
