<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devuelve token con credenciales válidas', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['token', 'user' => ['id', 'role']]);
});

it('rechaza credenciales incorrectas con 422', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'contraseña-incorrecta',
    ])->assertStatus(422);
});

it('rechaza acceso a rutas protegidas sin token', function () {
    $this->getJson('/api/assets')->assertStatus(401);
});

it('logout invalida el token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    // Verify we can access protected route with token
    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/assets')
        ->assertStatus(200);

    // Verify token exists before logout
    expect($user->tokens()->count())->toBe(1);

    // Logout with token
    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/logout')
        ->assertStatus(200);

    // Verify token was deleted
    $user->refresh();
    expect($user->tokens()->count())->toBe(0);
});

it('bloquea el login tras 5 intentos fallidos', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ]);
    }

    $this->postJson('/api/login', [
        'email'    => $user->email,
        'password' => 'wrong',
    ])->assertStatus(429);
});
