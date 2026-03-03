<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; // <-- ¡ESTA ES LA LÍNEA CLAVE!
use Illuminate\Http\Request;
class StripeController extends Controller
{
    // Generar enlace de pago (Checkout)
    public function checkout(Request $request)
    {
    // Si Laravel no lee el JSON, esto nos dirá por qué.
    
    // 1. Obtenemos la suscripcion (producto stripe)
    $priceId = $request->input('price_id');

    // 2. Si falla, devolvemos un reporte completo para ver el error
    if (!$priceId) {
        return response()->json([
            'error' => 'No se recibió ningún producto',
            'debug_info' => [
                'toda_la_data' => $request->all(), // ¿Está vacío?
                'header_content_type' => $request->header('Content-Type'), // ¿Dice application/json?
                'body_crudo' => $request->getContent() // ¿Llega el texto JSON puro?
            ]
        ], 400);
    }
        // Solicitamos el usuario logeado
        $user = $request->user();

        // 2. Generar sesión
        try {
            $checkout = $user->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => env('FRONTEND_URL') . '/SuscripcionCompletada',
                    'cancel_url' => env('FRONTEND_URL') . '/SuscripcionIncompleta',
                ]);

            return response()->json(['url' => $checkout->url]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    // LO ULTIMO CUANDO TODO LO DE ARRIBA FUNCIONE
    // Generar enlace al Portal de Cliente (Para cancelar/renovar)
    public function billingPortal(Request $request)
    {
        $user = $request->user();

        // Genera una URL temporal para entrar al portal de Stripe
        $url = $user->billingPortalUrl(env('FRONTEND_URL') . '/dashboard');

        return response()->json(['url' => $url]);
    }
}