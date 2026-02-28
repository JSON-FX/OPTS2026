<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with(['roles', 'office'])
            ->paginate(50);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::all(),
            'offices' => Office::where('is_active', true)->get(),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load(['roles', 'office']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => Role::all(),
            'offices' => Office::where('is_active', true)->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'office_id' => $request->office_id,
            'is_active' => $request->boolean('is_active', $user->is_active),
        ]);

        $user->syncRoles([$request->role]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
