<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OmiseWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Omise Webhook 路由（不需要认证，但需要验证签名）
Route::post('/omise/webhook', [OmiseWebhookController::class, 'handle'])->name('api.omise.webhook');
