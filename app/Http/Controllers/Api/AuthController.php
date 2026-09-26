<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->lower()->toString())->first();
        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password) || ! $user->is_active) {
            return response()->json(['message' => 'The supplied credentials are invalid.'], 422);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('home-erp-spa', ['*']);

        return response()->json(['token' => $token->plainTextToken, 'user' => $this->userPayload($user)]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data): User {
            $household = isset($data['household_id'])
                ? Household::findOrFail($data['household_id'])
                : Household::create(['name' => $data['name'].' Home', 'slug' => Str::slug($data['name'].'-home-'.Str::random(6)), 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']);
            $user = User::create(['household_id' => $household->id, 'name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'password' => $data['password']]);
            $user->assignRole('family-member');
            return $user;
        });

        return response()->json(['message' => 'Account created. You can now sign in.', 'user' => $this->userPayload($user)], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data);

        return response()->json(['message' => 'If that email exists, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            fn (User $user, string $password) => $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save(),
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully.'])
            : response()->json(['message' => __($status)], 422);
    }

    public function updateProfile(ProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'local');
            $data['avatar_path'] = $path;
        }
        $user->fill(collect($data)->except('avatar')->all())->save();

        return response()->json(['message' => 'Profile updated.', 'user' => $this->userPayload($user->fresh())]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(Hash::check($request->string('current_password')->toString(), $user->password), 422, 'Current password is incorrect.');
        $user->forceFill(['password' => $request->string('password')->toString()])->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;
        $sessions = $request->user()->tokens()->latest()->get(['id', 'name', 'last_used_at', 'created_at'])->map(fn ($token) => ['id' => $token->id, 'name' => $token->name, 'last_used_at' => $token->last_used_at, 'created_at' => $token->created_at, 'current' => $token->id === $currentId]);

        return response()->json(['data' => $sessions]);
    }

    public function revokeSession(Request $request, int $session): JsonResponse
    {
        $token = $request->user()->tokens()->whereKey($session)->firstOrFail();
        $token->delete();

        return response()->json(['message' => 'Session revoked.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'avatar_path' => $user->avatar_path,
            'household' => $user->household, 'roles' => $user->getRoleNames(), 'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
