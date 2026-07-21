<?php

namespace Database\Factories;

use App\Models\LegalCase;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionFactory extends Factory
{
    protected $model = Session::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d H:i:s'),
            'location' => fake()->address(),
            'status' => fake()->randomElement(['upcoming', 'completed', 'postponed', 'cancelled']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
