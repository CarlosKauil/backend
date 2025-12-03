<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class PaymentController extends Controller
{
    public function createPaymentIntent(Request $request, $auctionId)
    {
        $user = Auth::user();
        $auction = Auction::with('obra')->findOrFail($auctionId);

        // Validaciones de seguridad
        if ($auction->ganador_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        if ($auction->pago_status === 'pagado') {
            return response()->json(['error' => 'Esta subasta ya fue pagada'], 400);
        }

        // --- CÁLCULO DE COMISIÓN (3.6% + $3.00 MXN) ---
        $subtotal = $auction->precio_actual;
        
        // Fórmula: (Subtotal * 0.036) + 3
        $comision = ($subtotal * 0.036) + 3;
        
        // Agregamos IVA a la comisión (16%) - Opcional, pero recomendado en MX
        $ivaComision = $comision * 0.16;
        
        // Total a cobrar al cliente
        $total = $subtotal + $comision + $ivaComision;

        // Stripe trabaja con centavos (multiplicar por 100)
        $amountInCents = round($total * 100);

        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => 'mxn',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'auction_id' => $auction->id,
                    'user_id' => $user->id,
                    'obra' => $auction->obra->nombre
                ],
            ]);

            return response()->json([
                'clientSecret' => $paymentIntent->client_secret,
                'details' => [
                    'subtotal' => $subtotal,
                    'comision' => $comision + $ivaComision, // Comisión total mostrada al usuario
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}