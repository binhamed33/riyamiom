<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) < 8) {
            $fail('app.password_min_8');
            return;
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $fail('app.password_uppercase');
            return;
        }

        if (!preg_match('/[a-z]/', $value)) {
            $fail('app.password_lowercase');
            return;
        }

        if (!preg_match('/[0-9]/', $value)) {
            $fail('app.password_digit');
            return;
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $value)) {
            $fail('app.password_special');
            return;
        }
    }
}
