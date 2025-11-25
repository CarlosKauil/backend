<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Artist;
use App\Models\Area;
use App\Models\ProfileLink;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    public function show($link)
    {
        $profileLink = ProfileLink::where('link', $link)->first();

        if (!$profileLink) {
            return response()->json(['message' => 'Perfil no encontrado.'], 404);
        }

        $artist = Artist::where('user_id', $profileLink->user_id)
            ->with(['user', 'area'])
            ->first();

        if (!$artist) {
            return response()->json(['message' => 'Perfil de artista no encontrado.'], 404);
        }

        $obrasAceptadas = $artist->obras()
            ->with(['estatus', 'area'])
            ->where('estatus_id', 2)
            ->orderBy('anio_creacion', 'desc')
            ->get();

        return response()->json([
            'artist' => $artist,
            'obras_aceptadas' => $obrasAceptadas,
            'link' => $link,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'Artista') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $artist = Artist::where('user_id', $user->id)->firstOrFail();

        $rules = [
            'alias' => ['required', 'string', 'max:255', Rule::unique('artists')->ignore($artist->id)],
            'fecha_nacimiento' => 'required|date|before:today',
            'area_id' => 'required|exists:areas,id',
            'name' => 'sometimes|string|max:255',

            // Nuevos campos
            'instagram' => 'nullable|string|max:255',
            'x_twitter' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
        ];

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            $artist->update([
                'alias' => $validated['alias'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'area_id' => $validated['area_id'],
                'instagram' => $validated['instagram'] ?? $artist->instagram,
                'x_twitter' => $validated['x_twitter'] ?? $artist->x_twitter,
                'linkedin' => $validated['linkedin'] ?? $artist->linkedin,
                'country' => $validated['country'] ?? $artist->country,
            ]);

            if (isset($validated['name'])) {
                $user->update(['name' => $validated['name']]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Perfil actualizado con éxito.',
                'artist' => $artist->load('area'),
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en updateProfile: ' . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Obtiene el perfil del artista logueado.
     * GET /api/profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'Artista') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        try {
            $artist = Artist::with('area')->where('user_id', $user->id)->first();

            if (!$artist) {
                return response()->json([
                    'user' => $user,
                    'artist' => null,
                    'message' => 'El perfil de artista aún no ha sido inicializado.'
                ], 404);
            }

            // 🔗 Buscar o crear el link de perfil (en la nueva tabla)
            $profileLink = ProfileLink::where('user_id', $user->id)->first();

            return response()->json([
                'user' => $user,
                'artist' => $artist,
                'link' => $profileLink?->link,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getProfile: ' . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Actualiza el perfil del artista logueado.
     * PUT /api/profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'Artista') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $artist = Artist::where('user_id', $user->id)->firstOrFail();

        $rules = [
            'alias' => [
                'required',
                'string',
                'max:255',
                Rule::unique('artists')->ignore($artist->id),
            ],
            'fecha_nacimiento' => 'required|date|before:today',
            'area_id' => 'required|exists:areas,id',
            'name' => 'sometimes|string|max:255',
            // Nuevos campos
            'instagram' => 'nullable|string|max:255',
            'x_twitter' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            $artist->update([
                'alias' => $validated['alias'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'area_id' => $validated['area_id'],
                'instagram' => $validated['instagram'] ?? $artist->instagram,
                'x_twitter' => $validated['x_twitter'] ?? $artist->x_twitter,
                'linkedin' => $validated['linkedin'] ?? $artist->linkedin,
                'country' => $validated['country'] ?? $artist->country,
            ]);

            if (isset($validated['name'])) {
                $user->update(['name' => $validated['name']]);
            }

            // Genera el link único
            $baseName = Str::slug(explode(' ', trim($user->name))[0]);
            $uniqueSuffix = $artist->id ?? $user->id;
            $generatedLink = "{$baseName}-{$uniqueSuffix}";

            $existing = ProfileLink::where('link', $generatedLink)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($existing) {
                $generatedLink = "{$baseName}-{$uniqueSuffix}-" . Str::random(4);
            }

            $profileLink = ProfileLink::updateOrCreate(
                ['user_id' => $user->id],
                ['link' => $generatedLink]
            );

            if (Schema::hasColumn('users', 'profile_id')) {
                $user->update(['profile_id' => $profileLink->id]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Perfil actualizado con éxito.',
                'artist' => $artist->load('area'),
                'user' => $user,
                'link' => $profileLink->link,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en updateProfile: ' . $e->getMessage());
            return response()->json(['message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Perfil público del artista (por link único)
     * GET /api/artist/{link}
     */
    public function showProfile($link)
    {
        $profileLink = ProfileLink::where('link', $link)->first();

        if (!$profileLink) {
            return response()->json(['message' => 'Perfil no encontrado.'], 404);
        }

        $artist = Artist::where('user_id', $profileLink->user_id)
            ->with(['user', 'area'])
            ->first();

        if (!$artist) {
            return response()->json(['message' => 'Perfil de artista no encontrado.'], 404);
        }

        $obrasAceptadas = $artist->obras()
            ->with(['estatus', 'area'])
            ->where('estatus_id', 2)
            ->orderBy('anio_creacion', 'desc')
            ->get();

        return response()->json([
            'artist' => $artist,
            'obras_aceptadas' => $obrasAceptadas,
            'link' => $link,
        ]);
    }
}
