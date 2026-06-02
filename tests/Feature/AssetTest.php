<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    // Test 1: usuario no autenticado no puede ver assets
    public function test_unauthenticated_user_cannot_access_assets(): void
    {
        $response = $this->get('/assets');
        $response->assertRedirect('/login');
    }

    // Test 2: usuario autenticado puede ver la lista de assets
    public function test_authenticated_user_can_see_assets_index(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($user)->get('/assets');
        $response->assertStatus(200);
    }

    // Test 3: viewer no puede acceder al formulario de subida
    public function test_viewer_cannot_access_create_asset_form(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($user)->get('/assets/create');
        $response->assertStatus(403);
    }

    // Test 4: editor puede acceder al formulario de subida
    public function test_editor_can_access_create_asset_form(): void
    {
        $user = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($user)->get('/assets/create');
        $response->assertStatus(200);
    }

    // Test 5: admin puede subir un archivo
    public function test_admin_can_upload_asset(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->create('test-image.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->post('/assets', [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assets', [
            'user_id'       => $user->id,
            'original_name' => 'test-image.jpg',
        ]);
    }

    // Test 6: viewer no puede borrar assets
    public function test_viewer_cannot_delete_asset(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $viewer = User::factory()->create(['role' => 'viewer']);
        $asset  = Asset::factory()->create(['user_id' => $admin->id]);

        $response = $this->actingAs($viewer)->delete("/assets/{$asset->id}");
        $response->assertStatus(403);
    }
}