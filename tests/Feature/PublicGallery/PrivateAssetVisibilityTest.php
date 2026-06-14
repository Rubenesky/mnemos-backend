<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('private asset does not appear in public asset listing without authentication', function () {
    $user = User::factory()->create();
    Asset::factory()->create([
        'user_id' => $user->id,
        'is_public' => false,
        'status' => 'processed',
    ]);

    $this->getJson('/api/public/assets')
        ->assertOk()
        ->assertJsonPath('total', 0);
});

test('private asset returns 404 on public single-asset endpoint without authentication', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'is_public' => false,
        'status' => 'processed',
    ]);

    $this->getJson("/api/public/assets/{$asset->id}")
        ->assertNotFound();
});
