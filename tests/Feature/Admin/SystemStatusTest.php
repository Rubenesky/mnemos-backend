<?php

// RJC

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Feature tests for GET /api/admin/system/status.
 *
 * The controller uses Http::pool() for Cloudinary and Gemini probes,
 * so tests use Http::fake() instead of service mocks.
 */

test('admin receives all-ok status when all services are healthy', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Http::fake([
        'api.cloudinary.com/*'                => Http::response(['status' => 'ok'], 200),
        'generativelanguage.googleapis.com/*' => Http::response(['models' => []], 200),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/system/status');

    $response->assertOk()
             ->assertJsonStructure([
                 'database'   => ['ok', 'latency_ms', 'error'],
                 'cloudinary' => ['ok', 'latency_ms', 'error'],
                 'gemini'     => ['ok', 'latency_ms', 'error'],
                 'checked_at',
             ])
             ->assertJsonPath('database.ok', true)
             ->assertJsonPath('cloudinary.ok', true)
             ->assertJsonPath('gemini.ok', true);
});

test('admin receives degraded status when cloudinary is down', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Http::fake([
        'api.cloudinary.com/*'                => Http::response(['status' => 'error'], 500),
        'generativelanguage.googleapis.com/*' => Http::response(['models' => []], 200),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/system/status');

    $response->assertOk()
             ->assertJsonPath('database.ok', true)
             ->assertJsonPath('cloudinary.ok', false)
             ->assertJsonPath('gemini.ok', true);
});

test('admin receives degraded status when gemini is down', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Http::fake([
        'api.cloudinary.com/*'                => Http::response(['status' => 'ok'], 200),
        'generativelanguage.googleapis.com/*' => Http::response([], 401),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/system/status');

    $response->assertOk()
             ->assertJsonPath('database.ok', true)
             ->assertJsonPath('cloudinary.ok', true)
             ->assertJsonPath('gemini.ok', false);
});

test('response includes checked_at iso timestamp', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Http::fake([
        'api.cloudinary.com/*'                => Http::response(['status' => 'ok'], 200),
        'generativelanguage.googleapis.com/*' => Http::response(['models' => []], 200),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/admin/system/status');

    $checkedAt = $response->json('checked_at');
    expect($checkedAt)->toBeString();
    expect(strtotime($checkedAt))->toBeGreaterThan(0);
});

test('non-admin cannot access system status', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor)->getJson('/api/admin/system/status')->assertForbidden();
});

test('unauthenticated request is rejected', function () {
    $this->getJson('/api/admin/system/status')->assertUnauthorized();
});
