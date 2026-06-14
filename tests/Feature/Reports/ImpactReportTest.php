<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('admin can download impact report as pdf', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/api/reports/impact');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('editor cannot download impact report', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $response = $this->actingAs($editor)->getJson('/api/reports/impact');

    $response->assertForbidden();
});

test('viewer cannot download impact report', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $response = $this->actingAs($viewer)->getJson('/api/reports/impact');

    $response->assertForbidden();
});

test('unauthenticated user cannot download report', function () {
    $response = $this->getJson('/api/reports/impact');

    $response->assertUnauthorized();
});

test('report reflects actual data in the database', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);
    Asset::factory()->count(3)->create(['user_id' => $admin->id, 'status' => 'processed']);
    Asset::factory()->count(2)->create(['user_id' => $admin->id, 'status' => 'pending']);

    $service = app(\App\Services\ImpactReportService::class);
    $data = $service->gather();

    expect($data['assets']['total'])->toBe(5);
    expect($data['assets']['processed'])->toBe(3);
    expect($data['assets']['pending'])->toBe(2);
    expect($data['users']['admins'])->toBe(1);
    expect($data['users']['editors'])->toBe(1);
});
