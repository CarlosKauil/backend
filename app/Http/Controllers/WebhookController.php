<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhook;

class WebhookController extends CashierWebhook
{
    public function handleCustomerSubscriptionCreated(array $payload): void
    {
        $stripeId = $payload['data']['object']['customer'];
        $status   = $payload['data']['object']['status'];

        Log::info('handleCustomerSubscriptionCreated', [
            'stripe_id' => $stripeId,
            'status'    => $status,
        ]);

        if ($status === 'active') {
            $user = User::where('stripe_id', $stripeId)->first();
            $user?->update(['plan' => 'pro']);
        }
    }

    public function handleCustomerSubscriptionUpdated(array $payload): void
    {
        $stripeId = $payload['data']['object']['customer'];
        $status   = $payload['data']['object']['status'];

        Log::info('handleCustomerSubscriptionUpdated', [
            'stripe_id' => $stripeId,
            'status'    => $status,
        ]);

        $user = User::where('stripe_id', $stripeId)->first();
        if (!$user) return;

        if ($status === 'active') {
            $user->update(['plan' => 'pro']);
        } elseif (in_array($status, ['canceled', 'unpaid', 'past_due'])) {
            $user->update(['plan' => 'free']);
        }
    }

    public function handleCustomerSubscriptionDeleted(array $payload): void
    {
        $stripeId = $payload['data']['object']['customer'];

        Log::info('handleCustomerSubscriptionDeleted', [
            'stripe_id' => $stripeId,
        ]);

        $user = User::where('stripe_id', $stripeId)->first();
        $user?->update(['plan' => 'free']);
    }

    // ✅ Nuevo: maneja pagos de subastas
    public function handlePaymentIntentSucceeded(array $payload): void
    {
        $intent   = $payload['data']['object'];
        $metadata = $intent['metadata'] ?? [];

        Log::info('handlePaymentIntentSucceeded', [
            'intent_id' => $intent['id'],
            'metadata'  => $metadata,
        ]);

        // Solo procesar si es pago de subasta
        if (!isset($metadata['auction_id'])) {
            return;
        }

        $auction = \App\Models\Auction::find($metadata['auction_id']);

        if (!$auction || $auction->pago_status === 'pagado') {
            return;
        }

        DB::beginTransaction();
        try {
            $auction->update([
                'pago_status'    => 'pagado',
                'fecha_pago'     => now(),
                'transaccion_id' => $intent['id'],
            ]);

            try {
                Mail::to($auction->ganador->email)
                    ->send(new \App\Mail\PaymentReceived($auction));
            } catch (\Exception $e) {
                Log::error('Error correo pago subasta: ' . $e->getMessage());
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error webhook pago subasta: ' . $e->getMessage());
        }
    }
}