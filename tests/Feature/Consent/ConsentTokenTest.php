<?php

use App\Models\Asset;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── send-request ────────────────────────────────────────────────────────

it('admin puede generar un token de solicitud', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);
    $consent = Consent::factory()->create(['asset_id' => $asset->id, 'status' => 'pending']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/consents/{$consent->id}/send-request");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['token', 'url', 'expires_at']]);

    $this->assertNotNull($consent->fresh()->token);
    $this->assertNotNull($consent->fresh()->token_expires_at);
});

it('viewer no puede generar token de solicitud', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $asset = Asset::factory()->create();
    $consent = Consent::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($viewer, 'sanctum')
        ->postJson("/api/consents/{$consent->id}/send-request")
        ->assertStatus(403);
});

// ── public show ─────────────────────────────────────────────────────────

it('devuelve los datos del consentimiento con token válido', function () {
    $asset = Asset::factory()->create();
    $consent = Consent::factory()->create([
        'asset_id' => $asset->id,
        'status' => 'pending',
        'token' => 'valid-token-abc123',
        'token_expires_at' => now()->addDays(7),
        'responded_at' => null,
    ]);

    $this->getJson('/api/public/consents/valid-token-abc123')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => ['person_name', 'consent_type', 'asset_title', 'consent_date', 'expires_at']]);
});

it('devuelve 404 con token inexistente', function () {
    $this->getJson('/api/public/consents/token-que-no-existe')
        ->assertStatus(404);
});

it('devuelve 404 con token expirado', function () {
    $asset = Asset::factory()->create();
    Consent::factory()->create([
        'asset_id' => $asset->id,
        'token' => 'expired-token',
        'token_expires_at' => now()->subDay(),
        'responded_at' => null,
    ]);

    $this->getJson('/api/public/consents/expired-token')
        ->assertStatus(404);
});

it('devuelve 404 con token ya respondido', function () {
    $asset = Asset::factory()->create();
    Consent::factory()->create([
        'asset_id' => $asset->id,
        'token' => 'answered-token',
        'token_expires_at' => now()->addDays(7),
        'responded_at' => now()->subHour(),
    ]);

    $this->getJson('/api/public/consents/answered-token')
        ->assertStatus(404);
});

// ── public respond ──────────────────────────────────────────────────────

it('persona puede aceptar el consentimiento via token', function () {
    $asset = Asset::factory()->create();
    $consent = Consent::factory()->create([
        'asset_id' => $asset->id,
        'status' => 'pending',
        'token' => 'accept-token',
        'token_expires_at' => now()->addDays(7),
        'responded_at' => null,
    ]);

    $this->postJson('/api/public/consents/accept-token', ['status' => 'obtained'])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Consentimiento registrado. Gracias.');

    $fresh = $consent->fresh();
    expect($fresh->status)->toBe('obtained');
    expect($fresh->responded_at)->not->toBeNull();
});

it('persona puede rechazar el consentimiento via token', function () {
    $asset = Asset::factory()->create();
    $consent = Consent::factory()->create([
        'asset_id' => $asset->id,
        'status' => 'pending',
        'token' => 'deny-token',
        'token_expires_at' => now()->addDays(7),
        'responded_at' => null,
    ]);

    $this->postJson('/api/public/consents/deny-token', ['status' => 'denied'])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Decisión registrada. Su respuesta ha sido guardada.');

    expect($consent->fresh()->status)->toBe('denied');
});

it('no se puede responder con status inválido', function () {
    $asset = Asset::factory()->create();
    Consent::factory()->create([
        'asset_id' => $asset->id,
        'token' => 'invalid-status-token',
        'token_expires_at' => now()->addDays(7),
        'responded_at' => null,
    ]);

    $this->postJson('/api/public/consents/invalid-status-token', ['status' => 'pending'])
        ->assertStatus(422);
});
