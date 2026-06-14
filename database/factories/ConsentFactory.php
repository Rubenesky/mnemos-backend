<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'person_name' => $this->faker->name(),
            'person_email' => $this->faker->optional()->safeEmail(),
            'consent_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'consent_type' => $this->faker->randomElement(['photo', 'video', 'audio', 'general']),
            'status' => 'pending',
            'document_path' => null,
            'notes' => null,
            'token' => null,
            'token_expires_at' => null,
            'responded_at' => null,
        ];
    }
}
