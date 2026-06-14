<?php

use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── index ────────────────────────────────────────────────────────────────────

it('usuario autenticado puede listar sus notificaciones', function () {
    $user = User::factory()->create(['role' => 'admin']);

    AppNotification::create([
        'user_id' => $user->id,
        'type' => 'consent_responded',
        'data' => ['consent_id' => 1, 'person_name' => 'Ana', 'status' => 'obtained', 'asset_id' => 1],
        'read_at' => null,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/notifications');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['id', 'type', 'data', 'read_at', 'created_at']],
            'unread_count',
        ])
        ->assertJsonPath('unread_count', 1);
});

it('usuario no autenticado no puede listar notificaciones', function () {
    $this->getJson('/api/notifications')
        ->assertStatus(401);
});

it('solo devuelve las notificaciones del usuario autenticado', function () {
    $userA = User::factory()->create(['role' => 'admin']);
    $userB = User::factory()->create(['role' => 'admin']);

    AppNotification::create([
        'user_id' => $userA->id,
        'type' => 'volunteer_upload',
        'data' => ['asset_id' => 1, 'asset_name' => 'foto.jpg', 'uploader_name' => 'Paco'],
        'read_at' => null,
    ]);
    AppNotification::create([
        'user_id' => $userB->id,
        'type' => 'volunteer_upload',
        'data' => ['asset_id' => 2, 'asset_name' => 'otro.jpg', 'uploader_name' => 'Luis'],
        'read_at' => null,
    ]);

    $response = $this->actingAs($userA, 'sanctum')
        ->getJson('/api/notifications');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('unread_count'))->toBe(1);
});

it('unread_count es 0 cuando todas están leídas', function () {
    $user = User::factory()->create(['role' => 'admin']);

    AppNotification::create([
        'user_id' => $user->id,
        'type' => 'consent_responded',
        'data' => ['consent_id' => 1, 'person_name' => 'Ana', 'status' => 'obtained', 'asset_id' => 1],
        'read_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/notifications')
        ->assertStatus(200)
        ->assertJsonPath('unread_count', 0);
});

// ── markRead ─────────────────────────────────────────────────────────────────

it('usuario puede marcar una notificación propia como leída', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $notification = AppNotification::create([
        'user_id' => $user->id,
        'type' => 'consent_responded',
        'data' => ['consent_id' => 1, 'person_name' => 'Ana', 'status' => 'obtained', 'asset_id' => 1],
        'read_at' => null,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertStatus(200)
        ->assertJsonPath('data.read_at', fn ($v) => $v !== null);

    $this->assertNotNull($notification->fresh()->read_at);
});

it('usuario no puede marcar como leída una notificación ajena', function () {
    $owner = User::factory()->create(['role' => 'admin']);
    $other = User::factory()->create(['role' => 'admin']);

    $notification = AppNotification::create([
        'user_id' => $owner->id,
        'type' => 'consent_responded',
        'data' => ['consent_id' => 1, 'person_name' => 'Ana', 'status' => 'obtained', 'asset_id' => 1],
        'read_at' => null,
    ]);

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertStatus(403);
});

// ── markAllRead ───────────────────────────────────────────────────────────────

it('marcar todo como leído actualiza todas las notificaciones del usuario', function () {
    $user = User::factory()->create(['role' => 'admin']);

    AppNotification::create(['user_id' => $user->id, 'type' => 'consent_responded', 'data' => ['x' => 1], 'read_at' => null]);
    AppNotification::create(['user_id' => $user->id, 'type' => 'volunteer_upload',   'data' => ['x' => 2], 'read_at' => null]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/notifications/read-all')
        ->assertStatus(200)
        ->assertJsonPath('message', 'All notifications marked as read.');

    $unread = AppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    expect($unread)->toBe(0);
});

// ── NotificationService ───────────────────────────────────────────────────────

it('NotificationService::notifyAdmins crea una notificación por cada admin', function () {
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'editor']);

    $service = app(NotificationService::class);
    $service->notifyAdmins('volunteer_upload', [
        'asset_id' => 5,
        'asset_name' => 'test.jpg',
        'uploader_name' => 'Volunteer',
    ]);

    expect(AppNotification::count())->toBe(2);
    expect(AppNotification::where('type', 'volunteer_upload')->count())->toBe(2);
});

it('NotificationService::notify crea una sola notificación para el destinatario', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $service = app(NotificationService::class);
    $service->notify($user, 'consent_responded', [
        'consent_id' => 3,
        'person_name' => 'María',
        'status' => 'denied',
        'asset_id' => 7,
    ]);

    expect(AppNotification::where('user_id', $user->id)->count())->toBe(1);
    expect(AppNotification::first()->type)->toBe('consent_responded');
    expect(AppNotification::first()->data['person_name'])->toBe('María');
});
