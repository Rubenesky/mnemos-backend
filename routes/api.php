<?php

use App\Http\Controllers\Api\AssetApiController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AssetAuditController;
use App\Http\Controllers\Api\EmbedController;
use App\Http\Controllers\Api\EmergencyKitController;
use App\Http\Controllers\Api\PressRoomController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicConsentController;
use App\Http\Controllers\Api\PublicGalleryController;
use App\Http\Controllers\Api\RAGController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchApiController;
use App\Http\Controllers\Api\Admin\OrganizationSettingsController;
use App\Http\Controllers\Api\Admin\SystemStatusController;
use App\Http\Controllers\Api\Admin\VolunteerController;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check — no DB query, safe to ping frequently for uptime monitoring
Route::get('/health', fn () => response()->json(['ok' => true]))->middleware('throttle:60,1');

// Public gallery — no authentication required
Route::prefix('public')->group(function () {
    Route::get('/assets', [PublicGalleryController::class, 'assets']);      // list — must be before /{id}
    Route::get('/assets/{id}', [PublicGalleryController::class, 'asset']);
    Route::get('/collections', [PublicGalleryController::class, 'collections']);
    Route::get('/collections/{slug}', [PublicGalleryController::class, 'collection']);

    // Token-based consent form — no login required
    Route::get('/consents/{token}', [PublicConsentController::class, 'show']);
    Route::post('/consents/{token}', [PublicConsentController::class, 'respond'])->middleware('throttle:5,1');

    Route::get('/press-room', [PressRoomController::class, 'index'])->middleware('throttle:60,1');

    // Embeddable widget — public, no auth required
    Route::get('/embed/{slug}', [EmbedController::class, 'gallery'])->middleware('throttle:60,1');
    Route::get('/embed-code/{slug}', [EmbedController::class, 'embedCode'])->middleware('throttle:60,1');
});

// Rutas públicas de la API
Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:5,1');
Route::post('/register', [AuthApiController::class, 'register'])->middleware('throttle:5,1');

// Rutas protegidas por token
Route::middleware('auth:sanctum')->group(function () {
    // Cerrar sesión
    Route::post('/logout', [AuthApiController::class, 'logout']);

    // Información del usuario autenticado
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $request->user()->id,
                'name'       => $request->user()->name,
                'email'      => $request->user()->email,
                'role'       => $request->user()->role,
                'expires_at' => $request->user()->expires_at?->toISOString(),
            ]
        ]);
    })->middleware('throttle:60,1');

    // Assets API
    Route::get('/assets', [AssetApiController::class, 'index']);
    Route::post('/assets', [AssetApiController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/assets/{asset}', [AssetApiController::class, 'show']);
    Route::get('/assets/{asset}/status', function (Asset $asset) {
        $asset->load('metadata');
        return response()->json([
            'status'   => $asset->status,
            'metadata' => $asset->metadata ? [
                'title'        => $asset->metadata->title,
                'description'  => $asset->metadata->description,
                'tags'         => $asset->metadata->tags,
                'ai_generated' => $asset->metadata->ai_generated,
            ] : null,
        ]);
    });
    Route::patch('/assets/{asset}', [AssetApiController::class, 'update']);
    Route::delete('/assets/{asset}', [AssetApiController::class, 'destroy']);
    Route::get('/assets/{asset}/audit', [AssetAuditController::class, 'index']);
    Route::post('/assets/{asset}/variants', [AssetApiController::class, 'variants'])->middleware('throttle:10,1');

    // Búsqueda por lenguaje natural
    Route::post('/search', [SearchApiController::class, 'search'])->middleware('throttle:20,1');

    // RAG — Chat con la base de datos
    Route::post('/rag', [RAGController::class, 'query'])->middleware('throttle:10,1');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Consent management (GDPR) — export route BEFORE apiResource to avoid 'export' being treated as an ID
    Route::get('consents/export/csv', [ConsentController::class, 'exportCsv']);
    Route::post('consents/{consent}/send-request', [ConsentController::class, 'sendRequest']);
    Route::apiResource('consents', ConsentController::class);
    Route::get('assets/{asset}/publication-check', [ConsentController::class, 'publicationCheck']);

    Route::patch('/assets/{asset}/press-kit', [PressRoomController::class, 'toggle']);

    // Emergency Kit
    Route::patch('/assets/{asset}/emergency-kit', [EmergencyKitController::class, 'toggle']);
    Route::get('/emergency-kit/download', [EmergencyKitController::class, 'download']);

    Route::get('/reports/impact', [ReportController::class, 'impact'])->middleware('throttle:3,1');

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Collections (categories) — admin and editor only
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']);
    Route::get('/collections/{id}', [CollectionController::class, 'show']);
    Route::patch('/collections/{id}', [CollectionController::class, 'update']);
    Route::delete('/collections/{id}', [CollectionController::class, 'destroy']);
    Route::patch('/collections/{id}/visibility', [CollectionController::class, 'toggleVisibility']);
    Route::post('/collections/{id}/assets', [CollectionController::class, 'addAsset']);
    Route::delete('/collections/{id}/assets/{assetId}', [CollectionController::class, 'removeAsset']);

    // Admin panel — admin role only
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\Admin\UserController::class, 'index']);
        Route::post('/users', [\App\Http\Controllers\Api\Admin\UserController::class, 'store']);
        Route::patch('/users/{user}', [\App\Http\Controllers\Api\Admin\UserController::class, 'update']);
        Route::patch('/users/{user}/role', [\App\Http\Controllers\Api\Admin\UserController::class, 'changeRole']);
        Route::patch('/users/{user}/deactivate', [\App\Http\Controllers\Api\Admin\UserController::class, 'deactivate']);
        Route::patch('/users/{user}/activate', [\App\Http\Controllers\Api\Admin\UserController::class, 'activate']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Api\Admin\UserController::class, 'destroy']);

        // Organization settings
        Route::get('/settings', [OrganizationSettingsController::class, 'index']);
        Route::patch('/settings', [OrganizationSettingsController::class, 'update']);
        Route::post('/settings/logo', [OrganizationSettingsController::class, 'uploadLogo']);

        // System status health check — throttled to protect external API quotas
        Route::get('/system/status', [SystemStatusController::class, 'status'])->middleware('throttle:10,1');

        // Volunteer management
        Route::get('/volunteers', [VolunteerController::class, 'index']);
        Route::patch('/volunteers/{user}/extend', [VolunteerController::class, 'extend']);
    });
});