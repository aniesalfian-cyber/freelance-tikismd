<?php

namespace App\Http\Controllers;

use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s]{6,25}$/'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.unique' => 'Alamat email sudah digunakan oleh akun lain.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($data['avatar']);
        }

        $user->update($data);
        ActivityLogger::log('profile.update', 'Memperbarui profil dan identitas akun', $user);

        return back()->with('success', 'Profil & foto berhasil diperbarui.');
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'string'],
            'pin' => ['required', 'digits:4', 'confirmed'],
        ], [
            'pin.digits' => 'PIN baru wajib terdiri dari tepat 4 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN baru tidak sama.',
        ]);

        $user = $request->user();
        $validCurrent = false;

        if ($user->pin && Hash::check($data['current_pin'], $user->pin)) {
            $validCurrent = true;
        } elseif (! $user->pin && $data['current_pin'] === '1234') {
            $validCurrent = true;
        } elseif (Hash::check($data['current_pin'], $user->password)) {
            $validCurrent = true;
        }

        if (! $validCurrent) {
            return back()->withErrors(['current_pin' => 'PIN saat ini (atau Password akun) tidak sesuai.']);
        }

        $user->update(['pin' => $data['pin']]);
        ActivityLogger::log('profile.pin', 'Mengganti PIN 4 digit', $user);

        return back()->with('success', 'PIN 4 Digit berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.min' => 'Password baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
        ]);

        $user = $request->user();
        $validCurrent = Hash::check($data['current_password'], $user->password)
            || ($user->pin && Hash::check($data['current_password'], $user->pin));

        if (! $validCurrent) {
            return back()->withErrors(['current_password' => 'Password atau PIN saat ini salah.']);
        }

        $user->update(['password' => $data['password']]);
        ActivityLogger::log('profile.password', 'Mengganti password', $user);

        return back()->with('success', 'Password berhasil diganti.');
    }
}
