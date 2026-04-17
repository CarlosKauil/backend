<?php

namespace App\Console\Commands;

use App\Jobs\FinalizarSubastaJob;
use App\Models\Auction;
use Illuminate\Console\Command;

class FinalizarSubastasCommand extends Command
{
    protected $signature   = 'subastas:finalizar';
    protected $description = 'Finaliza las subastas cuyo tiempo ha terminado';

    public function handle(): void
    {
        $subastas = Auction::where('estado', 'activa')
            ->where('fecha_fin', '<=', now())
            ->get();

        if ($subastas->isEmpty()) {
            $this->info('No hay subastas para finalizar.');
            return;
        }

        foreach ($subastas as $auction) {
            FinalizarSubastaJob::dispatch($auction);
            $this->info("Job despachado para subasta ID: {$auction->id}");
        }
    }
}