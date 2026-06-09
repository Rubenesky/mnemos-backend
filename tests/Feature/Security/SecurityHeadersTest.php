<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('las respuestas incluyen X-Frame-Options: DENY', function () {
    $this->getJson('/api/public/collections')
         ->assertHeader('X-Frame-Options', 'DENY');
});

it('las respuestas incluyen X-Content-Type-Options: nosniff', function () {
    $this->getJson('/api/public/collections')
         ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('las respuestas incluyen Referrer-Policy', function () {
    $response = $this->getJson('/api/public/collections');
    expect($response->headers->get('Referrer-Policy'))->not->toBeNull();
});

it('las respuestas de login incluyen cabeceras de seguridad', function () {
    $response = $this->postJson('/api/login', [
        'email'    => 'nobody@example.com',
        'password' => 'wrong',
    ]);

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('no se expone stack trace en respuestas de error con APP_DEBUG=false', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
                     ->getJson('/api/assets/999999');

    $body = $response->getContent();
    expect($body)->not->toContain('"trace"');
    expect($body)->not->toContain('"exception"');
});
