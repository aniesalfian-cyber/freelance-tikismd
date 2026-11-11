<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        $rawLogin = trim((string) ($request->input('login') ?: $request->input('email')));
        if ($rawLogin === '') {
            throw ValidationException::withMessages([
                'login' => 'Nomor Handphone atau Email wajib diisi.',
                'email' => 'Nomor Handphone atau Email wajib diisi.',
            ]);
        }

        $password = (string) $request->input('password');
        $throttleKey = Str::lower($rawLogin).'|'.$request->ip();

        // Maks 5 percobaan gagal per menit.
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        // Cari user berdasarkan Email atau No. Handphone (normalisasi awalan 08 / 62 / +62 / tanpa spasi)
        $cleanPhone = preg_replace('/\D+/', '', $rawLogin);
        $phoneVariants = array_filter([$rawLogin, $cleanPhone]);
        if ($cleanPhone) {
            if (str_starts_with($cleanPhone, '62')) {
                $phoneVariants[] = '0'.substr($cleanPhone, 2);
            } elseif (str_starts_with($cleanPhone, '0')) {
                $phoneVariants[] = '62'.substr($cleanPhone, 1);
            }
        }
        $phoneVariants = array_values(array_unique($phoneVariants));

        $user = User::query()
            ->where('email', $rawLogin)
            ->orWhereIn('phone', $phoneVariants)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            ActivityLogger::log('auth.login_failed', 'Login gagal untuk '.$rawLogin);

            throw ValidationException::withMessages([
                'login' => 'Nomor HP/Email atau password salah.',
                'email' => 'Nomor HP/Email atau password salah.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Akun Anda dinonaktifkan. Hubungi HRD / SuperAdmin.',
                'email' => 'Akun Anda dinonaktifkan. Hubungi HRD / SuperAdmin.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        ActivityLogger::log('auth.login', "{$user->name} login sebagai {$user->roleLabel()}", $user, $user->id);

        return redirect()->to($user->dashboardRoute());
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            ActivityLogger::log('auth.logout', "{$user->name} logout", $user, $user->id);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
