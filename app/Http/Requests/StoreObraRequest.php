<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Obra;
use App\Models\EstatusObra;
use App\Models\MensajeRechazo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreObraRequest; // 👈 Importación clave

class ObraController extends Controller
{
    // Listar obras: admin ve todas, artista solo las suyas con filtros
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // 👈 CAMBIO AQUÍ: Agregamos 'user' a la carga.
            $query = Obra::with(['user', 'artist.user', 'area', 'estatus', 'mensajesRechazo.admin']); 

            // ... (el resto del código de index es igual)

            if ($user->role === 'Admin') {
                // Admin ve todas, ordenadas ascendente por fecha
                $obras = $query->orderBy('created_at', 'asc')->get();
            } elseif ($user->role === 'Artista') {
                // Artista ve solo sus obras
                $obras = $query->where('user_id', $user->id)->orderBy('created_at', 'asc')->get();
            } else {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            // Retornamos con el nombre del área incluido (aunque ya se carga en 'area')
            $obras->map(function ($obra) {
                $obra->area_nombre = $obra->area ? $obra->area->nombre : null;
                return $obra;
            });

            return response()->json($obras);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    // Subir una nueva obra (usa el Form Request para validación)
    public function store(StoreObraRequest $request) // 👈 Usando StoreObraRequest
    {
        // La autorización y validación se manejan en StoreObraRequest
        $user = $request->user();
        $validated = $request->validated();

        // 1. Obtener la carpeta de destino
        $folder = $this->getFolderByArea($validated['area_id']); 

        // 2. Guardar archivo en la carpeta correspondiente
        $path = $request->file('archivo')->store($folder, 'public');

        // 3. Crear registro de obra
        $obraData = [
            'user_id' => $user->id,
            'artist_id' => $user->artist->id, // Perfil de artista garantizado por authorize()
            'area_id' => $validated['area_id'],
            'nombre' => $validated['nombre'],
            'archivo' => $path,
            'anio_creacion' => $validated['anio_creacion'],
            'estatus_id' => 1, // Pendiente

            // Se agregan los campos opcionales/condicionales usando nullsafe. 
            // Serán null si no estaban en el request (ej. Música)
            'genero_tecnica' => $validated['genero_tecnica'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
        ];

        $obra = Obra::create($obraData);

        return response()->json($obra, 200);
    }


    // Ver detalles de una obra (Sin cambios)
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $obra = Obra::with(['artist.user', 'area', 'estatus', 'mensajesRechazo.admin'])->findOrFail($id);

        if ($user->role === 'Admin' || ($user->role === 'Artista' && $obra->artist_id == $user->artist->id)) {
            return response()->json($obra);
        }

        return response()->json(['message' => 'No autorizado'], 403);
    }

        
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $obra = Obra::findOrFail($id);

        // 1. VERIFICACIÓN DE PERMISOS
        // Artista solo puede actualizar si es el dueño y la obra NO está Aceptada.
        if ($user->role !== 'Admin' && $obra->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado para modificar esta obra.'], 403);
        }

        // 2. LÓGICA DE ACTUALIZACIÓN
        $rules = [];
        if ($user->role === 'Admin') {
            // El admin SOLO debe cambiar el estatus y potencialmente enviar un mensaje
            $rules['estatus_id'] = 'required|exists:estatus_obras,id|in:2,3'; // Solo Aceptada(2) o Rechazada(3)
            $rules['mensaje_rechazo'] = 'nullable|string|max:500';

            $validated = $request->validate($rules);
            $estatusId = $validated['estatus_id'];

            // Solo el Admin puede cambiar el estatus
            $obra->estatus_id = $estatusId;
            $obra->save();

            // Lógica de Mensaje de Rechazo
            if ($estatusId == 3 && $request->filled('mensaje_rechazo')) {
                $receptorId = $obra->artist?->user?->id; 
                if ($receptorId) { 
                    MensajeRechazo::create([
                        'obra_id' => $obra->id,
                        'emisor_id' => $user->id, 
                        'receptor_id' => $receptorId,
                        'mensaje' => $validated['mensaje_rechazo'],
                    ]);
                }
            }
            
        } elseif ($user->role === 'Artista') {
            // Artista puede editar metadatos antes de ser ACEPTADA
            if ($obra->estatus_id == 2) { // 2 = Aceptada
                return response()->json(['message' => 'No puedes editar una obra que ha sido aceptada.'], 403);
            }
            
            // Reglas para que el artista edite (si aplica, por ahora solo nombre y descripción)
            $rules = [
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:421',
                // NOTA: Si quisiera actualizar el archivo, la lógica sería más compleja aquí.
            ];

            $validated = $request->validate($rules);
            $obra->update($validated);
        }
        
        // Retornamos la obra actualizada con las relaciones
        return response()->json($obra->load('estatus', 'mensajesRechazo.emisor'), 200);
    }


    // Eliminar una obra
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $obra = Obra::findOrFail($id);

        if ($user->role === 'Admin' || ($user->role === 'Artista' && $obra->artist_id == $user->artist->id)) {
            // Eliminación de archivo físico
            if ($obra->archivo && Storage::disk('public')->exists($obra->archivo)) {
                Storage::disk('public')->delete($obra->archivo);
            }
            $obra->delete();
            return response()->json(null, 204);
        }

        return response()->json(['message' => 'No autorizado'], 403);
    }

    // Listar obras aceptadas públicamente por área
    public function aceptadasPublic($area_id)
    {
        // Aseguramos que solo devuelve las aceptadas de esa área
        // 👈 CAMBIO: También cargamos artist.user para datos públicos
        $obras = Obra::with(['artist.user', 'area', 'estatus']) 
            ->where('estatus_id', 2) // Solo aceptadas
            ->where('area_id', $area_id)
            ->get();

        return response()->json($obras);
    }

    /**
     * Método auxiliar para determinar la ruta de almacenamiento por Área.
     */
    private function getFolderByArea(int $areaId): string 
    {
        return match ($areaId) {
            1 => 'obras/modelado',
            2 => 'obras/musica',
            3 => 'obras/literatura',
            4 => 'obras/pintura',
            default => 'obras',
        };
    }
}