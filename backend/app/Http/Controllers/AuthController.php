<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => (string) $request->string('name'),
            'email' => (string) $request->string('email')->lower(),
            'password' => Hash::make((string) $request->string('password')),
            'role' => $request->input('role', 'member'),
        ]);

        $token = $user->createToken($request->input('device_name', 'vue-spa'))->plainTextToken;

        return $this->success(['user' => $user, 'token' => $token], 'Account created.', 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', (string) $request->string('email')->lower())->first();

        if (! $user || ! Hash::check((string) $request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->input('device_name', 'vue-spa'))->plainTextToken;

        return $this->success(['user' => $user, 'token' => $token], 'Logged in.');
    }

    public function me(Request $request)
    {
        return $this->success($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out.');
    }
}
