<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalCaseFactory extends Factory
{
    protected $model = LegalCase::class;

    public function definition(): array
    {
        return [
            'case_number' => 'CASE-' . fake()->unique()->numerify('#####'),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['civil', 'criminal', 'family', 'corporate']),
            'court' => fake()->randomElement(['High Court', 'Supreme Court', 'District Court']),
            'opponent' => fake()->name(),
            // الافتراضي حالة جارية: المنجز (closed/won/lost) يُطوى من القوائم
            // افتراضياً، فمصنع يرمي به عشوائياً يجعل الاختبارات قلابة
            'status' => fake()->randomElement(['active', 'pending', 'overdue']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'opened_at' => fake()->date(),
            'next_date' => fake()->optional()->date(),
            'client_id' => Client::factory(),
            'lawyer_id' => User::factory()->create(['role' => 'lawyer'])->id,
        ];
    }
}
