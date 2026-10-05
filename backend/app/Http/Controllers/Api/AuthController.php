<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('teams')->where('email', $validated['email'])->first();

        if (!$user || !$user->is_active || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        // Other devices stay signed in; tokens expire on their own (see sanctum.expiration).
        $token = $user->createToken('kcg_auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function demoLogin(Request $request)
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $validated = $request->validate([
            'role' => 'required|in:ceo,team_lead,employee',
        ]);

        $user = User::with('teams')->where('role', $validated['role'])->first();

        if (!$user) {
            return response()->json(['message' => 'No demo user found for this role.'], 404);
        }

        $user->tokens()->delete();
        $token = $user->createToken('kcg_demo_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:10|confirmed|different:current_password',
        ]);
        $user = $request->user();
        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is not correct.']]);
        }

        $user->update(['password' => $validated['password']]);
        // Sign out every other device; this one stays signed in.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current instanceof PersonalAccessToken, fn ($tokens) => $tokens->where('id', '!=', $current->id))->delete();

        return response()->json(['message' => 'Password changed. Other devices were signed out.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('teams');
        return response()->json([
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
