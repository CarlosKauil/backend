<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    // 1) Listar planes activos (público)
    public function indexPlans(): JsonResponse
    {
        $plans = Plan::active()->get();

        return response()->json($plans);
    }

    // 2) Ver mi suscripción actual (auth)
    public function mySubscription(): JsonResponse
    {
        $user = Auth::user();

        $subscription = $user->subscription()
            ->with('plan')
            ->first();

        return response()->json($subscription);
    }

    // 3) Crear o cambiar de plan (auth)
    public function changePlan(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'plan_id'       => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::active()->findOrFail($validated['plan_id']);

        // Aquí luego engancharás la lógica de pago (Stripe, etc.)
        // antes de confirmar la suscripción.

        DB::transaction(function () use ($user, $plan, $validated) {
            // 3.1 Cancelar suscripción actual (si existe)
            $user->subscription()
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            // 3.2 Crear nueva suscripción
            $startsAt = now();
            $endsAt   = $validated['billing_cycle'] === 'yearly'
                ? now()->addYear()
                : now()->addMonth();

            $user->subscription()->create([
                'plan_id'       => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'status'        => 'active',
                'starts_at'     => $startsAt,
                'ends_at'       => $endsAt,
            ]);
        });

        $fresh = $user->subscription()->with('plan')->first();

        return response()->json([
            'message'      => 'Plan actualizado correctamente.',
            'subscription' => $fresh,
        ], 201);
    }

    // 4) Cancelar mi suscripción (auth)
    public function cancel(): JsonResponse
    {
        $user = Auth::user();

        $subscription = $user->subscription()
            ->active()
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'No tienes una suscripción activa.',
            ], 404);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Suscripción cancelada correctamente.',
        ]);
    }

    // 5) Admin: listar todas las suscripciones con filtros básicos
    public function adminIndex(Request $request): JsonResponse
    {
        // Asume que tienes un middleware/policy que limita a admins
        $query = Subscription::with(['user', 'plan'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->plan_id, fn ($q) => $q->where('plan_id', $request->plan_id));

        $subs = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($subs);
    }
}
