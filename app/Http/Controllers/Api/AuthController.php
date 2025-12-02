<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\Artist;

class AuthController extends Controller
{

    // Método para listar todos los usuarios (solo para pruebas)
    public function index()
    {
        $user = User::all();
        return response()->json($user);
    }
    // Método para registrar un usuario general
    public function register(Request $request)
    {

     
        // Validación de los datos de entrada del formulario de registro
        $request->validate([
            'name' => 'required|string|max:255', // Nombre obligatorio, tipo string, máximo 255 caracteres
            'email' => 'required|string|email|max:255|unique:users', // Email obligatorio, formato válido, único
            'password' => 'required|string|min:6', // Contraseña obligatoria, mínimo 6 caracteres
            'role' => 'in:Admin,Artista,User' // Rol opcional, solo puede ser uno de los tres valores
        ]);

        try {
            // Creación del nuevo usuario en la base de datos
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password), // Se encripta la contraseña con bcrypt
                'role' => $request->role ?? 'Admin' // Si no se especifica el rol, se asigna "User" por defecto
          
               
            ]);
              // Creación de un token de autenticación usando Laravel Sanctum
                $token = $user->createToken('token')->plainTextToken;

                    // Retorno de una respuesta JSON con los datos del usuario y el token generado
            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);
        }
        catch (\Exception $e) {
            // Manejo de errores, en caso de que algo falle durante la creación del usuario
            return response()->json(['message' => 'Error al registrar el usuario'], 500);
        }
       

        
    }

    // Método para registrar un usuario con perfil de artista
    public function artistRegister(Request $request)
    {
        // Validación de los datos de entrada específicos para artistas
        $request->validate([
            'name' => 'required|string|max:255', // Nombre obligatorio
            'email' => 'required|string|email|max:255|unique:users', // Email obligatorio, válido y único
            'password' => 'required|string|min:6|confirmed', // Contraseña obligatoria con confirmación
            'alias' => 'required|string|max:255', // Alias artístico obligatorio
            'fecha_nacimiento' => 'required|date', // Fecha de nacimiento obligatoria, debe ser una fecha válida
            'area_id' => 'required|exists:areas,id', // ID del área obligatoria, debe existir en la tabla areas
        ]);

        // Creación del usuario con rol "Artista"
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password), // Se encripta la contraseña
            'role' => 'Artista',
        ]);

        // Creación del perfil de artista asociado al usuario creado
        $artist = Artist::create([
            'user_id' => $user->id, // Relación entre el artista y el usuario
            'alias' => $request->alias,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'area_id' => $request->area_id,
        ]);

        // Generación del token de autenticación
        $token = $user->createToken('token')->plainTextToken;

        // Respuesta JSON con los datos del usuario, del artista y el token
        return response()->json([
            'user' => $user,
            'artist' => $artist,
            'token' => $token,
        ]);
    }

    /** */

    // Método para iniciar sesión
    public function login(Request $request)
    {
            // Validación
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Intento de login
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        // Login exitoso
        $user = $request->user();

        return response()->json([
            'user' => $user
        ]);
    }

    // Método para obtener los datos del usuario autenticado
    public function user(Request $request)
    {
        // Retorna los datos del usuario que hizo la solicitud
        return response()->json($request->user());
    }

    // Método que permite acceso solo a usuarios con rol de administrador
    public function adminOnly(Request $request)
    {
        // Se verifica si el usuario autenticado no tiene el rol "Admin"
        if ($request->user()->role !== 'Admin') {
            // Si no es admin, se retorna un error 403 (prohibido)
            return response()->json(['message' => 'No autorizado'], 403);
        }
        // Si es admin, se retorna un mensaje de bienvenida
        return response()->json(['message' => 'Bienvenido, Admin']);
    }
}
