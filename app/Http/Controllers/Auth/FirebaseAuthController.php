<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FirebaseAuthController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseAuth $firebase)
    {
        $this->firebase = $firebase;
    }

    public function login(Request $request)
    {
        $idToken = $request->bearerToken();

        try {
            $verifiedIdToken = $this->firebase->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            $firebaseUser = $this->firebase->getUser($uid);
            $email = $firebaseUser->email;

            // Buscar o crear usuario en la base de datos
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $firebaseUser->displayName ?? $email]
            );

            // Autenticar al usuario
            Auth::login($user);

            // Opcional: emitir token de Sanctum o Passport
            $token = $user->createToken('firebase-token')->plainTextToken;

            return response()->json([
                'message' => 'Login exitoso',
                'user' => $user,
                'token' => $token // puedes omitir esto si no usas tokens Laravel
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Token inválido o expirado',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}
