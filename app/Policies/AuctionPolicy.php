<?php

namespace App\Policies;

use App\Models\Auction;
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
     * Determine whether the user can view the model.
     */
    public function create(User $user)
    {
        return $user->role === 'Admin';
    }

   public function bid(User $user, Auction $auction)
    {
        $artistId = $user->artist->id ?? null;
        $obraArtistId = $auction->obra->artist_id ?? null;
        return $user->role !== 'Admin' && $artistId !== $obraArtistId;
    }

        
    public function finalize(User $user, Auction $auction)
    {
        return $user->role === 'Admin';
    }

    public function cancel(User $user, Auction $auction)
    {
        return $user->role === 'Admin';
    }

    /**
     * Determine whether the user can create models.
     */
   
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Auction $auction): bool
    {
       return $user->role === 'Admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Auction $auction): bool
    {
        return false;
    }
     public function store(User $user, Auction $auction): bool
    {
        return false;
    }
    public function updateDeadline(User $user, Auction $auction): bool
    {
       return $user->role === 'Admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Auction $auction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Auction $auction): bool
    {
        return false;
    }
}
