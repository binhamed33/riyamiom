<?php

namespace Database\Factories;

use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'case_id' => LegalCase::factory(),
            'assigned_to' => User::factory()->create(['role' => 'lawyer'])->id,
            'created_by' => User::factory()->create(['role' => 'developer'])->id,
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'due_date' => fake()->optional()->date(),
            'completed_at' => null,
        ];
    }
}
