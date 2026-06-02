<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin panel controller for listing, editing roles, and deleting user accounts.
 *
 * @package App\Http\Controllers\Admin
 */
class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'role' => ['required', 'in:admin,editor,viewer'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Prevent an admin from removing their own admin role
        if ($user->id === auth()->id() && $request->role !== 'admin') {
            return back()->with('error', 'No puedes cambiar tu propio rol de admin.');
        }

        $user->update([
            'name' => $request->name,
            'role' => $request->role,
        ]);

        return redirect()->route('admin.users.index')
                         ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        // Prevent an admin from deleting their own account
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta desde aquí.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
                         ->with('success', 'Usuario eliminado correctamente.');
    }
}