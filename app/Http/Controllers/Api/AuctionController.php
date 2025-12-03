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
use Illuminate\Support\Facades\Mail; // 📧 Para correos
use App\Mail\PaymentReceived;        // 📧 Tu Mailable
use Stripe\Stripe;                   // 💳 Stripe
use Stripe\PaymentIntent;            // 💳 Stripe Intent

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
        // Incluimos 'obra.area' para los iconos del frontend
        $auction = Auction::with(['obra.artist.user', 'obra.area', 'bids.user', 'ganador'])
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

    // Obtener las subastas que el usuario autenticado ha ganado
    public function myWonAuctions()
    {
        $userId = Auth::id();

        $wonAuctions = Auction::with(['obra.artist.user', 'obra.area']) // Incluimos obra.area
            ->where('ganador_id', $userId)
            ->where('estado', 'finalizada')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($wonAuctions);
    }

    // ==========================================
    // 💳 MÉTODOS DE PAGO (STRIPE)
    // ==========================================

    /**
     * Paso 1: Crear la Intención de Pago en Stripe
     * Calcula comisiones y retorna el ClientSecret al frontend
     */
    public function createPaymentIntent(Request $request, $auctionId)
    {
        $user = Auth::user();
        $auction = Auction::with('obra')->findOrFail($auctionId);

        // Seguridad
        if ($auction->ganador_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        if ($auction->pago_status === 'pagado') {
            return response()->json(['error' => 'Esta subasta ya fue pagada'], 400);
        }

        // --- CÁLCULO DE PRECIOS ---
        $subtotal = $auction->precio_actual;
        
        // Comisión Stripe México estándar: 3.6% + $3.00 MXN
        $tasa = 0.036; 
        $fijo = 3.00;
        
        $comisionBase = ($subtotal * $tasa) + $fijo;
        $ivaComision = $comisionBase * 0.16; // IVA del 16% sobre la comisión
        
        $comisionTotal = $comisionBase + $ivaComision;
        $total = $subtotal + $comisionTotal;

        // Stripe usa centavos
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
                'breakdown' => [
                    'subtotal' => $subtotal,
                    'comision' => $comisionTotal,
                    'total' => $total
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error Stripe: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Paso 2: Confirmar el Pago y Guardar en BD
     * Se llama después de que Stripe confirma el éxito en el frontend
     */
    public function processPayment(Request $request, $auctionId)
    {
        $auction = Auction::with(['obra.artist', 'ganador'])->findOrFail($auctionId);

        if ($auction->ganador_id !== Auth::id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($auction->pago_status === 'pagado') {
            return response()->json(['error' => 'Esta subasta ya fue pagada'], 400);
        }

        DB::beginTransaction();
        try {
            // Recibimos el ID de transacción real de Stripe (o simulado si no hay)
            $transactionId = $request->input('transaction_id', 'TRX-' . strtoupper(uniqid()));

            // 1. Actualizar BD
            $auction->update([
                'pago_status' => 'pagado',
                'fecha_pago' => Carbon::now(),
                'transaccion_id' => $transactionId,
            ]);
            
            // 2. ENVIAR CORREO (Si falla el correo, no revertimos el pago, solo logueamos)
            try {
                Mail::to($auction->ganador->email)->send(new PaymentReceived($auction));
            } catch (\Exception $emailError) {
                \Log::error('Error enviando correo de recibo: ' . $emailError->getMessage());
            }

            DB::commit();

            return response()->json([
                'message' => 'Pago procesado exitosamente',
                'auction' => $auction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al procesar el pago en servidor'], 500);
        }
    }

    // Obtener reporte completo para el Administrador
    public function adminIndex(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'Admin') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $auctions = Auction::with(['obra.area', 'ganador']) // Incluimos area para el dashboard
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total_ventas' => $auctions->where('pago_status', 'pagado')->sum('precio_actual'),
            'pendientes_pago' => $auctions->where('estado', 'finalizada')->where('pago_status', '!=', 'pagado')->count(),
            'activas' => $auctions->where('estado', 'activa')->count(),
        ];

        return response()->json([
            'auctions' => $auctions,
            'stats' => $stats
        ]);
    }
}