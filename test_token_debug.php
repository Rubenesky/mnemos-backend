<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$user = \App\Models\User::factory()->create();
$tokenRecord = $user->createToken('test');
$plainToken = $tokenRecord->plainTextToken;

echo "Plain token: " . substr($plainToken, 0, 20) . "...\n";
echo "Token record ID: " . $tokenRecord->accessToken->id . "\n";
echo "User tokens count before delete: " . $user->tokens()->count() . "\n";

$user->tokens()->delete();
echo "User tokens count after delete: " . $user->tokens()->count() . "\n";
