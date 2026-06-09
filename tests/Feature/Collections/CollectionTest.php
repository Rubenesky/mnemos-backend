<?php

// RJC

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Role access
// ─────────────────────────────────────────────────────────────────────────────

it('unauthenticated request is rejected', function () {
    $this->getJson('/api/collections')->assertUnauthorized();
});

it('viewer cannot access collections', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer, 'sanctum')
         ->getJson('/api/collections')
         ->assertForbidden();
});

it('volunteer cannot access collections', function () {
    $volunteer = User::factory()->create(['role' => 'volunteer']);

    $this->actingAs($volunteer, 'sanctum')
         ->getJson('/api/collections')
         ->assertForbidden();
});

// ─────────────────────────────────────────────────────────────────────────────
// Index
// ─────────────────────────────────────────────────────────────────────────────

it('admin can list all collections', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Category::factory()->count(3)->create();

    $this->actingAs($admin, 'sanctum')
         ->getJson('/api/collections')
         ->assertOk()
         ->assertJsonStructure(['success', 'data'])
         ->assertJsonPath('success', true)
         ->assertJsonCount(3, 'data');
});

it('editor can list all collections', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    Category::factory()->count(2)->create();

    $this->actingAs($editor, 'sanctum')
         ->getJson('/api/collections')
         ->assertOk()
         ->assertJsonCount(2, 'data');
});

it('collection list includes assets_count', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $admin->id]);
    $cat->assets()->attach($asset->id);

    $this->actingAs($admin, 'sanctum')
         ->getJson('/api/collections')
         ->assertOk()
         ->assertJsonPath('data.0.assets_count', 1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Store
// ─────────────────────────────────────────────────────────────────────────────

it('admin can create a collection', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
         ->postJson('/api/collections', [
             'name'        => 'Summer Campaign',
             'description' => 'Photos from 2024 campaign',
             'is_public'   => true,
         ])
         ->assertCreated()
         ->assertJsonPath('success', true)
         ->assertJsonPath('data.name', 'Summer Campaign')
         ->assertJsonPath('data.is_public', true);
});

it('editor can create a collection', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor, 'sanctum')
         ->postJson('/api/collections', ['name' => 'Events 2024'])
         ->assertCreated()
         ->assertJsonPath('data.name', 'Events 2024');
});

it('slug is auto-generated from name', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
         ->postJson('/api/collections', ['name' => 'My New Collection'])
         ->assertCreated()
         ->assertJsonPath('data.slug', 'my-new-collection');
});

it('slug is unique when name would produce a duplicate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Category::factory()->create(['name' => 'Events', 'slug' => 'events']);

    $this->actingAs($admin, 'sanctum')
         ->postJson('/api/collections', ['name' => 'Events'])
         ->assertCreated()
         ->assertJsonPath('data.slug', 'events-2');
});

it('name is required when creating', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
         ->postJson('/api/collections', ['description' => 'No name'])
         ->assertUnprocessable();
});

it('is_public defaults to false when omitted', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
         ->postJson('/api/collections', ['name' => 'Private by default'])
         ->assertCreated()
         ->assertJsonPath('data.is_public', false);
});

// ─────────────────────────────────────────────────────────────────────────────
// Show
// ─────────────────────────────────────────────────────────────────────────────

it('admin can view a single collection with its assets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $admin->id]);
    $cat->assets()->attach($asset->id);

    $this->actingAs($admin, 'sanctum')
         ->getJson("/api/collections/{$cat->id}")
         ->assertOk()
         ->assertJsonPath('data.id', $cat->id)
         ->assertJsonCount(1, 'data.assets');
});

it('returns 404 for a non-existent collection', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
         ->getJson('/api/collections/99999')
         ->assertNotFound();
});

// ─────────────────────────────────────────────────────────────────────────────
// Update
// ─────────────────────────────────────────────────────────────────────────────

