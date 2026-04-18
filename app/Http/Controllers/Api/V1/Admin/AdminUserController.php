<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    protected function ensureAdmin($user)
    {
        if (!in_array($user->role, ['admin'])) {
            abort(403, 'Only admins can manage admin users.');
        }
    }

    /**
     * List all admin/staff users (exclude customers and suppliers).
     */
    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());

        $users = User::whereIn('role', ['admin', 'staff'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        return response()->json($users);
    }

    /**
     * Create a new admin or staff user.
     */
    public function store(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role'     => 'required|in:admin,staff',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return response()->json(['message' => 'Admin user created.', 'user' => $user], 201);
    }

    /**
     * Update an existing admin/staff user.
     */
    public function update(Request $request, User $user)
    {
        $this->ensureAdmin($request->user());

        // Prevent editing super admin or self? (Optional)
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot edit your own account here.'], 403);
        }

        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'role'  => 'sometimes|in:admin,staff',
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json(['message' => 'Admin user updated.', 'user' => $user]);
    }

    /**
     * Delete an admin/staff user (soft delete or hard delete – here we hard delete).
     */
    public function destroy(Request $request, User $user)
    {
        $this->ensureAdmin($request->user());

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Admin user deleted.']);
    }
}