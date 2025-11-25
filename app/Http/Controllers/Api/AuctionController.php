<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Notification;

class AuctionController extends Controller
{
    // Función reutilizable para validar conflictos de fechas
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

    // Método para crear una nueva subasta
    public function store(Request $request)
    {
        $this->authorize('create', Auction::class);

        $validated = $request->validate([
            'obra_id' => 'required|exists:obras,id',
            'precio_inicial' => 'required|numeric|min:0',
            'incremento_minimo' => 'required|numeric|min:1',
            'duracion_dias' => 'nullable|integer|min:1|max:30',
            'fecha_fin_custom' => 'nullable|date|after:now',
            'fecha_inicio' => 'nullable|date|after_or_equal:now',
        ]);

        $obra = \App\Models\Obra::find($validated['obra_id']);
        if (!$obra || $obra->estatus_id != 2 || $obra->es_subastable != 1) {
            return response()->json([
                'error' => 'Solo se pueden subastar obras aceptadas (aprobadas).'
            ], 400);
        }

        $fechaInicio = !empty($validated['fecha_inicio'])
            ? Carbon::parse($validated['fecha_inicio'])
            : Carbon::now();

        if (!empty($validated['fecha_fin_custom'])) {
            $fechaFin = Carbon::parse($validated['fecha_fin_custom']);
        } elseif (!empty($validated['duracion_dias'])) {
            $fechaFin = $fechaInicio->copy()->addDays($validated['duracion_dias']);
        } else {
            return response()->json([
                'error' => 'Debes proporcionar duracion_dias o fecha_fin_custom'
            ], 400);
        }

        if ($this->hasDateConflict($validated['obra_id'], $fechaInicio, $fechaFin)) {
            return response()->json([
                'error' => 'Conflicto: ya existe una subasta activa o programada para esta obra en ese rango de fechas'
            ], 400);
        }

        $estado = $fechaInicio->isFuture() ? 'programada' : 'activa';

        $auction = Auction::create([
            'obra_id' => $validated['obra_id'],
            'precio_inicial' => $validated['precio_inicial'],
            'precio_actual' => $validated['precio_inicial'],
            'incremento_minimo' => $validated['incremento_minimo'],
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'estado' => $estado,
        ]);

        $auction->load('obra');
        // Notification::send(User::where(...), new NewAuctionCreated($auction));

        return response()->json([
            'message' => 'Subasta creada exitosamente',
            'auction' => $auction
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
            'fecha_inicio' => 'nullable|date|after_or_equal:now',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
            'precio_inicial' => 'nullable|numeric|min:0.01',
            'incremento_minimo' => 'nullable|numeric|min:1',
        ]);
        $nuevaInicio = isset($validated['fecha_inicio']) ? Carbon::parse($validated['fecha_inicio']) : $auction->fecha_inicio;
        $nuevaFin = isset($validated['fecha_fin']) ? Carbon::parse($validated['fecha_fin']) : $auction->fecha_fin;

        if ($this->hasDateConflict($auction->obra_id, $nuevaInicio, $nuevaFin, $auction->id)) {
            return response()->json(['error' => 'Conflicto de fechas con otra subasta'], 400);
        }

        $auction->fill($validated)->save();

        return response()->json(['message' => 'Subasta actualizada correctamente', 'auction' => $auction]);
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $auctions = Auction::with(['obra.artist.user', 'bids.user'])
            ->where('estado', 'activa')
            ->where('fecha_fin', '>', Carbon::now())
            ->orderBy('fecha_fin', 'asc')
            ->paginate($perPage);

        $auctions->getCollection()->transform(function ($auction) {
            $auction->tiempo_restante = $auction->tiempoRestante();
            $auction->total_pujas = $auction->bids->count();
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

        $auction->update([
            'fecha_fin' => Carbon::parse($validated['fecha_fin'])
        ]);

        return response()->json([
            'message' => 'Hora límite actualizada exitosamente',
            'auction' => $auction,
            'nueva_fecha_fin' => $auction->fecha_fin
        ]);
    }

    public function show($id)
    {
        $auction = Auction::with(['obra.artist.user', 'bids.user', 'ganador'])
            ->find($id);

        if (!$auction) {
            return response()->json([
                'error' => 'Subasta no encontrada'
            ], 404);
        }

        $data = [
            'id' => $auction->id,
            'obra' => $auction->obra,
            'precio_inicial' => $auction->precio_inicial,
            'precio_actual' => $auction->precio_actual,
            'incremento_minimo' => $auction->incremento_minimo,
            'fecha_inicio' => $auction->fecha_inicio,
            'fecha_fin' => $auction->fecha_fin,
            'estado' => $auction->estado,
            'tiempo_restante' => $auction->tiempoRestante(),
            'is_activa' => $auction->isActiva(),
            'ganador' => $auction->ganador,
            'bids' => $auction->bids,
            'total_pujas' => $auction->bids->count(),
        ];

        return response()->json($data);
    }

    public function placeBid(Request $request, $auctionId)
    {
        $auction = Auction::findOrFail($auctionId);
        $this->authorize('bid', $auction);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $auction = Auction::where('id', $auctionId)->lockForUpdate()->first();

            if (!$auction) {
                DB::rollBack();
                return response()->json(['error' => 'Subasta no encontrada'], 404);
            }

            if (!$auction->isActiva()) {
                DB::rollBack();
                return response()->json(['error' => 'La subasta no está activa'], 400);
            }

            $montoMinimo = $auction->precio_actual + $auction->incremento_minimo;
            if ($auction->bids()->count() == 0) {
                $montoMinimo = $auction->precio_inicial;
            }

            if ($validated['monto'] < $montoMinimo) {
                DB::rollBack();
                return response()->json([
                    'error' => "Alguien ha ofertado antes. El monto mínimo ahora es $" . number_format($montoMinimo, 2),
                    'monto_minimo' => $montoMinimo
                ], 409);
            }

            $bid = Bid::create([
                'auction_id' => $auction->id,
                'user_id' => auth()->id(),
                'monto' => $validated['monto'],
                'fecha_puja' => Carbon::now(),
            ]);

            $tiempoRestante = Carbon::now()->diffInMinutes($auction->fecha_fin, false);
            $huboExtension = false;
            if ($tiempoRestante <= 5 && $tiempoRestante >= 0) {
                $auction->fecha_fin = Carbon::now()->addMinutes(5);
                $huboExtension = true;
            }

            $auction->precio_actual = $validated['monto'];
            $auction->save();

            DB::commit();

            // broadcast(new NewBidPlaced($bid))->toOthers();

            return response()->json([
                'message' => 'Puja exitosa',
                'bid' => $bid,
                'nuevo_precio' => $auction->precio_actual,
                'extended_time' => $huboExtension,
                'nueva_fecha_fin' => $auction->fecha_fin
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error en la puja: ' . $e->getMessage()], 500);
        }
    }

    public function finalize($auctionId)
    {
        $auction = Auction::with('bids')->findOrFail($auctionId);
        $this->authorize('finalize', $auction);

        if ($auction->estado === 'finalizada') {
            return response()->json([
                'error' => 'Esta subasta ya fue finalizada'
            ], 400);
        }

        $pujaMasAlta = $auction->bids()->orderBy('monto', 'desc')->first();

        $auction->update([
            'estado' => 'finalizada',
            'ganador_id' => $pujaMasAlta ? $pujaMasAlta->user_id : null,
        ]);

        $auction->load('ganador');

        return response()->json([
            'message' => 'Subasta finalizada exitosamente',
            'auction' => $auction,
            'ganador' => $auction->ganador,
            'precio_final' => $auction->precio_actual,
            'total_pujas' => $auction->bids->count()
        ]);
    }

    public function cancel($auctionId)
    {
        $auction = Auction::with('bids')->findOrFail($auctionId);
        $this->authorize('cancel', $auction);

        if ($auction->bids->count() > 0) {
            return response()->json([
                'error' => 'No se puede cancelar una subasta que ya tiene pujas'
            ], 400);
        }

        $auction->update([
            'estado' => 'cancelada'
        ]);

        return response()->json([
            'message' => 'Subasta cancelada exitosamente',
            'auction' => $auction
        ]);
    }

    public function myBids()
    {
        $bids = Bid::with(['auction.obra', 'auction.ganador'])
            ->where('user_id', auth()->id())
            ->orderBy('fecha_puja', 'desc')
            ->get();

        $bids = $bids->map(function ($bid) {
            return [
                'id' => $bid->id,
                'monto' => $bid->monto,
                'fecha_puja' => $bid->fecha_puja,
                'obra' => $bid->auction->obra,
                'subasta_estado' => $bid->auction->estado,
                'es_ganadora' => $bid->esGanadora(),
                'subasta_finalizada' => $bid->auction->estado === 'finalizada',
            ];
        });

        return response()->json($bids);
    }
}
