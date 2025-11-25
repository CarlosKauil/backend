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


use Firebase\JWT\JWT;

/*use App\Http\Controllers\Auth\FirebaseAuthController;
*/




// ==========================================
// RUTAS PÚBLICAS (sin autenticación)
// ==========================================

/**
 * Login con Firebase
 */
/*
Route::post('/firebase-login', [App\Http\Controllers\Auth\FirebaseLoginController::class, 'login']);
*/
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

/**
 * Obras públicas (sin login)
 */
Route::get('/obras/aceptadas-public/{area_id}', [ObraController::class, 'aceptadasPublic']);

/**
 * Perfil público del artista (Visualización)
 */
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
    
    // ------------------------------------------
    // USUARIO Y AUTENTICACIÓN
    // ------------------------------------------
    
    /**
     * Obtener usuario autenticado
     */
    Route::get('/user', [AuthController::class, 'user']);
    
    /**
     * Ruta solo para admin
     */
    Route::get('/admin-only', [AuthController::class, 'adminOnly']);
    
    /**
     * Logout
     */
    Route::post('/logout', [AuthController::class, 'logout']);
    
    /**
     * CRUD de usuarios
     */
    Route::apiResource('users', UserController::class);

    // ------------------------------------------
    // GESTIÓN DE OBRAS
    // ------------------------------------------
    
    /**
     * Artista sube obra
     */
    Route::post('/obras', [ObraController::class, 'store']);
    
    /**
     * Listar obras (admin o artista)
     */
    Route::get('/obras', [ObraController::class, 'index']);
    
    /**
     * ✅ RUTAS ESPECÍFICAS PRIMERO (antes de rutas con parámetros)
     */
    Route::get('/obras/pendientes', [ObraController::class, 'pendientes']); // Admin ve pendientes
    Route::get('/obras/aceptadas', [ObraController::class, 'aceptadas']);   // Obras aceptadas
    
    /**
     * ✅ RUTAS CON PARÁMETROS DESPUÉS
     */
    Route::get('/obras/{id}', [ObraController::class, 'show']);             // Ver obra
    Route::put('/obras/{id}', [ObraController::class, 'update']);           // Admin acepta/rechaza
    Route::delete('/obras/{id}', [ObraController::class, 'destroy']);       // Admin elimina obra

    /**
     * Notificaciones y obras aprobadas
     */
    Route::get('/notifications/rejections', [ObraController::class, 'getRejectionMessages']);
    Route::get('/obras-pendientes', [ObraController::class, 'getNewPendingObras']);
    Route::get('/obras-aprobadas', [ObraController::class, 'obrasAprobadas']);

    // ------------------------------------------
    // PERFIL
    // ------------------------------------------
    
    /**
     * Obtener perfil del usuario autenticado
     */
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    
    /**
     * Actualizar perfil
     */
    Route::put('/profile', [ProfileController::class, 'updateProfile']);

    // ------------------------------------------
    // METABASE / DASHBOARD
    // ------------------------------------------
    
    /**
     * Dashboard de Vartica
     */
   

    // ------------------------------------------
    // 🆕 SUBASTAS - RUTAS PROTEGIDAS
    // ------------------------------------------
    
    /**
     * Crear una nueva subasta
     * Body JSON:
     * {
     *   "obra_id": 1,
     *   "precio_inicial": 1000.00,
     *   "incremento_minimo": 100.00,
     *   "duracion_dias": 7
     * }
     */
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
});
