<?php

// RJC

use App\Models\Asset;
use App\Models\AssetView;
use App\Models\Consent;
use App\Models\User;
use App\Services\ImpactDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * Feature tests for GET /api/reports/impact-dashboard.
 */
beforeEach(fn () => Cache::flush());

// ─────────────────────────────────────────────────────────────────────────────
// Access control
// ─────────────────────────────────────────────────────────────────────────────

test('admin can access impact dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk();
});

test('editor cannot access impact dashboard', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor)
        ->getJson('/api/reports/impact-dashboard')
        ->assertStatus(403);
});

test('viewer cannot access impact dashboard', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer)
        ->getJson('/api/reports/impact-dashboard')
        ->assertStatus(403);
});

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/reports/impact-dashboard')->assertStatus(401);
});

// ─────────────────────────────────────────────────────────────────────────────
// Response structure
// ─────────────────────────────────────────────────────────────────────────────

test('dashboard returns correct json structure', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'total_assets',
                    'public_assets',
                    'total_downloads',
                    'consents_granted',
                    'alt_texts_generated',
                    'hours_saved',
                ],
                'trends' => [
                    'assets_last_30_days',
                    'consents_last_30_days',
                    'assets_by_month',
                ],
                'top_assets',
            ],
        ]);
});

test('assets_by_month contains exactly 6 entries', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $months = $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk()
        ->json('data.trends.assets_by_month');

    expect($months)->toHaveCount(6);
});

// ─────────────────────────────────────────────────────────────────────────────
// KPI calculations
// ─────────────────────────────────────────────────────────────────────────────

test('hours_saved equals alt_texts_generated times 0.083', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Asset::factory()->count(10)->create([
        'user_id' => $admin->id,
        'alt_text' => 'AI-generated description',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk();

    expect($response->json('data.summary.alt_texts_generated'))->toBe(10)
        ->and($response->json('data.summary.hours_saved'))->toBe(round(10 * 0.083, 2));
});

test('total_downloads counts asset_views rows', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    AssetView::create(['asset_id' => $asset->id, 'ip_hash' => 'abc']);
    AssetView::create(['asset_id' => $asset->id, 'ip_hash' => 'def']);
    AssetView::create(['asset_id' => $asset->id, 'ip_hash' => 'ghi']);

    $response = $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk();

    expect($response->json('data.summary.total_downloads'))->toBe(3);
});

test('consents_granted counts only obtained consents', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->count(4)->create(['asset_id' => $asset->id, 'status' => 'obtained']);
    Consent::factory()->count(2)->create(['asset_id' => $asset->id, 'status' => 'pending']);
    Consent::factory()->count(1)->create(['asset_id' => $asset->id, 'status' => 'denied']);

    $response = $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk();

    expect($response->json('data.summary.consents_granted'))->toBe(4);
});

// ─────────────────────────────────────────────────────────────────────────────
// Top assets
// ─────────────────────────────────────────────────────────────────────────────

test('top_assets are ordered by view_count descending', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset1 = Asset::factory()->create(['user_id' => $admin->id]);
    $asset2 = Asset::factory()->create(['user_id' => $admin->id]);
    $asset3 = Asset::factory()->create(['user_id' => $admin->id]);

    AssetView::create(['asset_id' => $asset2->id]);
    AssetView::create(['asset_id' => $asset2->id]);
    AssetView::create(['asset_id' => $asset2->id]);
    AssetView::create(['asset_id' => $asset1->id]);
    AssetView::create(['asset_id' => $asset1->id]);
    AssetView::create(['asset_id' => $asset3->id]);

    $topAssets = $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk()
        ->json('data.top_assets');

    expect($topAssets[0]['id'])->toBe($asset2->id)
        ->and($topAssets[0]['view_count'])->toBe(3)
        ->and($topAssets[1]['id'])->toBe($asset1->id)
        ->and($topAssets[1]['view_count'])->toBe(2);
});

test('top_assets returns at most 5 entries', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Asset::factory()->count(8)->create(['user_id' => $admin->id]);

    $topAssets = $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk()
        ->json('data.top_assets');

    expect(count($topAssets))->toBeLessThanOrEqual(5);
});

// ─────────────────────────────────────────────────────────────────────────────
// Cache
// ─────────────────────────────────────────────────────────────────────────────

test('response is cached after first request', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    expect(Cache::has('impact.dashboard'))->toBeFalse();

    $this->actingAs($admin)
        ->getJson('/api/reports/impact-dashboard')
        ->assertOk();

    expect(Cache::has('impact.dashboard'))->toBeTrue();
});

test('service gather returns correct keys', function () {
    $data = app(ImpactDashboardService::class)->gather();

    expect($data)->toHaveKeys(['summary', 'trends', 'top_assets'])
        ->and($data['summary'])->toHaveKeys([
            'total_assets', 'public_assets', 'total_downloads',
            'consents_granted', 'alt_texts_generated', 'hours_saved',
        ])
        ->and($data['trends']['assets_by_month'])->toHaveCount(6);
});
