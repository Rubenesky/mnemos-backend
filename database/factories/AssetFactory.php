<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'original_name' => $this->faker->word() . '.jpg',
            'filename'      => Str::uuid() . '.jpg',
            'mime_type'     => 'image/jpeg',
            'size'          => $this->faker->numberBetween(1000, 5000000),
            'path'          => 'assets/' . Str::uuid() . '.jpg',
            'file_hash'     => md5(Str::random(32)),
            'status'        => 'processed',
        ];
    }
}