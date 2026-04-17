<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Price;

class SubscriptionController extends Controller
{
    // Estado actual de la suscripción del usuario
   public function status(Request $request)
{
    $user = $request->user();
    $subscription = $user->subscriptions()->where('stripe_status', 'active')->first();

    // Obtener precio real desde Stripe
    Stripe::setApiKey(env('STRIPE_SECRET'));
    $stripePrice = Price::retrieve(env('STRIPE_PRICE_PRO'));

    return response()->json([
        'plan'              => $user->plan,
        'subscribed'        => !is_null($subscription),
        'ends_at'           => $subscription?->ends_at,
        'limits'            => config("plans.{$user->plan}"),
        'gallery_requested' => $user->gallery_requested,
        'pro_price'         => [
            'amount'   => $stripePrice->unit_amount / 100, // convierte centavos
            'currency' => strtoupper($stripePrice->currency),
            'label'    => '$' . number_format($stripePrice->unit_amount / 100, 2) . ' ' . strtoupper($stripePrice->currency) . ' / mes',
        ],
    ]);
}

    // Crear sesión de Stripe Checkout
    public function checkout(Request $request)
    {
        $user = $request->user();

        $session = $user->newSubscription('default', env('STRIPE_PRICE_PRO'))
            ->checkout([
                'success_url' => env('FRONTEND_URL') . '/dashboard?success=1',
                'cancel_url'  => env('FRONTEND_URL') . '/pricing?canceled=1',
            ]);

        return response()->json(['url' => $session->url]);
    }

    // Cancelar suscripción
    public function cancel(Request $request)
    {
        $user = $request->user();

        if (!$user->subscribed('default')) {
            return response()->json(['message' => 'No tienes una suscripción activa.'], 422);
        }

        $user->subscription('default')->cancel();

        return response()->json(['message' => 'Suscripción cancelada al final del período.']);
    }

    // Solicitud plan Galería
    public function galleryRequest(Request $request)
    {
        $user = $request->user();

        if ($user->gallery_requested) {
            return response()->json(['message' => 'Ya enviaste una solicitud anteriormente.'], 422);
        }

        $user->update(['gallery_requested' => true]);

        // Aquí después puedes agregar un Mail::to('ventas@tuempresa.com')...

        return response()->json(['message' => 'Solicitud enviada. Te contactaremos pronto.']);
    }
}