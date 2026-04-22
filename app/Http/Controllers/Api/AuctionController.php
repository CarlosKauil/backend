<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Obra;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentReceived;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class AuctionController extends Controller
{
    protected function hasDateConflict($obraId, $fechaInicio, $fechaFin, $excludeId = null)
    {
        $query = Auction::where('obra_id', $obraId)
            ->whereIn('estado', ['programada', 'activa'])
            ->where(function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
                    ->orWhereBetween('fecha_fin', [$fechaInicio, $fechaFin])
                    ->orWhere(function ($q2) use ($fechaInicio, $fechaFin) {
                        $q2->where('fecha_inicio', '<=', $fechaInicio)
                            ->where('fecha_fin', '>=', $fechaFin);
                    });
            });

        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'obra_id'           => 'required|exists:obras,id',
            'precio_inicial'    => 'required|numeric|min:1',
            'incremento_minimo' => 'required|numeric|min:1',
            'fecha_inicio'      => 'required|date|after_or_equal:now',
            'fecha_fin'         => 'required|date|after:fecha_inicio',
        ]);

        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $obra = Obra::find($validated['obra_id']);

        if (!$obra || $obra->estatus_id != 2 || !$obra->es_subastable) {
            return response()->json(['error' => 'La obra no es aceptada o no está marcada como subastable'], 400);
        }

        $subastaExistente = Auction::where('obra_id', $obra->id)
            ->whereIn('estado', ['programada', 'activa'])
            ->where(function ($q) use ($validated) {
                $q->where(function ($q2) use ($validated) {
                    $q2->where('fecha_inicio', '<', $validated['fecha_fin'])
                        ->where('fecha_fin',   '>', $validated['fecha_inicio']);
                });
            })
            ->first();

        if ($subastaExistente) {
            return response()->json(['error' => 'Ya existe una subasta activa o programada para esta obra en ese periodo.'], 400);
        }

        $fechaInicio = Carbon::parse($validated['fecha_inicio']);
        $estado = $fechaInicio->isFuture() ? 'programada' : 'activa';

        $auction = Auction::create([
            'obra_id'           => $validated['obra_id'],
            'precio_inicial'    => $validated['precio_inicial'],
            'precio_actual'     => $validated['precio_inicial'],
            'incremento_minimo' => $validated['incremento_minimo'],
            'fecha_inicio'      => $validated['fecha_inicio'],
            'fecha_fin'         => $validated['fecha_fin'],
            'estado'            => $estado,
        ]);

        return response()->json([
            'message' => "Subasta {$estado} creada correctamente.",
            'auction' => $auction,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $auction = Auction::findOrFail($id);
        $this->authorize('update', $auction);

        if ($auction->estado !== 'programada') {
            return response()->json(['error' => 'Solo puedes editar subastas programadas'], 400);
        }

        $validated = $request->validate([
            'fecha_inicio'      => 'nullable|date|after_or_equal:now',
            'fecha_fin'         => 'nullable|date|after:fecha_inicio',
            'precio_inicial'    => 'nullable|numeric|min:0.01',
            'incremento_minimo' => 'nullable|numeric|min:1',
        ]);

        $nuevaInicio = isset($validated['fecha_inicio']) ? Carbon::parse($validated['fecha_inicio']) : $auction->fecha_inicio;
        $nuevaFin    = isset($validated['fecha_fin'])    ? Carbon::parse($validated['fecha_fin'])    : $auction->fecha_fin;

        if ($this->hasDateConflict($auction->obra_id, $nuevaInicio, $nuevaFin, $auction->id)) {
            return response()->json(['error' => 'Conflicto de fechas con otra subasta'], 400);
        }

        $auction->fill($validated)->save();

        return response()->json(['message' => 'Subasta actualizada correctamente', 'auction' => $auction]);
    }

    public function index(Request $request)
    {
        $perPage  = $request->input('per_page', 15);
        $auctions = Auction::with(['obra.artist.user', 'bids.user'])
            ->where('estado', 'activa')
            ->where('fecha_fin', '>', Carbon::now())
            ->orderBy('fecha_fin', 'asc')
            ->paginate($perPage);

        $auctions->getCollection()->transform(function ($auction) {
            $auction->tiempo_restante = $auction->tiempoRestante();
            $auction->total_pujas     = $auction->bids->count();
            return $auction;
        });

        return response()->json($auctions);
    }

    public function updateDeadline(Request $request, $auctionId)
    {
        $auction = Auction::findOrFail($auctionId);
        $this->authorize('update', $auction);

        $validated = $request->validate([
            'fecha_fin' => 'required|date|after:now',
        ]);

        if ($auction->estado === 'finalizada' || $auction->estado === 'cancelada') {
            return response()->json([
                'error' => 'No se puede modificar la hora límite de una subasta finalizada o cancelada'
            ], 400);
        }

        $auction->update(['fecha_fin' => Carbon::parse($validated['fecha_fin'])]);

        return response()->json([
            'message'         => 'Hora límite actualizada exitosamente',
            'auction'         => $auction,
            'nueva_fecha_fin' => $auction->fecha_fin
        ]);
    }

    public function show($id)
    {
        $relations = ['obra.artist.user', 'obra.area', 'bids.user', 'ganador'];

        $auction = Auction::with($relations)->find($id);

        if (!$auction) {
            $auction = Auction::with($relations)
                ->where('obra_id', $id)
                ->latest()
                ->first();
        }

        if (!$auction) {
            return response()->json(['error' => 'Subasta no encontrada'], 404);
        }

        return response()->json([
            'id'                => $auction->id,
            'obra'              => $auction->obra,
            'precio_inicial'    => $auction->precio_inicial,
            'precio_actual'     => $auction->precio_actual,
            'incremento_minimo' => $auction->incremento_minimo,
            'fecha_inicio'      => $auction->fecha_inicio,
            'fecha_fin'         => $auction->fecha_fin,
            'estado'            => $auction->estado,
            'tiempo_restante'   => $auction->tiempoRestante(),
            'is_activa'         => $auction->isActiva(),
            'ganador'           => $auction->ganador,
            'bids'              => $auction->bids,
            'total_pujas'       => $auction->bids->count(),
            'monto_ganador'     => $auction->monto_ganador,
            'payment_deadline'  => $auction->payment_deadline,
            'pago_status'       => $auction->pago_status,
        ]);
    }

    /**
     * Obtener las obras compradas por el usuario autenticado
     * GET /api/my-purchases
     */
    public function myPurchases()
    {
        $userId = Auth::id();

        $purchases = Auction::with(['obra.artist.user'])
            ->where('ganador_id', $userId)
            ->where('estado', 'finalizada')
            ->where('pago_status', 'pagado')
            ->orderBy('fecha_pago', 'desc')
            ->get()
            ->map(function ($auction) {
                return [
                    'auction_id'    => $auction->id,
                    'obra_nombre'   => $auction->obra->nombre ?? $auction->obra->titulo ?? 'Sin título',
                    'obra_imagen'   => $auction->obra->archivo_url ?? null,
                    'artista'       => $auction->obra->artist->user->name ?? 'Desconocido',
                    'monto_pagado'  => $auction->monto_ganador,
                    'fecha_compra'  => $auction->fecha_pago,
                ];
            });

        return response()->json($purchases);
    }

   
}