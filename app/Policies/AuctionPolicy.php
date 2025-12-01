<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AuctionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * 1) Solo admin crea/edita subastas
     *    y además el plan del artista debe permitir subastas
     *    y la obra debe ser subastable.
     */
    public function create(User $user, Obra $obra): bool
    {
        // Aseguramos rol admin (en minúsculas, como en tu base de datos)
        if ($user->role !== 'admin') {
            return false;
        }

        // Plan del dueño de la obra (usuario artista)
        $plan = $obra->user?->currentPlan()->first();

        // Sin plan o plan sin subastas → no se permite
        if (! $plan || ! $plan->allows_auctions) {
            return false;
        }

        // La obra debe estar marcada como subastable
        if (! $obra->es_subastable) {
            return false;
        }

        return true;
    }

    /**
     * 2) El usuario puede pujar si:
     *    - No es admin (solo compradores)
     *    - No es el artista dueño de la obra
     */
    public function bid(User $user, Auction $auction): bool
    {
        $artistId     = $user->artist->id ?? null;
        $obraArtistId = $auction->obra->artist_id ?? null;

        // Tu lógica original, solo normalizamos el role a minúsculas
        return $user->role !== 'admin' && $artistId !== $obraArtistId;
    }

    /**
     * 3) Solo admin puede finalizar subasta.
     */
    public function finalize(User $user, Auction $auction): bool
    {
        return $user->role === 'admin';
    }

    /**
     * 4) Solo admin puede cancelar subasta.
     */
    public function cancel(User $user, Auction $auction): bool
    {
        return $user->role === 'admin';
    }

    /**
     * 5) Actualizar información de la subasta (admin).
     */
    public function update(User $user, Auction $auction): bool
    {
        return $user->role === 'admin';
    }

    /**
     * 6) Eliminar la subasta (lo mantienes deshabilitado).
     */
    public function delete(User $user, Auction $auction): bool
    {
        return false;
    }

    /**
     * 7) Método store, si lo estás usando explícitamente en algún @can.
     *    Lo dejamos coherente: solo admin.
     */
    public function store(User $user, Auction $auction): bool
    {
        return $user->role === 'admin';
    }

    /**
     * 8) Actualizar fecha límite (admin).
     */
    public function updateDeadline(User $user, Auction $auction): bool
    {
        return $user->role === 'admin';
    }

    /**
     * 9) Restore (no permitido).
     */
    public function restore(User $user, Auction $auction): bool
    {
        return false;
    }

    /**
     * 10) Borrado permanente (no permitido).
     */
    public function forceDelete(User $user, Auction $auction): bool
    {
        return false;
    }
}
