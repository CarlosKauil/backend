<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// ... tus otras rutas de consola si tienes ...

// Esta es la línea mágica para Laravel 11:
Schedule::command('auctions:update-status')->everyMinute();