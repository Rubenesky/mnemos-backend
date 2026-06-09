<?php

// RJC

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Feature tests for GET /api/admin/volunteers and PATCH /api/admin/volunteers/{user}/extend.
 */

test('admin can list only volunteer accounts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'volunteer', 'expires_at' => now()->addDays(30)]);
    User::factory()->create(['role' => 'volunteer', 'expires_at' => now()->addDays(5)]);
    User::factory()->create(['role' => 'editor']);
    User::factory()->create(['role' => 'viewer']);

    $response = $this->actingAs($admin)->getJson('/api/admin/volunteers');

    $response->assertOk()->assertJsonCount(2);
    $response->assertJsonStructure([[
        'id', 'name', 'email', 'is_active',
        'expires_at', 'days_remaining', 'assets_count',
    ]]);
});

test('response includes correct days_remaining for future expiry', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'volunteer', 'expires_at' => Carbon::today()->addDays(20)]);

    $response = $this->actingAs($admin)->getJson('/api/admin/volunteers');

    $response->assertOk();
    expect($response->json('0.days_remaining'))->toBe(20);
});

test('days_remaining is null when expires_at is null', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'volunteer', 'expires_at' => null]);

    $response = $this->actingAs($admin)->getJson('/api/admin/volunteers');

    $response->assertOk();
    expect($response->json('0.days_remaining'))->toBeNull();
});

test('days_remaining is zero or negative when already expired', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'volunteer', 'expires_at' => Carbon::today()->subDays(3)]);

    $response = $this->actingAs($admin)->getJson('/api/admin/volunteers');

    $response->assertOk();
    expect($response->json('0.days_remaining'))->toBeLessThanOrEqual(0);
});

test('admin can extend a future expiry by adding days', function () {
    $admin     = User::factory()->create(['role' => 'admin']);
    $volunteer = User::factory()->create([
        'role'       => 'volunteer',
        'expires_at' => Carbon::today()->addDays(10),
    ]);

    $response = $this->actingAs($admin)
                     ->patchJson("/api/admin/volunteers/{$volunteer->id}/extend", ['days' => 20]);

    $response->assertOk()
             ->assertJsonPath('days_remaining', 30);
});

test('extending an expired volunteer resets from today', function () {
    $admin     = User::factory()->create(['role' => 'admin']);
    $volunteer = User::factory()->create([
        'role'       => 'volunteer',
        'expires_at' => Carbon::today()->subDays(5),
    ]);

    $response = $this->actingAs($admin)
                     ->patchJson("/api/admin/volunteers/{$volunteer->id}/extend", ['days' => 14]);

    $response->assertOk()
             ->assertJsonPath('days_remaining', 14);
});

test('extending a volunteer with no expiry sets from today', function () {
    $admin     = User::factory()->create(['role' => 'admin']);
    $volunteer = User::factory()->create(['role' => 'volunteer', 'expires_at' => null]);

    $response = $this->actingAs($admin)
                     ->patchJson("/api/admin/volunteers/{$volunteer->id}/extend", ['days' => 7]);

    $response->assertOk()
             ->assertJsonPath('days_remaining', 7);
});

test('cannot extend a non-volunteer user', function () {
    $admin  = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($admin)
         ->patchJson("/api/admin/volunteers/{$editor->id}/extend", ['days' => 30])
         ->assertStatus(422)
         ->assertJsonPath('message', 'User is not a volunteer.');
});

test('extend validates days range', function () {
    $admin     = User::factory()->create(['role' => 'admin']);
    $volunteer = User::factory()->create(['role' => 'volunteer', 'expires_at' => now()->addDays(10)]);

    $this->actingAs($admin)
         ->patchJson("/api/admin/volunteers/{$volunteer->id}/extend", ['days' => 0])
         ->assertStatus(422);

    $this->actingAs($admin)
         ->patchJson("/api/admin/volunteers/{$volunteer->id}/extend", ['days' => 366])
         ->assertStatus(422);
});

test('non-admin cannot access volunteer endpoints', function () {
    $editor    = User::factory()->create(['role' => 'editor']);
    $volunteer = User::factory()->create(['role' => 'volunteer', 'expires_at' => now()->addDays(10)]);

    $this->actingAs($editor)->getJson('/api/admin/volunteers')->assertForbidden();
    $this->actingAs($editor)
         ->patchJson("/api/admin/volunteers/{$volunteer->id}/extend", ['days' => 30])
         ->assertForbidden();
});

test('unauthenticated request is rejected', function () {
    $this->getJson('/api/admin/volunteers')->assertUnauthorized();
});
