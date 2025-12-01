<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Obra;
use App\Models\EstatusObra;
use App\Models\MensajeRechazo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ObraController extends Controller
{
    // Listar obras: admin ve todas, artista solo las suyas con filtros
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Obra::with([
                'user', 
                'area', 
                'artist.user', 
                'estatus', 
                'mensajesRechazo']);

            // Filtro opcional por estatus
            if ($request->has('estatus_id')) {
                $query->where('estatus_id', $request->estatus_id);
            }

            if ($user->role === 'Admin') {
                $obras = $query->orderBy('created_at', 'asc')->get();
            } elseif ($user->role === 'Artista') {
                $obras = $query->where('user_id', $user->id)->orderBy('created_at', 'asc')->get();
            } else {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $obras->map(function ($obra) {
                $obra->area_nombre = $obra->area ? $obra->area->nombre : null;
                return $obra;
            });

            return response()->json($obras);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    public function aceptadas(Request $request)
    {
        $query = Obra::with(['user', 'area', 'estatus'])
            ->where('estatus_id', 2)
            ->where('es_subastable', 1); // <- AGREGADO AQUÍ

        if ($request->has('area_id') && !empty($request->area_id)) {
            $query->where('area_id', $request->area_id);
        }
        $obras = $query->get();

        return response()->json($obras);
    }


    public function obrasAprobadas(Request $request)
    {
        try {
            $query = Obra::with(['user', 'area', 'estatus'])
                ->where('estatus_id', 2);

            // Si llega ?area_id, filtramos
            if ($request->has('area_id') && !empty($request->area_id)) {
                $query->where('area_id', $request->area_id);
            }

            $obras = $query->get();

            $contador = [
                'modelado'   => Obra::where('estatus_id', 2)->where('area_id', 1)->count(),
                'musica'     => Obra::where('estatus_id', 2)->where('area_id', 2)->count(),
                'literatura' => Obra::where('estatus_id', 2)->where('area_id', 3)->count(),
                'pintura'    => Obra::where('estatus_id', 2)->where('area_id', 4)->count(),
            ];

            return response()->json([
                'obras'     => $obras,
                'contador'  => $contador,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Subir una nueva obra (solo artista)
   public function store(Request $request)
    {
        try {
            $areaId = (int) $request->input('area_id');
            Log::info("Iniciando registro de obra para el área: {$areaId}", [
                'user_id' => optional($request->user())->id,
                'input' => $request->all()
            ]);

            // VALIDACIÓN SEGÚN ÁREA Y ARCHIVOS
            $rules = [
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'anio_creacion' => 'nullable|integer|min:1900|max:' . date('Y'),
                'area_id' => 'required|integer|exists:areas,id',
                'es_subastable' => 'required|boolean',
                'precio' => 'nullable|numeric|min:0',
                'precio_subasta' => 'nullable|numeric|min:0',
            ];

            if (in_array($areaId, [1, 2, 4])) {
                $rules['genero_tecnica'] = 'required|string|max:17';
            }

            if ($areaId === 3) {
                $rules['libro'] = 'required|file|mimes:pdf|max:20480';
            } else {
                $rules['archivo'] = 'required|file|max:10240';
            }

            $request->validate($rules);
            Log::info("Validación exitosa para usuario: {$request->user()->id}");

            $user = $request->user();
            if (!$user) {
                Log::warning("Usuario no autenticado intentó subir obra", ['request_ip' => $request->ip()]);
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // LÍMITES DE OBRAS POR ÁREA
            $limites = [
                3 => 6,
                4 => 29,
                2 => 10,
                1 => 22,
            ];

            $limite = $limites[$areaId] ?? null;
            if ($limite !== null) {
                $obrasExistentes = Obra::where('area_id', $areaId)->count();
                if ($obrasExistentes >= $limite) {
                    Log::info("Límite de obras alcanzado para área {$areaId}", ['usuario_id' => $user->id]);
                    return response()->json([
                        'message' => 'Se alcanzó el límite máximo de obras en esta área.'
                    ], 400);
                }
            }

            $carpeta = match ($areaId) {
                1 => 'modelado',
                2 => 'musica',
                3 => 'literatura',
                4 => 'pintura',
                default => 'otros',
            };

            // GUARDAR ARCHIVOS SEGÚN ÁREA
            $libroPath = null;
            $archivoPath = null;

            if ($areaId === 3) {
                $libro = $request->file('libro');
                $nombreLibro = uniqid('libro_') . '.' . $libro->getClientOriginalExtension();
                $libroPath = $libro->storeAs("obras/literatura", $nombreLibro, 'b2');
                Log::info("Archivo de libro subido", ['path' => $libroPath, 'usuario_id' => $user->id]);
            } else {
                $archivo = $request->file('archivo');
                $nombreArchivo = uniqid('obra_') . '.' . $archivo->getClientOriginalExtension();
                $archivoPath = $archivo->storeAs("obras/$carpeta", $nombreArchivo, 'b2');
                Log::info("Archivo de obra subido", ['path' => $archivoPath, 'usuario_id' => $user->id]);
            }

            // CREAR OBRA
            $obra = Obra::create([
                'user_id' => $user->id,
                'artist_id' => $user->artist->id,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'anio_creacion' => $request->anio_creacion,
                'genero_tecnica' => $request->genero_tecnica,
                'archivo' => $archivoPath,
                'libro' => $libroPath,
                'area_id' => $areaId,
                'estatus_id' => 1,
                'es_subastable' => $request->es_subastable,
                'precio' => $request->precio,
                'precio_subasta' => $request->precio_subasta,
            ]);

            Log::info("Obra creada correctamente", ['obra_id' => $obra->id, 'usuario_id' => $user->id]);

            $obrasAceptadas = Obra::with(['user', 'area', 'estatus'])
                ->where('estatus_id', 2)
                ->get();

            $contador = [
                'modelado'   => Obra::where('estatus_id', 2)->where('area_id', 1)->count(),
                'musica'     => Obra::where('estatus_id', 2)->where('area_id', 2)->count(),
                'literatura' => Obra::where('estatus_id', 2)->where('area_id', 3)->count(),
                'pintura'    => Obra::where('estatus_id', 2)->where('area_id', 4)->count(),
            ];

            return response()->json([
                'message' => 'Obra registrada correctamente y enviada para revisión.',
                'obra' => $obra,
                'obras_aceptadas' => $obrasAceptadas,
                'contador' => $contador,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning("Error de validación al registrar obra", ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error("Excepción al registrar obra", ['error' => $e->getMessage(), 'line' => $e->getLine()]);
            return response()->json([
                'message' => 'Error al registrar la obra',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }


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

        if ($user->role !== 'Admin' && $obra->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado para modificar esta obra.'], 403);
        }

        $rules = [];
        if ($user->role === 'Admin') {
            // Admin solo puede cambiar estatus y mensaje
            $rules['estatus_id'] = 'required|exists:estatus_obras,id|in:2,3';
            $rules['mensaje_rechazo'] = 'nullable|string|max:500';

            $validated = $request->validate($rules);
            $estatusId = $validated['estatus_id'];

            $obra->estatus_id = $estatusId;
            $obra->save();

            if ($estatusId == 3 && $request->filled('mensaje_rechazo')) {
                MensajeRechazo::create([
                    'obra_id' => $obra->id,
                    'emisor_id' => $user->id,
                    'receptor_id' => $obra->user_id,
                    'mensaje' => $validated['mensaje_rechazo'],
                ]);
            }
            
        } else {
            // Artista puede editar antes de ser aceptada o si está rechazada
            if ($obra->estatus_id == 2) {
                return response()->json(['message' => 'No puedes editar una obra que ha sido aceptada.'], 403);
            }

            // Reglas para edición de metadatos (aquí podrías expandir para editar archivos nuevos, etc.)
            $rules = [
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string|max:421',
                'es_subastable' => 'sometimes|boolean',
                // Agrega otros campos si lo deseas...
            ];

            $validated = $request->validate($rules);

            // Si el área = literatura puede aceptar un nuevo PDF (NO obligatorio aquí)
            if ($obra->area_id == 3 && $request->hasFile('libro')) {
                $libro = $request->file('libro');
                $nombreLibro = uniqid('libro_') . '.' . $libro->getClientOriginalExtension();
                $libroPath = $libro->storeAs("obras/literatura", $nombreLibro, 'public');
                $obra->libro = $libroPath;
            }
            // Otros archivos para otras áreas si haces la lógica...
            $obra->update($validated);
        }

        // Cargar relaciones necesarias para el frontend
        return response()->json(
            $obra->fresh([
                'estatus', 
                'mensajesRechazo', 
                'user', 
                'area',
                'artist.user'
            ]), 
            200
        );
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $obra = Obra::findOrFail($id);

        if ($user->role === 'Admin' || ($user->role === 'Artista' && $obra->artist_id == $user->artist->id)) {
            if ($obra->archivo && Storage::disk('public')->exists($obra->archivo)) {
                Storage::disk('public')->delete($obra->archivo);
            }
            if ($obra->libro && Storage::disk('public')->exists($obra->libro)) {
                Storage::disk('public')->delete($obra->libro);
            }
            $obra->delete();
            return response()->json(null, 204);
        }

        return response()->json(['message' => 'No autorizado'], 403);
    }

    public function aceptadasPublic($area_id)
    {
        $obras = \App\Models\Obra::with(['user.profileLink', 'area', 'estatus'])
            ->where('estatus_id', 2)
            ->where('area_id', $area_id)
            ->get()
            ->map(function ($obra) {
                $obra->user->profile_url = $obra->user->profileLink
                    ? url('/artist/' . $obra->user->profileLink->link)
                    : null;
                return $obra;
            });

        return response()->json($obras);
    }

    public function getRejectionMessages(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'Artista') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }
        
        $messages = MensajeRechazo::where('receptor_id', $user->id)
            ->with(['obra' => function ($query) {
                $query->select('id', 'nombre', 'archivo', 'libro');
            }])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        return response()->json($messages);
    }

    // Obtener las 15 obras nuevas que están pendientes de revisión
    public function getNewPendingObras(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'Admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }
        $obras = \App\Models\Obra::with(['user', 'artist', 'area'])
            ->where('estatus_id', 1) // Solo las pendientes
            ->latest('created_at')
            ->limit(15)
            ->get();

        return response()->json($obras);
    }
}