it('admin can update a collection name and description', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    $this->actingAs($admin, 'sanctum')
         ->patchJson("/api/collections/{$cat->id}", ['name' => 'New Name'])
         ->assertOk()
         ->assertJsonPath('data.name', 'New Name');
});

it('slug is regenerated when name changes on update', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create(['name' => 'Old', 'slug' => 'old']);

    $this->actingAs($admin, 'sanctum')
         ->patchJson("/api/collections/{$cat->id}", ['name' => 'Totally New Name'])
         ->assertOk()
         ->assertJsonPath('data.slug', 'totally-new-name');
});

it('slug is not changed when name stays the same on update', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create(['name' => 'Same', 'slug' => 'same']);

    $this->actingAs($admin, 'sanctum')
         ->patchJson("/api/collections/{$cat->id}", ['description' => 'New desc'])
         ->assertOk()
         ->assertJsonPath('data.slug', 'same');
});

// ─────────────────────────────────────────────────────────────────────────────
// Destroy
// ─────────────────────────────────────────────────────────────────────────────

it('admin can delete a collection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();

    $this->actingAs($admin, 'sanctum')
         ->deleteJson("/api/collections/{$cat->id}")
         ->assertOk()
         ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
});

it('deleting a collection does not delete its assets', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $admin->id]);
    $cat->assets()->attach($asset->id);

    $this->actingAs($admin, 'sanctum')
         ->deleteJson("/api/collections/{$cat->id}")
         ->assertOk();

    $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    $this->assertDatabaseMissing('asset_category', ['category_id' => $cat->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Visibility
// ─────────────────────────────────────────────────────────────────────────────

it('admin can make a private collection public', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create(['is_public' => false]);

    $this->actingAs($admin, 'sanctum')
         ->patchJson("/api/collections/{$cat->id}/visibility")
         ->assertOk()
         ->assertJsonPath('is_public', true);
});

it('admin can make a public collection private', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create(['is_public' => true]);

    $this->actingAs($admin, 'sanctum')
         ->patchJson("/api/collections/{$cat->id}/visibility")
         ->assertOk()
         ->assertJsonPath('is_public', false);
});

// ─────────────────────────────────────────────────────────────────────────────
// Asset membership
// ─────────────────────────────────────────────────────────────────────────────

it('admin can add an asset to a collection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin, 'sanctum')
         ->postJson("/api/collections/{$cat->id}/assets", ['asset_id' => $asset->id])
         ->assertOk()
         ->assertJsonPath('success', true)
         ->assertJsonPath('assets_count', 1);

    $this->assertDatabaseHas('asset_category', [
        'category_id' => $cat->id,
        'asset_id'    => $asset->id,
    ]);
});

it('adding the same asset twice does not create a duplicate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $admin->id]);
    $cat->assets()->attach($asset->id);

    $this->actingAs($admin, 'sanctum')
         ->postJson("/api/collections/{$cat->id}/assets", ['asset_id' => $asset->id])
         ->assertOk()
         ->assertJsonPath('assets_count', 1);
});

it('returns 422 when asset_id does not exist', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();

    $this->actingAs($admin, 'sanctum')
         ->postJson("/api/collections/{$cat->id}/assets", ['asset_id' => 99999])
         ->assertUnprocessable();
});

it('admin can remove an asset from a collection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $admin->id]);
    $cat->assets()->attach($asset->id);

    $this->actingAs($admin, 'sanctum')
         ->deleteJson("/api/collections/{$cat->id}/assets/{$asset->id}")
         ->assertOk()
         ->assertJsonPath('assets_count', 0);

    $this->assertDatabaseMissing('asset_category', [
        'category_id' => $cat->id,
        'asset_id'    => $asset->id,
    ]);
});

it('removing an asset not in the collection does not fail', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $cat   = Category::factory()->create();
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin, 'sanctum')
         ->deleteJson("/api/collections/{$cat->id}/assets/{$asset->id}")
         ->assertOk()
         ->assertJsonPath('assets_count', 0);
});
