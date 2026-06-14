<?php

// RJC

use App\Models\Asset;
use App\Models\AssetMetadata;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Per page
// ---------------------------------------------------------------------------

it('el per_page por defecto es 12', function () {
    $user = User::factory()->create(['role' => 'admin']);
    Asset::factory()->count(15)->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets')
        ->assertStatus(200)
        ->assertJsonPath('meta.per_page', 12);
});

it('per_page=24 se respeta correctamente', function () {
    $user = User::factory()->create(['role' => 'admin']);
    Asset::factory()->count(5)->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?per_page=24')
        ->assertStatus(200)
        ->assertJsonPath('meta.per_page', 24);
});

it('per_page inválido vuelve al valor por defecto de 12', function () {
    $user = User::factory()->create(['role' => 'admin']);
    Asset::factory()->count(5)->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?per_page=99')
        ->assertStatus(200)
        ->assertJsonPath('meta.per_page', 12);
});

// ---------------------------------------------------------------------------
// Filtro de búsqueda
// ---------------------------------------------------------------------------

it('búsqueda por título devuelve sólo el asset coincidente', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $assetA = Asset::factory()->create(['user_id' => $user->id]);
    AssetMetadata::create([
        'asset_id' => $assetA->id,
        'title' => 'Beautiful photo',
        'description' => null,
        'tags' => [],
        'ai_generated' => false,
    ]);

    $assetB = Asset::factory()->create(['user_id' => $user->id]);
    AssetMetadata::create([
        'asset_id' => $assetB->id,
        'title' => 'Other',
        'description' => null,
        'tags' => [],
        'ai_generated' => false,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?search=beautiful')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assetA->id);
});

it('búsqueda por tag devuelve el asset que lo contiene', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $asset = Asset::factory()->create(['user_id' => $user->id]);
    AssetMetadata::create([
        'asset_id' => $asset->id,
        'title' => 'Sunset',
        'description' => null,
        'tags' => ['landscape', 'sunset'],
        'ai_generated' => false,
    ]);

    $other = Asset::factory()->create(['user_id' => $user->id]);
    AssetMetadata::create([
        'asset_id' => $other->id,
        'title' => 'Portrait',
        'description' => null,
        'tags' => ['people'],
        'ai_generated' => false,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?search=landscape')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $asset->id);
});

it('búsqueda por original_name devuelve el asset correcto', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'original_name' => 'vacation_photo.jpg',
    ]);

    Asset::factory()->create([
        'user_id' => $user->id,
        'original_name' => 'work_document.pdf',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?search=vacation')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $asset->id);
});

// ---------------------------------------------------------------------------
// Filtro por tipo
// ---------------------------------------------------------------------------

it('type[]=image devuelve sólo assets de tipo imagen', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $image = Asset::factory()->create([
        'user_id' => $user->id,
        'mime_type' => 'image/jpeg',
    ]);
    Asset::factory()->create([
        'user_id' => $user->id,
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?type[]=image')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $image->id);
});

it('type[]=pdf devuelve sólo assets PDF', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $pdf = Asset::factory()->create([
        'user_id' => $user->id,
        'mime_type' => 'application/pdf',
    ]);
    Asset::factory()->create([
        'user_id' => $user->id,
        'mime_type' => 'image/png',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?type[]=pdf')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pdf->id);
});

it('type[]=image y type[]=video devuelven tanto imágenes como vídeos', function () {
    $user = User::factory()->create(['role' => 'admin']);

    Asset::factory()->create(['user_id' => $user->id, 'mime_type' => 'image/jpeg']);
    Asset::factory()->create(['user_id' => $user->id, 'mime_type' => 'video/mp4']);
    Asset::factory()->create(['user_id' => $user->id, 'mime_type' => 'application/pdf']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?type[]=image&type[]=video')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

// ---------------------------------------------------------------------------
// Filtro por estado de consentimiento
// ---------------------------------------------------------------------------

it('consent_status=pending devuelve assets con al menos un consentimiento pendiente', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $assetPending = Asset::factory()->create(['user_id' => $user->id]);
    Consent::factory()->create(['asset_id' => $assetPending->id, 'status' => 'pending']);

    $assetObtained = Asset::factory()->create(['user_id' => $user->id]);
    Consent::factory()->create(['asset_id' => $assetObtained->id, 'status' => 'obtained']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?consent_status=pending')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assetPending->id);
});

it('consent_status=none devuelve assets sin ningún registro de consentimiento', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $assetNoConsent = Asset::factory()->create(['user_id' => $user->id]);

    $assetWithConsent = Asset::factory()->create(['user_id' => $user->id]);
    Consent::factory()->create(['asset_id' => $assetWithConsent->id, 'status' => 'pending']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?consent_status=none')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assetNoConsent->id);
});

it('consent_status=obtained devuelve assets con al menos un consentimiento obtenido', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $assetObtained = Asset::factory()->create(['user_id' => $user->id]);
    Consent::factory()->create(['asset_id' => $assetObtained->id, 'status' => 'obtained']);

    $assetPending = Asset::factory()->create(['user_id' => $user->id]);
    Consent::factory()->create(['asset_id' => $assetPending->id, 'status' => 'pending']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?consent_status=obtained')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assetObtained->id);
});

// ---------------------------------------------------------------------------
// Filtros de fecha
// ---------------------------------------------------------------------------

it('date_from excluye assets más antiguos', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $old = Asset::factory()->create(['user_id' => $user->id, 'created_at' => '2024-01-15 10:00:00']);
    $new = Asset::factory()->create(['user_id' => $user->id, 'created_at' => '2024-06-01 10:00:00']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?date_from=2024-05-01')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $new->id);
});

it('date_to excluye assets más recientes', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $old = Asset::factory()->create(['user_id' => $user->id, 'created_at' => '2024-01-15 10:00:00']);
    $new = Asset::factory()->create(['user_id' => $user->id, 'created_at' => '2024-06-01 10:00:00']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?date_to=2024-03-01')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $old->id);
});

// ---------------------------------------------------------------------------
// Press kit y Emergency kit
// ---------------------------------------------------------------------------

it('press_kit=1 devuelve sólo assets del press kit', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $kitAsset = Asset::factory()->create(['user_id' => $user->id, 'is_press_kit' => true]);
    Asset::factory()->create(['user_id' => $user->id, 'is_press_kit' => false]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?press_kit=1')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $kitAsset->id);
});

it('emergency_kit=1 devuelve sólo assets del kit de emergencia', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $emergencyAsset = Asset::factory()->create(['user_id' => $user->id, 'is_emergency_kit' => true]);
    Asset::factory()->create(['user_id' => $user->id, 'is_emergency_kit' => false]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?emergency_kit=1')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $emergencyAsset->id);
});

// ---------------------------------------------------------------------------
// Filtros combinados
// ---------------------------------------------------------------------------

it('la combinación de search y type funciona correctamente', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Image with matching title — should be returned
    $target = Asset::factory()->create(['user_id' => $user->id, 'mime_type' => 'image/jpeg']);
    AssetMetadata::create([
        'asset_id' => $target->id,
        'title' => 'Sunset landscape',
        'description' => null,
        'tags' => [],
        'ai_generated' => false,
    ]);

    // PDF with matching title — excluded by type filter
    $pdf = Asset::factory()->create(['user_id' => $user->id, 'mime_type' => 'application/pdf']);
    AssetMetadata::create([
        'asset_id' => $pdf->id,
        'title' => 'Sunset landscape document',
        'description' => null,
        'tags' => [],
        'ai_generated' => false,
    ]);

    // Image with non-matching title — excluded by search filter
    Asset::factory()->create(['user_id' => $user->id, 'mime_type' => 'image/png']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/assets?search=sunset&type[]=image')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

// ---------------------------------------------------------------------------
// IDOR con filtros
// ---------------------------------------------------------------------------

it('IDOR: el editor no ve assets de otro usuario aunque use el filtro de búsqueda', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);

    $adminAsset = Asset::factory()->create(['user_id' => $admin->id]);
    AssetMetadata::create([
        'asset_id' => $adminAsset->id,
        'title' => 'Secret document',
        'description' => null,
        'tags' => [],
        'ai_generated' => false,
    ]);

    $editorAsset = Asset::factory()->create(['user_id' => $editor->id]);
    AssetMetadata::create([
        'asset_id' => $editorAsset->id,
        'title' => 'Editor secret document',
        'description' => null,
        'tags' => [],
        'ai_generated' => false,
    ]);

    $this->actingAs($editor, 'sanctum')
        ->getJson('/api/assets?search=secret')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $editorAsset->id);
});

it('IDOR: el admin ve todos los assets que coinciden con el filtro de tipo', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);

    Asset::factory()->create(['user_id' => $admin->id,  'mime_type' => 'image/jpeg']);
    Asset::factory()->create(['user_id' => $editor->id, 'mime_type' => 'image/png']);
    Asset::factory()->create(['user_id' => $editor->id, 'mime_type' => 'application/pdf']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/assets?type[]=image')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('IDOR: el editor sólo ve sus propios assets que coinciden con el filtro de tipo', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $editor = User::factory()->create(['role' => 'editor']);

    Asset::factory()->create(['user_id' => $admin->id,  'mime_type' => 'image/jpeg']);
    $editorImage = Asset::factory()->create(['user_id' => $editor->id, 'mime_type' => 'image/png']);
    Asset::factory()->create(['user_id' => $editor->id, 'mime_type' => 'application/pdf']);

    $this->actingAs($editor, 'sanctum')
        ->getJson('/api/assets?type[]=image')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $editorImage->id);
});
