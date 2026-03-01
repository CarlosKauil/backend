<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controladores existentes
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
use Firebase\JWT\JWT;

use App\Http\Controllers\Api\StripeController;
/*use App\Http\Controllers\Auth\FirebaseAuthController;
*/

Route::get('/superset/guest-token', [SupersetController::class, 'getGuestToken']);

Route::get('/plans', [SubscriptionController::class, 'indexPlans']);


// ==========================================
// RUTAS PÚBLICAS (sin autenticación)
// ==========================================

Route::get('/unity-files', [UnityFilesController::class, 'getUnityFiles']);   

//RUTAS QUE NECESITAMOS PARA LOS PLANES DE SUSCRIPCIONES//===============================================================================

/**
 * Login con Firebase
 */
/*
Route::post('/firebase-login', [App\Http\Controllers\Auth\FirebaseLoginController::class, 'login']);
*/
Route::post('/preset/guest-token', [PresetController::class, 'guestToken']);
/**
 * Ruta de prueba
 */
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

/**
 * Áreas (CRUD básico)
 */
Route::apiResource('areas', AreasController::class);

/**
 * Autenticación y usuarios
 */
Route::post('/register', [AuthController::class, 'register']);           // Registro de usuario normal
Route::post('/artist-register', [AuthController::class, 'artistRegister']); // Autoregistro de artista
Route::post('/login', [AuthController::class, 'login']);                 // Login

/** Obras públicas (sin login)*/
Route::get('/obras/aceptadas-public/{area_id}', [ObraController::class, 'aceptadasPublic']);

/** Perfil público del artista (Visualización)*/
Route::get('/artist/{link}', [ProfileController::class, 'showProfile']);

// ==========================================
// 🆕 SUBASTAS - RUTAS PÚBLICAS
// ==========================================

/**
 * Obtener todas las subastas activas
 * Cualquier usuario puede ver las subastas sin autenticarse
 */
Route::get('/auctions', [AuctionController::class, 'index']);
/**
 * Ver detalle de una subasta específica
 * Ejemplo: GET /api/auctions/1
 */
Route::get('/auctions/{id}', [AuctionController::class, 'show']);




// ==========================================
// RUTAS PROTEGIDAS (requieren token)
// ==========================================


Route::middleware('auth:sanctum')->group(function () {


    Route::get('/subscription/me', [SubscriptionController::class, 'mySubscription']);
    Route::post('/subscription/change-plan', [SubscriptionController::class, 'changePlan']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('/admin/subscriptions', [SubscriptionController::class, 'adminIndex']);

    // ------------------------------------------
    // STRIPE - RUTAS PROTEGIDAS (CORREGIDO)
    // ------------------------------------------

    //RUTA DE ACCESO AL CHECKOUT DE STRIPE EN BASE AL ID SUSCRIPCION Y USUARIO LOGEADO
    Route::post('/stripe/checkout', [StripeController::class, 'checkout']);

    //SOLO SI LO DE ARRIBA YA FUNCIONA
    //RUTA DE ACCESO AL PORTAL DE FACTURACION EN BASE AL USUARIO LOGEADO
    Route::post('/stripe/portal', [StripeController::class, 'billingPortal']);


    // ------------------------------------------
    // USUARIO Y AUTENTICACIÓN
    // ------------------------------------------
    
    /**Obtener usuario autenticado*/
    Route::get('/user', [AuthController::class, 'user']);
    /**Ruta solo para admin*/
    Route::get('/admin-only', [AuthController::class, 'adminOnly']);
    /**Logout*/
    Route::post('/logout', [AuthController::class, 'logout']);
    /**CRUD de usuarios*/
    Route::apiResource('users', UserController::class);

    // ------------------------------------------
    // GESTIÓN DE OBRAS
    // ------------------------------------------
    /**Artista sube obra*/
    Route::post('/obras', [ObraController::class, 'store']);
    /**Listar obras (admin o artista)*/
    Route::get('/obras', [ObraController::class, 'index']);
    /**✅ RUTAS ESPECÍFICAS PRIMERO (antes de rutas con parámetros)*/
    Route::get('/obras/pendientes', [ObraController::class, 'pendientes']); // Admin ve pendientes
    Route::get('/obras/aceptadas', [ObraController::class, 'aceptadas']);   // Obras aceptadas
    
    /**✅ RUTAS CON PARÁMETROS DESPUÉS*/
    Route::get('/obras/{id}', [ObraController::class, 'show']);             // Ver obra
    Route::put('/obras/{id}', [ObraController::class, 'update']);           // Admin acepta/rechaza
    Route::delete('/obras/{id}', [ObraController::class, 'destroy']);       // Admin elimina obra

    /**Notificaciones y obras aprobadas*/
    Route::get('/notifications/rejections', [ObraController::class, 'getRejectionMessages']);
    Route::get('/obras-pendientes', [ObraController::class, 'getNewPendingObras']);
    Route::get('/obras-aprobadas', [ObraController::class, 'obrasAprobadas']);

    // ------------------------------------------
    // PERFIL
    // ------------------------------------------
    
    /**Obtener perfil del usuario autenticado*/
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    
    /**Actualizar perfil*/
    Route::put('/profile', [ProfileController::class, 'updateProfile']);

    // ------------------------------------------
    // 🆕 SUBASTAS - RUTAS PROTEGIDAS
    // ------------------------------------------

    /**Crear una nueva subasta*/
    Route::post('/auctions', [AuctionController::class, 'store']);
    /**
     * Realizar una puja en una subasta
     * Ejemplo: POST /api/auctions/1/bid
     * Body JSON:
     * {
     *   "monto": 1500.00
     * }
     */
    Route::post('/auctions/{id}/bid', [AuctionController::class, 'placeBid']);
    /**
     * Finalizar una subasta manualmente (antes de tiempo)
     * Ejemplo: POST /api/auctions/1/finalize
     */
    Route::post('/auctions/{id}/finalize', [AuctionController::class, 'finalize']);
    /**
     * Cancelar una subasta (solo si no tiene pujas)
     * Ejemplo: POST /api/auctions/1/cancel
     */
    Route::post('/auctions/{id}/cancel', [AuctionController::class, 'cancel']);
    Route::get('/auctions/public', [AuctionController::class, 'publicAuctions']);    
    /**
     * Obtener todas las pujas del usuario autenticado
     * Permite ver el historial de pujas realizadas
     */
    Route::get('/my-bids', [AuctionController::class, 'myBids']);

    Route::get('/my-won-auctions', [AuctionController::class, 'myWonAuctions']);

    Route::post('/auctions/{id}/pay', [AuctionController::class, 'processPayment']);
    Route::get('/admin/auctions-report', [AuctionController::class, 'adminIndex']);

    Route::post('/create-payment-intent/{id}', [PaymentController::class, 'createPaymentIntent']);

});