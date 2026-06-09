<?php

use Illuminate\Support\Facades\Route;

// Health check — used to verify Laravel boots and routing works
Route::get('/ping', fn () => response()->json(['ok' => true, 'env' => app()->environment()]));

// Root — confirms service is alive
Route::get('/', fn () => response()->json(['service' => 'mnemos-backend', 'status' => 'running']));
