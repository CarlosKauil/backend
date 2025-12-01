<?php

namespace Database\Seeders;
use App\Models\Plan;

use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['slug' => 'basic'],
            [
                'name' => 'Plan Básico',
                'type' => 'user',
                'price_monthly' => 0,
                'currency' => 'MXN',
                'max_works_per_area' => 3,
                'max_file_size_mb' => 50, // ej. 50MB
                'commission_percent' => 35,
                'allows_auctions' => true,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Plan Pro',
                'type' => 'user',
                'price_monthly' => 99,
                'currency' => 'MXN',
                'max_works_per_area' => 10,
                'max_file_size_mb' => 200,
                'commission_percent' => 30,
                'allows_auctions' => true,
            ]
        );

        // Galería con subasta
        Plan::updateOrCreate(
            ['slug' => 'gallery-auction'],
            [
                'name' => 'Plan Galería (con subasta)',
                'type' => 'gallery',
                'price_monthly' => 2499,
                'currency' => 'MXN',
                'max_works_per_area' => null,
                'max_file_size_mb' => null,
                'commission_percent' => 25,
                'allows_auctions' => true,
            ]
        );

        // Galería solo exhibición
        Plan::updateOrCreate(
            ['slug' => 'gallery-exhibition'],
            [
                'name' => 'Plan Galería (solo exhibición)',
                'type' => 'gallery',
                'price_monthly' => 4999,
                'currency' => 'MXN',
                'max_works_per_area' => null,
                'max_file_size_mb' => null,
                'commission_percent' => 0, // no hay subasta
                'allows_auctions' => false,
            ]
        );
    }
}