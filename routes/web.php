<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
     ->middleware(['auth', 'verified'])
     ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Rutas de perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Listar assets — todos los usuarios autenticados pueden ver
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');

    // Solo admins y editors pueden subir y editar
    Route::middleware('role:admin,editor')->group(function () {
        Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::patch('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    });

    // Solo admins pueden borrar assets
    Route::middleware('role:admin')->group(function () {
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    });

    // Ver asset individual — al final para no interceptar /create
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');

    // Categorías — solo admins
    Route::middleware('role:admin')->group(function () {
        Route::resource('categories', CategoryController::class)
             ->except(['show']);
    });

    // Panel de administración — solo admins
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)
             ->except(['create', 'store', 'show']);
    });

    // Exportaciones — solo admins
    Route::middleware('role:admin')->prefix('export')->name('export.')->group(function () {
        Route::get('/assets', [ExportController::class, 'assets'])->name('assets');
    });
});

require __DIR__.'/auth.php';