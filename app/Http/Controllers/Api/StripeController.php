<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
    // Generar enlace de pago (Checkout)
    public function checkout(Request $request)
    {
        $priceId = $request->input('price_id');

        if (!$priceId) {
            return response()->json([
                'error' => 'No se recibió ningún producto',
                'debug_info' => [
                    'toda_la_data' => $request->all(),
                    'header_content_type' => $request->header('Content-Type'),
                    'body_crudo' => $request->getContent()
                ]
            ], 400);
        }

        try {

            Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',

                // Stripe enviará el session_id cuando el pago termine
                'success_url' => env('FRONTEND_URL') . '/SuscripcionCompletada?session_id={CHECKOUT_SESSION_ID}',

                'cancel_url' => env('FRONTEND_URL') . '/SuscripcionIncompleta',
            ]);

            return response()->json([
                'url' => $session->url
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // (Opcional por ahora) Portal de cliente - requiere implementación adicional
    public function billingPortal(Request $request)
    {
        return response()->json([
            'error' => 'Portal de facturación no implementado sin Cashier.'
        ], 400);
    }
}