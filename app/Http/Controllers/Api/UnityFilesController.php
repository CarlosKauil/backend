<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UnityFilesController extends Controller
{
    private function getB2Auth()
    {
        return Cache::remember('b2_auth', 82800, function () {
            $keyId = config('services.b2.key_id');
            $appKey = config('services.b2.app_key');
            
            Log::info('Intentando autenticar con B2', ['keyId' => $keyId]);
            
            $response = Http::withOptions([
                'verify' => false,
            ])->withHeaders([
                'Authorization' => 'Basic ' . base64_encode("$keyId:$appKey")
            ])->get('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
            
            if (!$response->successful()) {
                Log::error('Error autenticación B2', ['response' => $response->json()]);
                throw new \Exception('Error al autenticar con B2: ' . $response->body());
            }
            
            $authData = $response->json();
            Log::info('Autenticación B2 exitosa', ['downloadUrl' => $authData['downloadUrl']]);
            
            return $authData;
        });
    }
    
    public function getUnityFiles()
    {
        try {
            // Limpiar cache si hay problemas
            if (request()->has('clear_cache')) {
                Cache::forget('b2_auth');
            }
            
            $auth = $this->getB2Auth();
            
            $bucketId = config('services.b2.bucket_id');
            Log::info('Solicitando autorización de descarga', ['bucketId' => $bucketId]);
            
            // Obtener token de descarga
            $downloadAuth = Http::withOptions([
                'verify' => false,
            ])->withHeaders([
                'Authorization' => $auth['authorizationToken']
            ])->post($auth['apiUrl'] . '/b2api/v2/b2_get_download_authorization', [
                'bucketId' => $bucketId,
                'fileNamePrefix' => 'assets/',
                'validDurationInSeconds' => 3600
            ]);
            
            if (!$downloadAuth->successful()) {
                Log::error('Error obtener token descarga', ['response' => $downloadAuth->json()]);
                throw new \Exception('Error al obtener autorización de descarga: ' . $downloadAuth->body());
            }
            
            $downloadAuthData = $downloadAuth->json();
            $baseUrl = $auth['downloadUrl'] . '/file/' . config('services.b2.bucket_name');
            $token = $downloadAuthData['authorizationToken'];
            
            $urls = [
                'loaderUrl' => "$baseUrl/assets/game.loader.js?Authorization=$token",
                'dataUrl' => "$baseUrl/assets/game.data?Authorization=$token",
                'frameworkUrl' => "$baseUrl/assets/game.framework.js?Authorization=$token",
                'codeUrl' => "$baseUrl/assets/game.wasm?Authorization=$token",
            ];
            
            Log::info('URLs generadas exitosamente');
            
            return response()->json($urls);
            
        } catch (\Exception $e) {
            Log::error('Error en getUnityFiles', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}