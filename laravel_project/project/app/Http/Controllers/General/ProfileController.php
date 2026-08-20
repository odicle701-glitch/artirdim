<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    public function edit()
    {
        $user =  auth()->user();

        $auctions = $user->auctions()->latest()->get();

        $isOwner = auth()->check() && auth()->id() === $user->id;
        $isFollowing = auth()->check() ? auth()->user()->isFollowing($user->id) : false;
        $followerCount = $user->followers()->count();
        $followingCount = $user->followings()->count();
        $activities = $this->buildActivities($user, $isOwner);

        return view('profile.show', compact(
            'user', 'auctions', 'isOwner', 'isFollowing', 'followerCount', 'followingCount', 'activities'
        ));
    }

    private function buildActivities(User $user, bool $isOwner)
    {
        $items = collect();

        $canSeeBids = $isOwner || ! $user->bids_hidden;

        if ($canSeeBids) {
            foreach ($user->bids()->with('auction')->latest()->take(10)->get() as $bid) {
                $items->push([
                    'type'   => 'bid',
                    'icon'   => 'bi-hammer',
                    'color'  => '#155eef',
                    'title'  => $isOwner ? 'Teklif verdin' : 'Teklif verdi',
                    'subject'=> $bid->auction?->title ?? 'İlan silinmiş',
                    'amount' => $bid->amount,
                    'date'   => $bid->created_at,
                    'url'    => $bid->auction ? route('auctions.show', $bid->auction) : null,
                ]);
            }
        }

        foreach ($user->purchases()->with('auction')->latest()->take(10)->get() as $order) {
            $items->push([
                'type'   => 'win',
                'icon'   => 'bi-trophy-fill',
                'color'  => '#f59e0b',
                'title'  => $isOwner ? 'Açık artırmayı kazandın' : 'Açık artırmayı kazandı',
                'subject'=> $order->auction?->title ?? 'İlan silinmiş',
                'amount' => $order->amount,
                'date'   => $order->created_at,
                'url'    => $order->auction ? route('auctions.show', $order->auction) : null,
            ]);
        }

        return $items->sortByDesc('date')->take(12)->values();
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_.]+$/',
                'unique:users,username,'.$user->id,
            ],
            'phone' => ['required', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:300'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Ad Soyad zorunludur.',
            'username.required' => 'Kullanıcı adı zorunludur.',
            'username.min' => 'Kullanıcı adı en az 3 karakter olmalı.',
            'username.max' => 'Kullanıcı adı en fazla 30 karakter olabilir.',
            'username.unique' => 'Bu kullanıcı adı zaten alınmış.',
            'username.regex' => 'Sadece harf, rakam, nokta ve alt çizgi kullanılabilir.',
            'phone.required' => 'Telefon zorunludur.',
            'bio.max' => 'Bio en fazla 300 karakter olabilir.',
        ]);

        $user->name = $request->name;
        $user->username = Str::lower($request->username);
        $user->phone = $request->phone;
        $user->bio = $request->bio;

        if ($request->hasFile('profile_image')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('profile_image')->store('avatars', 'public');
        }

        $user->save();

        return back()->with('profile_success', 'Profil bilgileriniz güncellendi.');
    }

    public function email(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'confirmemailpassword' => ['required'],
        ], [
            'email.required' => 'E-posta zorunludur.',
            'email.unique' => 'Bu e-posta zaten kullanılıyor.',
            'confirmemailpassword.required' => 'Mevcut şifrenizi girin.',
        ]);

        if (! Hash::check($request->confirmemailpassword, $user->password)) {
            return back()->withErrors(['confirmemailpassword' => 'Şifreniz hatalı.']);
        }

        $user->email = $request->email;
        $user->email_verified_at = null;
        $user->save();

        return back()->with('email_success', 'E-posta adresiniz güncellendi.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'currentpassword' => ['required'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'currentpassword.required' => 'Mevcut şifrenizi girin.',
            'password.required' => 'Yeni şifre zorunludur.',
            'password.confirmed' => 'Şifreler eşleşmiyor.',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->currentpassword, $user->password)) {
            return back()->withErrors(['currentpassword' => 'Mevcut şifreniz hatalı.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('password_success', 'Şifreniz başarıyla güncellendi.');
    }

    public function destroy(Request $request)
    {
        $user = auth()->user();

        if ($user->password) {
            $request->validate([
                'delete_password' => ['required'],
            ], [
                'delete_password.required' => 'Hesabı silmek için şifrenizi girin.',
            ]);

            if (! Hash::check($request->delete_password, $user->password)) {
                return back()->withErrors(['delete_password' => 'Şifreniz hatalı.']);
            }
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        auth()->logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Hesabınız kalıcı olarak silindi.');
    }

    public function show(string $username)
    {
        $username = ltrim($username, '@');
        $user = User::with('roles')
            ->where('username', strtolower($username))
            ->firstOrFail();

        $auctions = $user->auctions()->latest()->get();

        $isOwner = auth()->check() && auth()->id() === $user->id;
        $isFollowing = auth()->check() ? auth()->user()->isFollowing($user->id) : false;
        $followerCount = $user->followers()->count();
        $followingCount = $user->followings()->count();
        $activities = $this->buildActivities($user, $isOwner);

        return view('profile.show', compact(
            'user', 'auctions', 'isOwner', 'isFollowing', 'followerCount', 'followingCount', 'activities'
        ));
    }
}
