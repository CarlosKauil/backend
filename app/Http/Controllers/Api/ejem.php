  <?php



namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Obra;
use App\Models\EstatusObra;
use App\Models\MensajeRechazo;
use Illuminate\Support\Facades\Storage;



class ObraController extends Controller

{

  // Listar obras: admin ve todas, artista solo las suyas con filtros

  public function index(Request $request)
  {

  try {

    $user = $request->user();

    $query = Obra::with(['user', 'area', 'estatus', 'mensajesRechazo.admin']);
    // Filtro opcional por estatus
  if ($request->has('estatus_id')) {
    $query->where('estatus_id', $request->estatus_id);
  }
  if ($user->role === 'Admin') {
    // Admin ve todas, ordenadas ascendente por fecha
    $obras = $query->orderBy('created_at', 'asc')->get();

  } elseif ($user->role === 'Artista') {

    // Artista ve solo sus obras
    $obras = $query->where('user_id', $user->id)->orderBy('created_at', 'asc')->get();

  } else {

    return response()->json(['message' => 'No autorizado'], 403);

  }



// Retornamos con el nombre del área incluido

$obras->map(function ($obra) {

$obra->area_nombre = $obra->area ? $obra->area->nombre : null;

return $obra;

});



return response()->json($obras);

} catch (\Exception $e) {

return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);

}

}



// Subir una nueva obra (solo artista)

public function store(Request $request)

{

$user = $request->user();

if ($user->role !== 'Artista') {

return response()->json(['message' => 'Solo los artistas pueden subir obras'], 403);

}

if (!$user->artist) {

return response()->json(['message' => 'El usuario no tiene perfil de artista'], 400);

}



// Validación dinámica según el área

$rules = [

'area_id' => 'required|exists:areas,id',

'nombre' => 'required|string|max:255',

];



switch ($request->area_id) {

case 1: // Modelado

$rules['archivo'] = 'required|file|mimes:glb,gltf|max:10240'; // 10MB

$rules['genero_tecnica'] = 'required|string|max:255';

$rules['anio_creacion'] = 'required|digits:4|integer|min:1900|max:' . date('Y');

$rules['descripcion'] = 'nullable|string|max:421';

$folder = 'obras/modelado';

break;



case 2: // Música

$rules['archivo'] = 'required|file|mimes:wav|max:51200'; // 50MB

$rules['anio_creacion'] = 'required|digits:4|integer|min:1900|max:' . date('Y');

$folder = 'obras/musica';

break;



case 3: // Literatura

$rules['archivo'] = 'required|file|mimes:jpg,png|max:5120'; // 5MB

$rules['genero_tecnica'] = 'required|string|max:255';

$rules['anio_creacion'] = 'required|digits:4|integer|min:1900|max:' . date('Y');

$rules['descripcion'] = 'nullable|string|max:421';

$folder = 'obras/literatura';

break;



case 4: // Pintura

$rules['archivo'] = 'required|file|mimes:jpg,png|max:5120'; // 5MB

$rules['genero_tecnica'] = 'required|string|max:255';

$rules['anio_creacion'] = 'required|digits:4|integer|min:1900|max:' . date('Y');

$rules['descripcion'] = 'nullable|string|max:421';

$folder = 'obras/pintura';

break;



default:

return response()->json(['message' => 'Área no válida'], 400);

}



$validated = $request->validate($rules);



// Guardar archivo en la carpeta correspondiente

$path = $request->file('archivo')->store($folder, 'public');



// Crear registro de obra

$obraData = [

'user_id' => $user->id,

'artist_id' => $user->artist->id,

'area_id' => $request->area_id,

'nombre' => $request->nombre,

'archivo' => $path,

'anio_creacion' => $request->anio_creacion,

'estatus_id' => 1, // Pendiente

];



// Solo agregar los campos extra si no es música

if ($request->area_id != 2) {

$obraData['genero_tecnica'] = $request->genero_tecnica;

$obraData['descripcion'] = $request->descripcion;

}



$obra = Obra::create($obraData);



return response()->json($obra, 200);

}





