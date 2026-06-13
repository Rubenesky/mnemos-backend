<?php

// RJC

use App\Models\Asset;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * Feature tests for the GDPR Intelligence Panel endpoints.
 *
 * GET /api/admin/gdpr/dashboard
 * GET /api/admin/gdpr/audit/export
 */

// Flush the cache before every test so risk data cached by a prior test
// never leaks into the next scenario.
beforeEach(fn () => Cache::flush());

// ─────────────────────────────────────────────────────────────────────────────
// Access control
// ─────────────────────────────────────────────────────────────────────────────

test('non-admin roles cannot access gdpr dashboard', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertStatus(403);
})->with(['editor', 'viewer', 'volunteer']);

test('non-admin roles cannot export gdpr csv', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/api/admin/gdpr/audit/export')
        ->assertStatus(403);
})->with(['editor', 'viewer', 'volunteer']);

test('unauthenticated request cannot access gdpr dashboard', function () {
    $this->getJson('/api/admin/gdpr/dashboard')->assertStatus(401);
});

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard — response structure
// ─────────────────────────────────────────────────────────────────────────────

test('dashboard returns correct json structure', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'pending_consents',
                'accepted_consents',
                'rejected_consents',
                'blocked_assets',
                'total_assets',
                'blocked_percentage',
                'risk_level',
                'alerts',
            ],
        ]);
});

test('dashboard risk_level is one of the three valid values', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $level = $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->json('data.risk_level');

    expect($level)->toBeIn(['low', 'medium', 'high']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard — risk level calculation
// ─────────────────────────────────────────────────────────────────────────────

test('risk is low when no consents exist', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonPath('data.risk_level', 'low')
        ->assertJsonPath('data.alerts', []);
});

test('risk is medium when pending consents are between 10 and 50', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->count(15)->create(['asset_id' => $asset->id, 'status' => 'pending']);

    // Add 29 clean assets so blocked_pct = 1/30 ≈ 3.3% < 5% — keeps blocked
    // from triggering high or medium on its own; only pending count drives medium.
    Asset::factory()->count(29)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonPath('data.risk_level', 'medium')
        ->assertJsonPath('data.pending_consents', 15);
});

test('risk is high when pending consents exceed 50', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->count(51)->create(['asset_id' => $asset->id, 'status' => 'pending']);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonPath('data.risk_level', 'high')
        ->assertJsonPath('data.pending_consents', 51);
});

test('risk is high when rejection rate exceeds 10 percent', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    // 8 obtained + 2 denied = 20% rejection rate → high
    Consent::factory()->count(8)->create(['asset_id' => $asset->id, 'status' => 'obtained']);
    Consent::factory()->count(2)->create(['asset_id' => $asset->id, 'status' => 'denied']);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonPath('data.risk_level', 'high');
});

test('risk is medium when blocked percentage is between 5 and 20', function () {
    $admin   = User::factory()->create(['role' => 'admin']);
    $blocked = Asset::factory()->create(['user_id' => $admin->id]);

    // 1 blocked out of 10 total = 10% → medium via blocked_pct
    Asset::factory()->count(9)->create(['user_id' => $admin->id]);

    // 9 obtained + 1 denied = 10% rejection rate. Condition is > 0.10,
    // so exactly 10% does NOT trigger high; blocked_pct of 10% drives medium.
    Consent::factory()->count(9)->create(['asset_id' => $blocked->id, 'status' => 'obtained']);
    Consent::factory()->create(['asset_id' => $blocked->id, 'status' => 'denied']);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonPath('data.risk_level', 'medium');
});

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard — KPI accuracy
// ─────────────────────────────────────────────────────────────────────────────

test('blocked assets are counted correctly', function () {
    $admin  = User::factory()->create(['role' => 'admin']);
    $asset1 = Asset::factory()->create(['user_id' => $admin->id]);
    $asset2 = Asset::factory()->create(['user_id' => $admin->id]);
    Asset::factory()->create(['user_id' => $admin->id]); // clean — no consents

    Consent::factory()->create(['asset_id' => $asset1->id, 'status' => 'pending']);
    Consent::factory()->create(['asset_id' => $asset2->id, 'status' => 'denied']);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonPath('data.blocked_assets', 2)
        ->assertJsonPath('data.total_assets',   3);
});

