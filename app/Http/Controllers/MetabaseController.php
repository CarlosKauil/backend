<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT; // Necesitarás 'firebase/php-jwt' o similar
use Illuminate\Support\Facades\Log;

class MetabaseController extends Controller
{
    public function getEmbedUrl($id)
    {
        $metabaseUrl = config('services.metabase.url');
        $metabaseSecret = config('services.metabase.secret');

        // 1. Definir el payload del JWT
        $payload = [
            'resource' => [
                'dashboard' => (int) $id, // ID del Dashboard de Metabase
            ],
            'params' => (object) [
                // Aquí puedes pasar filtros. Ej: 'user_id' => auth()->id()
                // Si no hay filtros, debe ser un objeto vacío {}
            ],
            // Tiempo de expiración del token (ej. 10 minutos desde ahora)
            'exp' => time() + (60 * 10), 
        ];

        // 2. Firmar el JWT
        $token = JWT::encode($payload, $metabaseSecret, 'HS256');

        // 3. Crear el URL seguro
        $iframeUrl = "{$metabaseUrl}/embed/dashboard/{$token}#bordered=true&titled=true";

        return response()->json([
            'embedUrl' => $iframeUrl
        ]);
    }
}