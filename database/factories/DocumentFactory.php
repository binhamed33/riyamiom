<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'uploaded_by' => User::factory()->create(['role' => 'developer'])->id,
            'title' => fake()->sentence(3),
            'file_path' => 'documents/' . fake()->uuid() . '.pdf',
            'file_type' => 'pdf',
            'file_size' => fake()->numberBetween(1000, 50000),
            'access_level' => fake()->randomElement(['all', 'team', 'private']),
        ];
    }
}
