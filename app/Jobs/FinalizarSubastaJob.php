<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Notifications\SubastaGanadaNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizarSubastaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Auction $auction) {}

    public function handle(): void
    {
        // Evitar doble procesamiento si ya fue finalizada
        if ($this->auction->estado !== 'activa') {
            return;
        }

        $pujaMasAlta = $this->auction->bids()
            ->orderBy('monto', 'desc')
            ->with('user')
            ->first();

        if (!$pujaMasAlta) {
            // Sin pujas → cancelar
            $this->auction->update(['estado' => 'cancelada']);
            return;
        }

        // Horas que tiene el ganador para pagar (puedes moverlo a config)
        $horasParaPagar = 24;

        $this->auction->update([
            'estado'           => 'finalizada',
            'ganador_id'       => $pujaMasAlta->user_id,
            'monto_ganador'    => $pujaMasAlta->monto,
            'payment_deadline' => now()->addHours($horasParaPagar),
            'pago_status'      => 'pendiente',
        ]);

        // Notificar al ganador
        $pujaMasAlta->user->notify(new SubastaGanadaNotification($this->auction));
    }
}