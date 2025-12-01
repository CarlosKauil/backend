<?php

namespace App\Policies;

use App\Models\Obra;
use App\Models\User;

class ObraPolicy
{
    /**
     * 1) Determina si el usuario puede crear una nueva obra.
     *    Aplica límite de obras por área según el plan.
     */
    public function create(User $user, int $areaId): bool
    {
        // 1.0 Admin no tiene límite (opcional; quita si no lo quieres)
        if ($user->role === 'admin') {
            return true;
        }

        $plan = $user->currentPlan()->first();

        // 1.1 Sin plan -> equivalente al Básico (3 obras por área)
        if (! $plan) {
            $max = 3;
        } else {
            // null = sin límite
            $max = $plan->max_works_per_area ?? null;
        }

        if ($max === null) {
            return true;
        }

        // 1.2 Contar obras del usuario en esa área
        $count = Obra::where('user_id', $user->id)
            ->where('area_id', $areaId)
            ->count();

        return $count < $max;
    }

    /**
     * 2) Verifica si el usuario puede subir un archivo con cierto tamaño (MB).
     */
    public function uploadFile(User $user, int $fileSizeMb): bool
    {
        // Admin sin límite de tamaño (opcional)
        if ($user->role === 'admin') {
            return true;
        }

        $plan = $user->currentPlan()->first();

        // 2.1 Plan básico por defecto 50MB si no hay plan
        $limit = $plan->max_file_size_mb ?? 50;

        return $fileSizeMb <= $limit;
    }

    /**
     * 3) Editar / borrar la obra (dueño o admin).
     */
    public function update(User $user, Obra $obra): bool
    {
        return $user->id === $obra->user_id || $user->role === 'admin';
    }

    public function delete(User $user, Obra $obra): bool
    {
        return $user->id === $obra->user_id || $user->role === 'admin';
    }
}
