<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateAuctionStatus extends Command
{
    protected $signature = 'auctions:update-status';
    protected $description = 'Actualiza estados y determina ganadores';

    public function handle()
    {
        $now = Carbon::now();
        $this->info("--- Iniciando actualización [{$now}] ---");

        // 1. FINALIZAR SUBASTAS VENCIDAS (Estén 'activas' O 'programadas')
        // Esto corrige el error: si una programada ya venció su fecha_fin, se cierra directo.
        $toClose = Auction::whereIn('estado', ['activa', 'programada'])
            ->where('fecha_fin', '<=', $now)
            ->get();

        foreach ($toClose as $auction) {
            $this->cerrarSubasta($auction);
        }

        // 2. ABRIR SUBASTAS (Programada -> Activa)
        // Solo las que NO hayan sido cerradas arriba y su fecha de inicio ya pasó
        $toOpen = Auction::where('estado', 'programada')
            ->where('fecha_inicio', '<=', $now)
            ->where('fecha_fin', '>', $now) // Importante: Que no haya vencido
            ->get();

        foreach ($toOpen as $auction) {
            $auction->update(['estado' => 'activa']);
            $this->info("Subasta ID {$auction->id} iniciada (Activa).");
        }
        
        return Command::SUCCESS;
    }

    // Lógica encapsulada para determinar ganador
    protected function cerrarSubasta($auction)
    {
        DB::beginTransaction();
        try {
            // --- AQUÍ SE DETERMINA EL GANADOR ---
            $winningBid = $auction->bids()->orderBy('monto', 'desc')->first();

            $auction->estado = 'finalizada';

            if ($winningBid) {
                $auction->ganador_id = $winningBid->user_id;
                $auction->precio_actual = $winningBid->monto; // Fijar precio final
                $this->info("Subasta ID {$auction->id} finalizada. GANADOR: Usuario {$winningBid->user_id} con ${$winningBid->monto}");
            } else {
                $this->info("Subasta ID {$auction->id} finalizada. DESIERTA (Sin pujas).");
            }

            $auction->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error cerrando ID {$auction->id}: " . $e->getMessage());
        }
    }
}