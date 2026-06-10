<?php

// RJC

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature tests for GET /api/public/assets — collection-free public gallery endpoint.
 */

test('returns public processed assets without requiring a collection', function () {
    $user = User::factory()->create();
    Asset::factory()->create([
        'user_id'        => $user->id,
        'is_public'      => true,
        'status'         => 'processed',
        'cloudinary_url' => 'https://res.cloudinary.com/test/image.jpg',
    ]);

    $response = $this->getJson('/api/public/assets');

    $response->assertOk()
             ->assertJsonStructure(['org_name', 'data', 'current_page', 'last_page', 'total'])
             ->assertJsonPath('total', 1);
});

test('excludes non-public assets', function () {
    $user = User::factory()->create();
    Asset::factory()->create(['user_id' => $user->id, 'is_public' => false, 'status' => 'processed']);

    $this->getJson('/api/public/assets')->assertOk()->assertJsonPath('total', 0);
});

test('excludes assets not yet processed', function () {
    $user = User::factory()->create();
    Asset::factory()->create(['user_id' => $user->id, 'is_public' => true, 'status' => 'pending']);

    $this->getJson('/api/public/assets')->assertOk()->assertJsonPath('total', 0);
});

test('paginates at 12 per page', function () {
    $user = User::factory()->create();
    Asset::factory()->count(15)->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ]);

    $response = $this->getJson('/api/public/assets');

    $response->assertOk()
             ->assertJsonPath('total', 15)
             ->assertJsonPath('last_page', 2)
             ->assertJsonCount(12, 'data');
});

test('filters by collection slug when provided', function () {
    $user     = User::factory()->create();
    $category = Category::factory()->create(['is_public' => true]);

    $inCollection = Asset::factory()->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ]);
    $inCollection->categories()->attach($category);

    Asset::factory()->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ]);

    $response = $this->getJson("/api/public/assets?collection={$category->slug}");

    $response->assertOk()->assertJsonPath('total', 1);
});

test('collection filter ignores private categories', function () {
    $user     = User::factory()->create();
    $category = Category::factory()->create(['is_public' => false]);

    $asset = Asset::factory()->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ]);
    $asset->categories()->attach($category);

    // Private category filter is ignored — returns all public assets regardless
    $this->getJson("/api/public/assets?collection={$category->slug}")
         ->assertOk()
         ->assertJsonPath('total', 1);
});

test('response includes org_name from settings', function () {
    $this->getJson('/api/public/assets')
         ->assertOk()
         ->assertJsonStructure(['org_name']);
});

test('filters by collection_id when provided', function () {
    $user     = User::factory()->create();
    $category = Category::factory()->create(['is_public' => true]);

    $inCollection = Asset::factory()->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ]);
    $inCollection->categories()->attach($category);

    Asset::factory()->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ]);

    $this->getJson("/api/public/assets?collection_id={$category->id}")
         ->assertOk()
         ->assertJsonPath('total', 1);
});

test('returns 403 when collection_id belongs to a private collection', function () {
    $category = Category::factory()->create(['is_public' => false]);

    $this->getJson("/api/public/assets?collection_id={$category->id}")
         ->assertForbidden();
});

test('returns 404 when collection_id does not exist', function () {
    $this->getJson('/api/public/assets?collection_id=99999')
         ->assertNotFound();
});

test('public collections response includes assets_count', function () {
    $user     = User::factory()->create();
    $category = Category::factory()->create(['is_public' => true]);
    $asset    = Asset::factory()->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ]);
    $asset->categories()->attach($category);

    $this->getJson('/api/public/collections')
         ->assertOk()
         ->assertJsonStructure(['org_name', 'data'])
         ->assertJsonPath('data.0.assets_count', 1);
});
