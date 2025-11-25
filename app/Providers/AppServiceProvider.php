<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
    // app/Providers/AuthServiceProvider.php

    protected $policies = [
        \App\Models\Auction::class => \App\Policies\AuctionPolicy::class,
    ];


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
