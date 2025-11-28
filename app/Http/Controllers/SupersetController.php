<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupersetController extends Controller
{
    public function getGuestToken(Request $request): JsonResponse
    {
        $supersetBase = rtrim(config('services.superset.base_url'), '/');
        $username     = config('services.superset.username');
        $password     = config('services.superset.password');
        $dashboardId  = config('services.superset.dashboard_id');

        if (! $supersetBase || ! $username || ! $password || ! $dashboardId) {
            return response()->json([
                'step'   => 'config',
                'error'  => 'Faltan variables de entorno de Superset',
                'detail' => [
                    'SUPERSET_BASE_URL'   => $supersetBase,
                    'SUPERSET_USERNAME'   => $username,
                    'SUPERSET_PASSWORD'   => $password ? '*' : null,
                    'SUPERSET_DASHBOARD_ID' => $dashboardId,
                ],
            ], 500);
        }

        // 1. LOGIN EN SUPERSET
        try {
            $loginResponse = Http::asJson()->post("{$supersetBase}/api/v1/security/login", [
                'username' => $username,
                'password' => $password,
                'provider' => 'db',
                'refresh'  => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Superset login connection error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'step'   => 'login_connection',
                'error'  => 'No se pudo conectar a Superset',
                'detail' => $e->getMessage(),
            ], 500);
        }

        if (! $loginResponse->ok()) {
            Log::error('Superset login failed', [
                'status' => $loginResponse->status(),
                'body'   => $loginResponse->body(),
            ]);

            return response()->json([
                'step'   => 'login_http',
                'error'  => 'Login en Superset falló',
                'status' => $loginResponse->status(),
                'body'   => $loginResponse->json(),
            ], 500);
        }

        $accessToken = $loginResponse->json('access_token');
        if (! $accessToken) {
            Log::error('Superset login without access_token', [
                'response' => $loginResponse->json(),
            ]);

            return response()->json([
                'step'   => 'login_token',
                'error'  => 'Superset no devolvió access_token',
                'body'   => $loginResponse->json(),
            ], 500);
        }

        // 2. PETICIÓN DEL GUEST TOKEN
        $payload = [
            'user' => [
                'username'   => 'mi_guest_user',
                'first_name' => 'MI',
                'last_name'  => 'Guest',
            ],
            'resources' => [
                [
                    'type' => 'dashboard',
                    'id'   => $dashboardId,
                ],
            ],
            'rls' => [],
        ];

        try {
            $guestResponse = Http::withToken($accessToken)
                ->asJson()
                ->post("{$supersetBase}/api/v1/security/guest_token/", $payload);
        } catch (\Throwable $e) {
            Log::error('Superset guest_token connection error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'step'   => 'guest_connection',
                'error'  => 'No se pudo conectar a /security/guest_token',
                'detail' => $e->getMessage(),
            ], 500);
        }

        if (! $guestResponse->ok()) {
            Log::error('Superset guest_token failed', [
                'status' => $guestResponse->status(),
                'body'   => $guestResponse->body(),
            ]);

            return response()->json([
                'step'   => 'guest_http',
                'error'  => 'Superset devolvió error al pedir guest_token',
                'status' => $guestResponse->status(),
                'body'   => $guestResponse->json(),
            ], 500);
        }

        $guestToken = $guestResponse->json('token');

        if (! $guestToken) {
            Log::error('Superset guest_token without token', [
                'response' => $guestResponse->json(),
            ]);

            return response()->json([
                'step'   => 'guest_token',
                'error'  => 'Superset no devolvió el campo token',
                'body'   => $guestResponse->json(),
            ], 500);
        }

        // TODO OK
        return response()->json([
            'token' => $guestToken,
        ]);
    }
}