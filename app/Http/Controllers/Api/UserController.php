<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    // Listar todos los usuarios
    public function index()
    {
        return response()->json(User::all());
    }

    // Mostrar un usuario específico
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // Actualizar el rol de un usuario
    public function update(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:Admin,Artista,User',
        ]);

        $user = User::findOrFail($id);
        $user->role = $request->role;
        $user->save();

        return response()->json($user);
    }

    // Eliminar un usuario
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(null, 204);
    }
}