test('consent counts are accurate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->count(3)->create(['asset_id' => $asset->id, 'status' => 'pending']);
    Consent::factory()->count(5)->create(['asset_id' => $asset->id, 'status' => 'obtained']);
    Consent::factory()->count(1)->create(['asset_id' => $asset->id, 'status' => 'denied']);

    $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->assertJsonPath('data.pending_consents',  3)
        ->assertJsonPath('data.accepted_consents', 5)
        ->assertJsonPath('data.rejected_consents', 1);
});

test('alerts are generated when blocked assets exist', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->create(['asset_id' => $asset->id, 'status' => 'pending']);

    $alerts = $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->json('data.alerts');

    expect($alerts)->not->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// Alert shape — structured {key, count?} descriptors
// ─────────────────────────────────────────────────────────────────────────────

test('alert assetsBlocked includes key and count when blocked assets exist', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->create(['asset_id' => $asset->id, 'status' => 'pending']);

    $alerts = $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->json('data.alerts');

    $blocked = collect($alerts)->firstWhere('key', 'assetsBlocked');
    expect($blocked)->not->toBeNull()
        ->and($blocked['key'])->toBe('assetsBlocked')
        ->and($blocked['count'])->toBe(1);
});

test('alert pendingHigh fires when more than 50 consents are pending', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->count(51)->create(['asset_id' => $asset->id, 'status' => 'pending']);

    $alerts = $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->json('data.alerts');

    $alert = collect($alerts)->firstWhere('key', 'pendingHigh');
    expect($alert)->not->toBeNull()
        ->and($alert)->not->toHaveKey('count'); // no count on pendingHigh
});

test('alert pendingMedium fires with count when 10 to 50 consents are pending', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    // 29 extra clean assets keep blocked_pct < 5% so only pending triggers medium
    Asset::factory()->count(29)->create(['user_id' => $admin->id]);
    Consent::factory()->count(15)->create(['asset_id' => $asset->id, 'status' => 'pending']);

    $alerts = $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->json('data.alerts');

    $alert = collect($alerts)->firstWhere('key', 'pendingMedium');
    expect($alert)->not->toBeNull()
        ->and($alert['count'])->toBe(15);
});

test('alert rejectionRateExceeded fires when rejection rate exceeds 10 percent', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    // 8 obtained + 2 denied = 20% rejection rate
    Consent::factory()->count(8)->create(['asset_id' => $asset->id, 'status' => 'obtained']);
    Consent::factory()->count(2)->create(['asset_id' => $asset->id, 'status' => 'denied']);

    $alerts = $this->actingAs($admin)
        ->getJson('/api/admin/gdpr/dashboard')
        ->assertOk()
        ->json('data.alerts');

    $alert = collect($alerts)->firstWhere('key', 'rejectionRateExceeded');
    expect($alert)->not->toBeNull()
        ->and($alert)->not->toHaveKey('count'); // no count on rejectionRateExceeded
});

// ─────────────────────────────────────────────────────────────────────────────
// CSV export
// ─────────────────────────────────────────────────────────────────────────────

test('csv export returns 200 with csv content type', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get('/api/admin/gdpr/audit/export')
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('csv export contains all required column headers', function () {
    $admin   = User::factory()->create(['role' => 'admin']);
    $content = $this->actingAs($admin)
        ->get('/api/admin/gdpr/audit/export')
        ->assertOk()
        ->streamedContent();

    foreach ([
        'asset_title', 'person_name', 'person_email',
        'status', 'consent_type', 'created_at',
        'responded_at', 'token_expires_at',
    ] as $column) {
        expect($content)->toContain($column);
    }
});

test('csv export includes consent data rows', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $asset = Asset::factory()->create(['user_id' => $admin->id]);

    Consent::factory()->create([
        'asset_id'    => $asset->id,
        'person_name' => 'Maria García',
        'status'      => 'pending',
    ]);

    $content = $this->actingAs($admin)
        ->get('/api/admin/gdpr/audit/export')
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('Maria García');
});
