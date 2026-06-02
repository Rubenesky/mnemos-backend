<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAssetTest extends TestCase
{
    use RefreshDatabase;

    // Test 1: usuario no autenticado no puede acceder a la API
    public function test_unauthenticated_user_cannot_access_api(): void
    {
        $response = $this->getJson('/api/assets');
        $response->assertStatus(401);
    }

    // Test 2: login correcto devuelve token
    public function test_user_can_login_and_get_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'role'     => 'viewer',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'token',
                     'user' => ['id', 'name', 'email', 'role']
                 ]);
    }

    // Test 3: login incorrecto devuelve error
    public function test_login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    // Test 4: usuario autenticado puede listar assets
    public function test_authenticated_user_can_list_assets(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);
        Asset::factory()->count(3)->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->getJson('/api/assets');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data',
                     'meta' => ['total', 'per_page', 'current_page', 'last_page']
                 ]);
    }

    // Test 5: usuario autenticado puede ver un asset individual
    public function test_authenticated_user_can_view_single_asset(): void
    {
        $user  = User::factory()->create(['role' => 'viewer']);
        $asset = Asset::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->getJson("/api/assets/{$asset->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => ['id', 'original_name', 'mime_type', 'size_kb', 'status', 'url']
                 ]);
    }

    // Test 6: solo admin puede borrar un asset via API
    public function test_only_admin_can_delete_asset_via_api(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $viewer = User::factory()->create(['role' => 'viewer']);
        $asset  = Asset::factory()->create(['user_id' => $admin->id]);

        // Viewer no puede borrar
        $this->actingAs($viewer, 'sanctum')
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);

        // Admin sí puede borrar
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    // Test 7: logout invalida el token
    public function test_logout_invalidates_token(): void
    {
        $user  = User::factory()->create(['role' => 'viewer']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Verificamos que el token fue eliminado de la BD
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}