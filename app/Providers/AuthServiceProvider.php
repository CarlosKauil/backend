<?php
use App\Models\Obra;
use App\Models\Auction;
use App\Policies\ObraPolicy;
use App\Policies\AuctionPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Obra::class    => ObraPolicy::class,
        Auction::class => AuctionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
