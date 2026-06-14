<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registro exitoso devuelve token y rol viewer por defecto', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);

    expect($response->json('user.role'))->toBe('viewer');
});

it('no se puede registrar con contraseña débil', function () {
    $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'weak@example.com',
        'password' => '12345678',
        'password_confirmation' => '12345678',
    ])->assertStatus(422);
});

it('no se puede registrar con email duplicado', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Otro usuario',
        'email' => 'existing@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertStatus(422);
});

it('no se puede inyectar role admin via registro', function () {
    $this->postJson('/api/register', [
        'name' => 'Hacker',
        'email' => 'hacker@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'role' => 'admin',
    ])->assertStatus(201);

    $user = User::where('email', 'hacker@example.com')->first();
    expect($user->role)->toBe('viewer');
});

it('registro falla si las contraseñas no coinciden', function () {
    $this->postJson('/api/register', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Different1!',
    ])->assertStatus(422);
});
