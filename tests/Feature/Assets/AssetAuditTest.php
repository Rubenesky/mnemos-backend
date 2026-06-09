<?php

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Access control ──────────────────────────────────────────────────────────

it('admin can view the audit trail of any asset', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'editor']);
    $asset = Asset::factory()->create(['user_id' => $owner->id]);

    ActivityLog::create([
        'user_id'     => $owner->id,
        'action'      => 'upload',
        'entity_type' => 'Asset',
        'entity_id'   => $asset->id,
        'metadata'    => ['filename' => 'photo.jpg'],
        'ip_address'  => '127.0.0.1',
    ]);

    $this->actingAs($admin, 'sanctum')
         ->getJson("/api/assets/{$asset->id}/audit")
         ->assertOk()
         ->assertJsonStructure(['data' => [['event', 'user', 'timestamp', 'detail']]]);
});

it('editor can view the audit trail of their own asset', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create(['user_id' => $editor->id]);

    ActivityLog::create([
        'user_id'     => $editor->id,
        'action'      => 'upload',
        'entity_type' => 'Asset',
        'entity_id'   => $asset->id,
        'metadata'    => ['filename' => 'doc.pdf'],
        'ip_address'  => '127.0.0.1',
    ]);

    $this->actingAs($editor, 'sanctum')
         ->getJson("/api/assets/{$asset->id}/audit")
         ->assertOk()
         ->assertJsonCount(1, 'data');
});

it('editor cannot view the audit trail of another users asset', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $other  = User::factory()->create(['role' => 'editor']);
    $asset  = Asset::factory()->create(['user_id' => $other->id]);

    $this->actingAs($editor, 'sanctum')
         ->getJson("/api/assets/{$asset->id}/audit")
         ->assertForbidden();
});

it('viewer cannot view any audit trail', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $asset  = Asset::factory()->create();

    $this->actingAs($viewer, 'sanctum')
         ->getJson("/api/assets/{$asset->id}/audit")
         ->assertForbidden();
});

it('unauthenticated request is rejected', function () {
    $asset = Asset::factory()->create();

    $this->getJson("/api/assets/{$asset->id}/audit")
         ->assertUnauthorized();
});

// ── Response structure ──────────────────────────────────────────────────────

it('returns entries in chronological order', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create();

    ActivityLog::create([
        'user_id'     => $admin->id,
        'action'      => 'upload',
        'entity_type' => 'Asset',
        'entity_id'   => $asset->id,
        'metadata'    => ['filename' => 'file.jpg'],
        'ip_address'  => '127.0.0.1',
        'created_at'  => now()->subHours(2),
    ]);

    ActivityLog::create([
        'user_id'     => $admin->id,
        'action'      => 'edit',
        'entity_type' => 'Asset',
        'entity_id'   => $asset->id,
        'metadata'    => null,
        'ip_address'  => '127.0.0.1',
        'created_at'  => now()->subHour(),
    ]);

    $data = $this->actingAs($admin, 'sanctum')
                 ->getJson("/api/assets/{$asset->id}/audit")
                 ->assertOk()
                 ->json('data');

    expect($data[0]['event'])->toBe('upload');
    expect($data[1]['event'])->toBe('edit');
});

it('resolves upload detail from metadata filename', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create();

    ActivityLog::create([
        'user_id'     => $admin->id,
        'action'      => 'upload',
        'entity_type' => 'Asset',
        'entity_id'   => $asset->id,
        'metadata'    => ['filename' => 'report.pdf'],
        'ip_address'  => '127.0.0.1',
    ]);

    $data = $this->actingAs($admin, 'sanctum')
                 ->getJson("/api/assets/{$asset->id}/audit")
                 ->assertOk()
                 ->json('data');

    expect($data[0]['detail'])->toBe('report.pdf');
});

it('returns empty data array when asset has no activity', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create();

    $this->actingAs($admin, 'sanctum')
         ->getJson("/api/assets/{$asset->id}/audit")
         ->assertOk()
         ->assertJson(['data' => []]);
});
