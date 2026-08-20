<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles')->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'admin');
        })->withCount(['auctions', 'bids']);

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->q.'%')
                    ->orWhere('email', 'like', '%'.$request->q.'%')
                    ->orWhere('phone', 'like', '%'.$request->q.'%');
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->role));
        }

        if ($request->filled('verified')) {
            $request->verified === 'yes'
                ? $query->where('is_verified', true)
                : $query->where('is_verified', false);
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'total' => User::count(),
            'verified' => User::where('is_verified', true)->count(),
            'pending' => User::where('is_verified', false)->count(),
            'sellers' => User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))->count(),
            'buyers' => User::whereHas('roles', fn ($q) => $q->where('name', 'buyer'))->count(),
            'admins' => User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count(),
        ];

        $roles = Role::all();

        return view('admin.users.index', compact('users', 'stats', 'roles'));
    }

    public function show(User $user)
    {
        $user->load('roles')
            ->loadCount(['auctions', 'bids', 'watchlist', 'purchases', 'sales']);

       $user->load([
    'auctions' => fn ($q) => $q->latest()->take(5)->with('cover'),
    'bids' => fn ($q) => $q->with('auction')->latest()->take(5),
]);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::all();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'exists:roles,name'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Rules\Password::defaults()];
        }

        $request->validate($rules, [
            'name.required' => 'Ad Soyad zorunludur.',
            'email.unique' => 'Bu e-posta zaten kullanılıyor.',
            'role.required' => 'Rol seçimi zorunludur.',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->is_verified = $request->boolean('is_verified');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        if ($request->role) {
            $user->syncRoles([$request->role]);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Kullanıcı başarıyla güncellendi.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['message' => 'Kendi hesabınızı silemezsiniz.'], 422);
            }
            return back()->with('error', 'Kendi hesabınızı silemezsiniz.');
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $name = $user->name;
        $user->delete();

        $msg = $name . ' silindi.';

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => $msg]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', $msg);
    }

    public function verify(User $user)
    {
        $user->update(['is_verified' => true]);

        return back()->with('success', $user->name.' doğrulandı.');
    }

    public function unverify(User $user)
    {
        $user->update(['is_verified' => false]);

        return back()->with('success', $user->name.' doğrulaması kaldırıldı.');
    }
}
