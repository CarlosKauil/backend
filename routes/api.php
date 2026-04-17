<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AreasController;
use App\Http\Controllers\Api\ObraController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\UnityFilesController;
use App\Http\Controllers\SupersetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\PresetController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;

Route::get('/superset/guest-token', [SupersetController::class, 'getGuestToken']);
Route::get('/plans', [SubscriptionController::class, 'indexPlans']);

// ==========================================
// RUTAS PÚBLICAS (sin autenticación)
// ==========================================

Route::get('/unity-files', [UnityFilesController::class, 'getUnityFiles']);
Route::post('/preset/guest-token', [PresetController::class, 'guestToken']);

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::apiResource('areas', AreasController::class);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/artist-register', [AuthController::class, 'artistRegister']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/obras/aceptadas-public/{area_id}', [ObraController::class, 'aceptadasPublic']);
Route::get('/artist/{link}', [ProfileController::class, 'showProfile']);

// ==========================================
// 🆕 SUBASTAS - RUTAS PÚBLICAS
// ✅ /auctions/{id} va AL FINAL para no atrapar otras rutas
// ==========================================
Route::get('/auctions', [AuctionController::class, 'index']);
// ⚠️ /auctions/{id} se mueve al final de todo el archivo

// ==========================================
// RUTAS PROTEGIDAS (requieren token)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/subscription/status', [SubscriptionController::class, 'status']);
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscription/gallery-request', [SubscriptionController::class, 'galleryRequest']);

    // USUARIO Y AUTENTICACIÓN
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/admin-only', [AuthController::class, 'adminOnly']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('users', UserController::class);

    // GESTIÓN DE OBRAS
    Route::post('/obras', [ObraController::class, 'store']);
    Route::get('/obras', [ObraController::class, 'index']);
    Route::get('/obras/pendientes', [ObraController::class, 'pendientes']);
    Route::get('/obras/aceptadas', [ObraController::class, 'aceptadas']);
    Route::get('/obras/{id}', [ObraController::class, 'show']);
    Route::put('/obras/{id}', [ObraController::class, 'update']);
    Route::delete('/obras/{id}', [ObraController::class, 'destroy']);

    Route::get('/notifications/rejections', [ObraController::class, 'getRejectionMessages']);
    Route::get('/obras-pendientes', [ObraController::class, 'getNewPendingObras']);
    Route::get('/obras-aprobadas', [ObraController::class, 'obrasAprobadas']);

    // PERFIL
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);

    // 🆕 SUBASTAS - RUTAS PROTEGIDAS
    // ✅ Rutas específicas PRIMERO
    Route::get('/my-bids', [AuctionController::class, 'myBids']);
    Route::get('/my-won-auctions', [AuctionController::class, 'myWonAuctions']);
    Route::get('/admin/auctions-report', [AuctionController::class, 'adminIndex']);
    Route::get('/auctions/public', [AuctionController::class, 'publicAuctions']);

    Route::post('/auctions', [AuctionController::class, 'store']);
    Route::post('/auctions/{id}/bid', [AuctionController::class, 'placeBid']);
    Route::post('/auctions/{id}/finalize', [AuctionController::class, 'finalize']);
    Route::post('/auctions/{id}/cancel', [AuctionController::class, 'cancel']);
    Route::post('/auctions/{id}/pay', [AuctionController::class, 'processPayment']);
   Route::post('/create-payment-intent/{id}', [AuctionController::class, 'createPaymentIntent']);
});

Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook']);

// ✅ /auctions/{id} AL FINAL para no interferir con rutas específicas
Route::get('/auctions/{id}', [AuctionController::class, 'show']);

Route::middleware(['auth:sanctum', 'plan:pro,gallery'])->group(function () {
    // Route::get('/feature-exclusiva', [...]);
});

Route::middleware(['auth:sanctum', 'plan:gallery'])->group(function () {
    // Route::get('/feature-gallery', [...]);
});