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
        $users = User::all();
        return response()->json($users);
    }

    // Método para registrar un usuario general
    public function register(Request $request)
    {
        // Validación de los datos de entrada del formulario de registro
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'in:Admin,Artista,User'
        ]);

        try {
            // Creación del nuevo usuario en la base de datos
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => $request->role ?? 'User' // Por defecto User, no Admin
            ]);

            // Creación de un token de autenticación usando Laravel Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            // Retorno de una respuesta JSON con los datos del usuario y el token generado
            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Usuario registrado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'message' => 'Error al registrar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Método para registrar un usuario con perfil de artista
    public function artistRegister(Request $request)
    {
        // Validación de los datos de entrada específicos para artistas
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'alias' => 'required|string|max:255',
            'fecha_nacimiento' => 'required|date',
            'area_id' => 'required|exists:areas,id',
        ]);

        try {
            // Creación del usuario con rol "Artista"
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => 'Artista',
            ]);

            // Creación del perfil de artista asociado al usuario creado
            $artist = Artist::create([
                'user_id' => $user->id,
                'alias' => $request->alias,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'area_id' => $request->area_id,
            ]);

            // Generación del token de autenticación
            $token = $user->createToken('auth_token')->plainTextToken;

            // Respuesta JSON con los datos del usuario, del artista y el token
            return response()->json([
                'user' => $user,
                'artist' => $artist,
                'token' => $token,
                'message' => 'Artista registrado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el artista',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ MÉTODO LOGIN CORREGIDO
     * Ahora devuelve el token de Sanctum
     */
    public function login(Request $request)
    {
        // Validación
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Intento de login
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // Login exitoso
        $user = Auth::user();

        // ✅ IMPORTANTE: Revocamos tokens anteriores para evitar acumulación
        $user->tokens()->delete();

        // ✅ Crear nuevo token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token, // ✅ Ahora sí devuelve el token
            'message' => 'Login exitoso'
        ], 200);
    }

    /**
     * ✅ MÉTODO LOGOUT AGREGADO
     * Elimina el token actual del usuario
     */
    public function logout(Request $request)
    {
        try {
            // Eliminar el token actual
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logout exitoso'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cerrar sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Método para obtener los datos del usuario autenticado
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    // Método que permite acceso solo a usuarios con rol de administrador
    public function adminOnly(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        return response()->json([
            'message' => 'Bienvenido, Admin'
        ]);
    }
}