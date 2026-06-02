<?php

use App\Http\Controllers\Api\AssetApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\RAGController;
use App\Http\Controllers\Api\SearchApiController;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas públicas de la API
Route::post('/login', [AuthApiController::class, 'login'])->middleware('throttle:5,1');

// Rutas protegidas por token
Route::middleware('auth:sanctum')->group(function () {
    // Cerrar sesión
    Route::post('/logout', [AuthApiController::class, 'logout']);

    // Información del usuario autenticado
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data'    => [
                'id'    => $request->user()->id,
                'name'  => $request->user()->name,
                'email' => $request->user()->email,
                'role'  => $request->user()->role,
            ]
        ]);
    });

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
    Route::post('/assets/{asset}/variants', [AssetApiController::class, 'variants'])->middleware('throttle:10,1');

    // Búsqueda por lenguaje natural
    Route::post('/search', [SearchApiController::class, 'search'])->middleware('throttle:20,1');

    // RAG — Chat con la base de datos
    Route::post('/rag', [RAGController::class, 'query'])->middleware('throttle:10,1');
});