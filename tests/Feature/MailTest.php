<?php
// RJC
use App\Mail\AssetUploadNotificationMail;
use App\Mail\ConsentRequestMail;
use App\Mail\ConsentResponseMail;
use App\Models\Asset;
use App\Models\Consent;
use App\Models\User;
use App\Services\ConsentTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Test 1: ConsentRequestMail is sent when sendRequest is called and person_email is set.
 */
it('sends ConsentRequestMail when send-request is called with person_email set', function () {
    Mail::fake();

    $admin   = User::factory()->create(['role' => 'admin']);
    $asset   = Asset::factory()->create(['user_id' => $admin->id]);
    $consent = Consent::factory()->create([
        'asset_id'     => $asset->id,
        'person_email' => 'person@example.com',
        'status'       => 'pending',
    ]);

    $this->actingAs($admin, 'sanctum')
         ->postJson("/api/consents/{$consent->id}/send-request")
         ->assertOk();

    Mail::assertSent(ConsentRequestMail::class, function ($mail) use ($consent) {
        return $mail->hasTo($consent->person_email)
            && $mail->consent->id === $consent->id;
    });
});

/**
 * Test 2: ConsentRequestMail is NOT sent when person_email is null.
 */
it('does not send ConsentRequestMail when person_email is null', function () {
    Mail::fake();

    $admin   = User::factory()->create(['role' => 'admin']);
    $asset   = Asset::factory()->create(['user_id' => $admin->id]);
    $consent = Consent::factory()->create([
        'asset_id'     => $asset->id,
        'person_email' => null,
        'status'       => 'pending',
    ]);

    $this->actingAs($admin, 'sanctum')
         ->postJson("/api/consents/{$consent->id}/send-request")
         ->assertOk();

    Mail::assertNotSent(ConsentRequestMail::class);
});

/**
 * Test 3: AssetUploadNotificationMail is sent to all admins when a volunteer uploads an asset.
 */
it('sends AssetUploadNotificationMail to all admins when a volunteer uploads', function () {
    Mail::fake();

    $admin1    = User::factory()->create(['role' => 'admin']);
    $admin2    = User::factory()->create(['role' => 'admin']);
    $volunteer = User::factory()->create(['role' => 'volunteer']);

    $file = \Illuminate\Http\UploadedFile::fake()->image('photo.jpg');

    // Mock CloudinaryService and ProcessAssetAI to avoid real calls
    \Illuminate\Support\Facades\Queue::fake();
    $this->mock(\App\Services\CloudinaryService::class, function ($mock) {
        $mock->shouldReceive('upload')->andReturn([
            'url'        => 'https://res.cloudinary.com/test/image/upload/test.jpg',
            'public_id'  => 'test_public_id',
        ]);
    });

    $this->actingAs($volunteer, 'sanctum')
         ->postJson('/api/assets', ['file' => $file])
         ->assertCreated();

    Mail::assertSent(AssetUploadNotificationMail::class, 2); // one per admin
    Mail::assertSent(AssetUploadNotificationMail::class, function ($mail) use ($admin1) {
        return $mail->hasTo($admin1->email);
    });
    Mail::assertSent(AssetUploadNotificationMail::class, function ($mail) use ($admin2) {
        return $mail->hasTo($admin2->email);
    });
});

/**
 * Test 4: ConsentResponseMail is sent to all admins when a person responds to a consent request.
 */
it('sends ConsentResponseMail to all admins when consent is responded', function () {
    Mail::fake();

    $admin   = User::factory()->create(['role' => 'admin']);
    $asset   = Asset::factory()->create(['user_id' => $admin->id]);
    $consent = Consent::factory()->create([
        'asset_id' => $asset->id,
        'status'   => 'pending',
    ]);

    // Generate a valid token first
    $service = app(ConsentTokenService::class);
    $token   = $service->generateToken($consent);

    $this->postJson("/api/public/consents/{$token}", ['status' => 'obtained'])
         ->assertOk();

    Mail::assertSent(ConsentResponseMail::class, function ($mail) use ($consent, $admin) {
        return $mail->hasTo($admin->email)
            && $mail->decision === 'obtained'
            && $mail->consent->id === $consent->id;
    });
});