// Ver detalles de una obra

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

if ($user->role !== 'Admin' && $obra->user_id !== $user->id) {

return response()->json(['message' => 'No autorizado para modificar esta obra.'], 403);

}



// 2. VALIDACIÓN (Más estricta para Admin)

$rules = [];

if ($user->role === 'Admin') {

// El admin SOLO debe cambiar el estatus y potencialmente enviar un mensaje

$rules['estatus_id'] = 'required|exists:estatus_obras,id|in:2,3'; // Solo Aceptada o Rechazada

$rules['mensaje_rechazo'] = 'nullable|string|max:500';



$validated = $request->validate($rules);

$estatusId = $validated['estatus_id'];



// Solo el Admin puede cambiar el estatus

$obra->estatus_id = $estatusId;

$obra->save();



// Lógica de Mensaje de Rechazo (YA LA TIENES CORRECTA)

if ($estatusId == 3 && $request->filled('mensaje_rechazo')) {

$receptorId = $obra->artist?->user?->id;

if ($receptorId) {

MensajeRechazo::create([

'obra_id' => $obra->id,

'emisor_id' => $user->id, // El admin es el emisor

'receptor_id' => $receptorId,

'mensaje' => $validated['mensaje_rechazo'],

]);

}

}


} else {

// Artista puede editar metadatos antes de ser ACEPTADA o si está RECHAZADA

if ($obra->estatus_id == 2) { // 2 = Aceptada

return response()->json(['message' => 'No puedes editar una obra que ha sido aceptada.'], 403);

}


// Aquí podrías definir las reglas para que el artista edite (e.g., nombre, descripcion)

$rules = [

'nombre' => 'required|string|max:255',

'descripcion' => 'nullable|string|max:421',

// ... otros campos que quieras que el artista edite

];



// NOTA: Si permites al artista actualizar el archivo, necesitarías la lógica del método store.

$validated = $request->validate($rules);

$obra->update($validated);

}


// Retornamos la obra actualizada con las relaciones

return response()->json($obra->load('estatus', 'mensajesRechazo.emisor'), 200);

}





// (Opcional) Eliminar una obra (solo admin o el artista dueño)

public function destroy(Request $request, $id)

{

$user = $request->user();

$obra = Obra::findOrFail($id);



if ($user->role === 'Admin' || ($user->role === 'Artista' && $obra->artist_id == $user->artist->id)) {

if ($obra->archivo && Storage::disk('public')->exists($obra->archivo)) {

Storage::disk('public')->delete($obra->archivo);

}

$obra->delete();

return response()->json(null, 204);

}



return response()->json(['message' => 'No autorizado'], 403);

}



public function aceptadasPublic($area_id)

{

// Aseguramos que solo devuelve las aceptadas de esa área

$obras = Obra::with(['user', 'area', 'estatus'])

->where('estatus_id', 2) // Solo aceptadas

->where('area_id', $area_id)

->get();



return response()->json($obras);

}



// NUEVO MÉTODO: Obtener Mensajes de Rechazo para el Artista logueado

public function getRejectionMessages(Request $request)

{

$user = $request->user();



// 1. Verificación de seguridad y rol

// (Asegúrate que el usuario esté autenticado y sea Artista)

if (!$user || $user->role !== 'Artista') {

// En un entorno de API, si el middleware falla, esto no se ejecuta,

// pero es una buena doble verificación.

return response()->json(['message' => 'Acceso denegado.'], 403);

}


// 2. Obtener mensajes

// Usamos 'receptor_id' porque el Admin envía el mensaje al artista (user_id).

$messages = MensajeRechazo::where('receptor_id', $user->user_id)

// 💡 Importante: Cargamos la obra asociada para mostrar su nombre en el Navbar

->with(['obra' => function ($query) {

$query->select('id', 'nombre'); // Solo nombre y ID para ser ligeros

}])

->orderBy('created_at', 'desc')

->limit(15)

->get();



return response()->json($messages);

}









} 