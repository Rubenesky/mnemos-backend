<?php

/**
 * Feature tests for the Admin User Management API endpoints.
 *
 * Covers: listing, creating, role changes, deactivation/activation,
 * deletion, self-modification guards, and access control for non-admins.
 */

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can list users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/admin/users');

    $response->assertOk()
        ->assertJsonCount(4);

    $response->assertJsonStructure([['id', 'name', 'email', 'role', 'is_active', 'created_at', 'assets_count']]);
});

test('admin can create user with role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/api/admin/users', [
        'name' => 'New Editor',
        'email' => 'editor@example.com',
        'password' => 'Password1!',
        'role' => 'editor',
    ]);

    $response->assertCreated()
        ->assertJsonPath('role', 'editor')
        ->assertJsonPath('email', 'editor@example.com');

    $this->assertDatabaseHas('users', ['email' => 'editor@example.com', 'role' => 'editor']);
});

test('admin can change role of another user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'viewer']);

    $response = $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'editor']);

    $response->assertOk()
        ->assertJsonPath('role', 'editor');

    $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'editor']);
});

test('admin cannot change own role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$admin->id}/role", ['role' => 'editor']);

    $response->assertForbidden()
        ->assertJsonPath('message', 'You cannot change your own role.');
});

test('admin can deactivate another user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'editor', 'is_active' => true]);

    $response = $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$target->id}/deactivate");

    $response->assertOk()
        ->assertJsonPath('is_active', false);

    $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
});

test('admin cannot deactivate own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$admin->id}/deactivate");

    $response->assertForbidden()
        ->assertJsonPath('message', 'You cannot deactivate your own account.');
});

test('deactivated user cannot login', function () {
    User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => bcrypt('Password1!'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'inactive@example.com',
        'password' => 'Password1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Your account has been deactivated.');
});

test('delete fails with 409 when user has assets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'viewer']);
    Asset::factory()->create(['user_id' => $target->id]);

    $response = $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$target->id}");

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Cannot delete user with associated assets. Reassign or delete their assets first.');
});

test('editor cannot access admin endpoints', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor)->getJson('/api/admin/users')->assertForbidden();
});

// Corrección 2 — last-admin deletion guard

test('cannot delete the last active admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$admin->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Cannot delete the last active admin.');
});

test('can delete an admin when another active admin exists', function () {
    $admin1 = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin2 = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin1)
        ->deleteJson("/api/admin/users/{$admin2->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('users', ['id' => $admin2->id]);
});

// Corrección 3 — last-admin deactivation guard
// Scenario: a stale admin token (role=admin, is_active=false in DB) tries to deactivate
// the only truly active admin. AdminOnly checks role, not is_active.

test('cannot deactivate the last active admin', function () {
    $adminA = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    \Illuminate\Support\Facades\DB::table('users')->where('id', $adminA->id)->update(['is_active' => false]);

    $adminB = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($adminA)
        ->patchJson("/api/admin/users/{$adminB->id}/deactivate")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Cannot deactivate the last active admin.');
});

test('can deactivate an admin when another active admin exists', function () {
    $admin1 = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin2 = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin1)
        ->patchJson("/api/admin/users/{$admin2->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('is_active', false);
});

// ── Protected account guards ──────────────────────────────────────────────────

test('cannot delete a protected user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $protected = User::factory()->create(['role' => 'admin', 'is_active' => true, 'is_protected' => true]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$protected->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Esta cuenta está protegida y no puede eliminarse.');

    $this->assertDatabaseHas('users', ['id' => $protected->id]);
});

test('cannot downgrade role of a protected user via changeRole', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $protected = User::factory()->create(['role' => 'admin', 'is_active' => true, 'is_protected' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$protected->id}/role", ['role' => 'editor'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Esta cuenta está protegida y su rol no puede cambiarse.');

    $this->assertDatabaseHas('users', ['id' => $protected->id, 'role' => 'admin']);
});

test('cannot downgrade role of a protected user via update', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $protected = User::factory()->create(['role' => 'admin', 'is_active' => true, 'is_protected' => true]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$protected->id}", ['role' => 'viewer'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Esta cuenta está protegida y su rol no puede cambiarse.');

    $this->assertDatabaseHas('users', ['id' => $protected->id, 'role' => 'admin']);
});

test('can delete a non-protected admin when another active admin exists', function () {
    $admin1 = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin2 = User::factory()->create(['role' => 'admin', 'is_active' => true, 'is_protected' => false]);

    $this->actingAs($admin1)
        ->deleteJson("/api/admin/users/{$admin2->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('users', ['id' => $admin2->id]);
});

test('can change role of a non-protected user', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $target = User::factory()->create(['role' => 'editor', 'is_active' => true, 'is_protected' => false]);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'viewer'])
        ->assertOk()
        ->assertJsonPath('role', 'viewer');
});

test('protected field is included in user list response', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'editor', 'is_protected' => true]);

    $this->actingAs($admin)
        ->getJson('/api/admin/users')
        ->assertOk()
        ->assertJsonStructure([['id', 'name', 'email', 'role', 'is_active', 'is_protected', 'assets_count']]);
});
