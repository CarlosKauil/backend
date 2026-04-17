<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

class VencerPagosCommand extends Command
{
    protected $signature   = 'subastas:vencer-pagos';
    protected $description = 'Marca como vencidos los pagos que superaron el deadline';

    public function handle(): void
    {
        $vencidas = Auction::where('estado', 'finalizada')
            ->where('pago_status', 'pendiente')
            ->where('payment_deadline', '<=', now())
            ->get();

        foreach ($vencidas as $auction) {
            $auction->update(['pago_status' => 'vencido']);
            $this->info("Pago vencido para subasta ID: {$auction->id}");
        }

        $this->info("Total vencidas: {$vencidas->count()}");
    }
}