<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Registro local
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'status' => 'active',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    // Login local
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $user = Auth::guard('api')->user();
        if ($user->status !== 'active') {
            return response()->json(['error' => 'Cuenta no activa'], 403);
        }

        return $this->respondWithToken($token, $user);
    }

    // Logout
    public function logout()
    {
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    // Perfil del usuario
    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    // Refrescar token
    public function refresh()
    {
        $token = Auth::guard('api')->refresh();
        return $this->respondWithToken($token, Auth::guard('api')->user());
    }

    // Login/Registro social
    protected function loginOrRegisterSocial($socialUser, $provider)
    {
        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'profile_picture' => $socialUser->getAvatar(),
                'role_id' => 1,
                'status' => 'active',
            ]
        );

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user' => $user,
            'provider' => $provider,
            'token' => $token,
        ]);
    }

    // Socialite: Google
    public function redirectToGoogle() { return Socialite::driver('google')->stateless()->redirect(); }
    public function handleGoogleCallback() {
        $socialUser = Socialite::driver('google')->stateless()->user();
        return $this->loginOrRegisterSocial($socialUser, 'google');
    }

    // Socialite: Github
    public function redirectToGithub() { return Socialite::driver('github')->stateless()->redirect(); }
    public function handleGithubCallback() {
        $socialUser = Socialite::driver('github')->stateless()->user();
        return $this->loginOrRegisterSocial($socialUser, 'github');
    }

    // Socialite: Facebook
    public function redirectToFacebook() { return Socialite::driver('facebook')->stateless()->redirect(); }
    public function handleFacebookCallback() {
        $socialUser = Socialite::driver('facebook')->stateless()->user();
        return $this->loginOrRegisterSocial($socialUser, 'facebook');
    }

    // Helper para respuesta con token
    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }
}
