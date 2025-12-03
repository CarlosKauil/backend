<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PresetController extends Controller
{
    public function guestToken(Request $request)
    {
        // Puedes permitir que te manden otro dashboard_id desde el front,
        // o usar el default del .env
        $dashboardId = $request->input('dashboard_id', env('PRESET_EMBED_DASHBOARD_ID'));

        if (!$dashboardId) {
            return response()->json([
                'error' => 'dashboard_id requerido'
            ], 422);
        }

        try {
            // 1) Autenticarse contra la API de Preset para obtener un access_token
            //    POST https://manage.app.preset.io/api/v1/auth/
            //    body: { "name": API_TOKEN_NAME, "secret": API_TOKEN_SECRET }
            $authResponse = Http::asJson()
                ->acceptJson()
                ->post(env('PRESET_API_URL') . '/api/v1/auth/', [
                    'name'   => env('PRESET_API_TOKEN_NAME'),
                    'secret' => env('PRESET_API_TOKEN_SECRET'),
                ]);

            if ($authResponse->failed()) {
                Log::error('Preset auth failed', ['body' => $authResponse->body()]);
                return response()->json([
                    'error' => 'Error autenticando contra Preset'
                ], 500);
            }

            $authJson = $authResponse->json();
            $accessToken = $authJson['payload']['access_token'] ?? null;

            if (!$accessToken) {
                Log::error('Preset auth response sin access_token', ['response' => $authJson]);
                return response()->json([
                    'error' => 'No se obtuvo access_token de Preset'
                ], 500);
            }

            // 2) Pedir el guest token para el dashboard embebido
            //    POST https://manage.app.preset.io/api/v1/teams/<TEAM_ID>/workspaces/<WORKSPACE_ID>/guest-token/
            $user = $request->user(); // si usas Sanctum / JWT

            $payload = [
                'user' => [
                    'username'   => $user?->email ?? 'guest',
                    'first_name' => $user?->name ?? 'Guest',
                    'last_name'  => '',
                ],
                'resources' => [
                    [
                        'type' => 'dashboard',
                        'id'   => $dashboardId,
                    ],
                ],
                // aquí podrías meter reglas de Row-Level Security si las usas
                'rls' => [],
            ];

            $guestResponse = Http::withToken($accessToken, 'Bearer')
                ->asJson()
                ->acceptJson()
                ->post(
                    env('PRESET_API_URL') .
                        '/api/v1/teams/' . env('PRESET_TEAM_ID') .
                        '/workspaces/' . env('PRESET_WORKSPACE_ID') .
                        '/guest-token/',
                    $payload
                );

            if ($guestResponse->failed()) {
                Log::error('Preset guest-token failed', ['body' => $guestResponse->body()]);
                return response()->json([
                    'error' => 'Error obteniendo guest token de Preset'
                ], 500);
            }

            $guestJson = $guestResponse->json();

            // En los ejemplos de Preset el token viene como payload.token
            $token = $guestJson['payload']['token']
                ?? $guestJson['data']['payload']['token']
                ?? null;

            if (!$token) {
                Log::error('Preset guest-token sin token', ['response' => $guestJson]);
                return response()->json([
                    'error' => 'No se obtuvo guest token desde Preset'
                ], 500);
            }

            return response()->json([
                'token' => $token,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error general en PresetController::guestToken', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error interno generando el guest token',
            ], 500);
        }
    }
}