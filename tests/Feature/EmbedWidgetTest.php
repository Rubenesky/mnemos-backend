<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helper ──────────────────────────────────────────────────────────────────

/**
 * Create a public category with a given number of public+processed assets.
 */
function makePublicCollection(string $slug = 'test-col', int $assetCount = 3): Category
{
    $category = Category::factory()->create(['slug' => $slug, 'is_public' => true]);
    $user     = User::factory()->create();

    Asset::factory()->count($assetCount)->create([
        'user_id'   => $user->id,
        'is_public' => true,
        'status'    => 'processed',
    ])->each(fn($a) => $a->categories()->attach($category));

    return $category;
}

// ── gallery endpoint ─────────────────────────────────────────────────────────

it('renders the embed gallery page without authentication', function () {
    $col = makePublicCollection('nature');

    $this->get('/api/public/embed/nature')
         ->assertOk()
         ->assertSee($col->name)
         ->assertSee('Powered by Mnemos');
});

it('returns 404 for a non-existent collection slug', function () {
    $this->get('/api/public/embed/does-not-exist')
         ->assertNotFound();
});

it('returns 404 for a private collection', function () {
    Category::factory()->create(['slug' => 'private-col', 'is_public' => false]);

    $this->get('/api/public/embed/private-col')
         ->assertNotFound();
});

it('defaults to light theme and 3 cols when no params given', function () {
    makePublicCollection('defaults');

    $this->get('/api/public/embed/defaults')
         ->assertOk()
         ->assertSee('data-theme="light"', false)
         ->assertSee('repeat(3, 1fr)', false);
});

it('respects dark theme parameter', function () {
    makePublicCollection('dark-col');

    $this->get('/api/public/embed/dark-col?theme=dark')
         ->assertOk()
         ->assertSee('data-theme="dark"', false);
});

it('respects cols parameter', function () {
    makePublicCollection('cols-col');

    $this->get('/api/public/embed/cols-col?cols=2')
         ->assertOk()
         ->assertSee('repeat(2, 1fr)', false);
});

it('ignores invalid theme and falls back to light', function () {
    makePublicCollection('bad-theme');

    $this->get('/api/public/embed/bad-theme?theme=rainbow')
         ->assertOk()
         ->assertSee('data-theme="light"', false);
});

it('ignores invalid cols and falls back to 3', function () {
    makePublicCollection('bad-cols');

    $this->get('/api/public/embed/bad-cols?cols=99')
         ->assertOk()
         ->assertSee('repeat(3, 1fr)', false);
});

it('embed page has no X-Frame-Options DENY header', function () {
    makePublicCollection('frame-test');

    $response = $this->get('/api/public/embed/frame-test');

    $response->assertOk();
    expect($response->headers->get('X-Frame-Options'))->toBeNull();
});

it('embed page has frame-ancestors CSP header', function () {
    makePublicCollection('csp-test');

    $response = $this->get('/api/public/embed/csp-test');

    $response->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toContain('frame-ancestors *');
});

// ── embed-code endpoint ──────────────────────────────────────────────────────

it('returns embed code JSON with snippet and preview_url', function () {
    makePublicCollection('code-col');

    $this->get('/api/public/embed-code/code-col')
         ->assertOk()
         ->assertJsonStructure(['snippet', 'preview_url']);
});

it('snippet contains an iframe tag', function () {
    makePublicCollection('iframe-col');

    $data = $this->get('/api/public/embed-code/iframe-col')
                 ->assertOk()
                 ->json();

    expect($data['snippet'])->toContain('<iframe')
                             ->toContain('width="100%"')
                             ->toContain('height="600"');
});

it('snippet src contains the collection slug', function () {
    makePublicCollection('slug-check');

    $data = $this->get('/api/public/embed-code/slug-check')
                 ->assertOk()
                 ->json();

    expect($data['snippet'])->toContain('slug-check');
    expect($data['preview_url'])->toContain('slug-check');
});

it('snippet forwards theme and cols params', function () {
    makePublicCollection('params-col');

    $data = $this->get('/api/public/embed-code/params-col?theme=dark&cols=4&limit=6')
                 ->assertOk()
                 ->json();

    expect($data['snippet'])->toContain('theme=dark')
                             ->toContain('cols=4')
                             ->toContain('limit=6');
});

it('embed-code returns 404 for unknown collection', function () {
    $this->get('/api/public/embed-code/unknown-xyz')
         ->assertNotFound();
});
