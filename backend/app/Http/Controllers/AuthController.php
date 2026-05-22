<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // login endpoint that only allows users with role ADMIN to log in, and returns a token for authentication.
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();

        if (! $user || ($user->role ?? null) !== 'ADMIN' || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials, please try again!'], 401);
        }

        // Revoke all previous tokens for the user.
        $user->tokens()->delete();

        $token = $user->createToken('admin')->plainTextToken;

        return [
            'accessToken' => $token,
            
            'user' => $this->toApiLoginUser($user),
        ];
    }
    // me endpoint that returns the currently authenticated user's information.
    public function me(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        return $this->toApiUser($user);
    }
    // update profile endpoint that allows the authenticated user to update their name, bio, and avatar URL.
    public function updateProfile(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'avatarUrl' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }

        if (array_key_exists('bio', $data)) {
            $updates['bio'] = $data['bio'];
        }

        if (array_key_exists('avatarUrl', $data)) {
            $updates['avatar_url'] = $data['avatarUrl'];
        }

        $user->update($updates);

        return $this->toApiUser($user->fresh());
    }
    // toapiuser and toapiloginuser functions that convert a User model instance to an array suitable for API responses, with the appropriate fields for each case.
    private function toApiUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'name' => $user->name,
            'bio' => $user->bio,
            'avatarUrl' => $user->avatar_url,
            'createdAt' => $user->created_at?->toISOString(),
        ];
    }

    private function toApiLoginUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'name' => $user->name,
            'bio' => $user->bio,
            'avatarUrl' => $user->avatar_url,
        ];
    }
}
