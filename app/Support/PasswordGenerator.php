<?php

namespace App\Support;

class PasswordGenerator
{
    /** Password acak 8 karakter yang mudah diketik: selalu memuat huruf besar, kecil, dan angka. */
    public static function generate(int $length = 8): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all = $upper.$lower.$digits;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        // Fisher–Yates dengan random_int (CSPRNG)
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /** Data untuk modal kredensial + template pesan WhatsApp siap kirim. */
    public static function credentialPayload(\App\Models\User $user, string $password, string $heading = 'Kredensial akun Anda'): array
    {
        $message = "Halo {$user->name},\n\n{$heading} pada aplikasi *D&P PT. HSM*:\n\n"
            .($user->phone ? "Nomor HP: {$user->phone}\n" : "")
            ."Email: {$user->email}\nPassword: {$password}\nLink: ".url('/login')
            ."\n\n(Anda dapat login langsung menggunakan Nomor Handphone terdaftar)\n\nDemi keamanan, segera ganti password Anda setelah login pertama (menu Profil).\nTerima kasih.";

        $phone = preg_replace('/\D+/', '', (string) $user->phone);
        if ($phone && str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        return [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'email' => $user->email,
            'password' => $password,
            'message' => $message,
            'wa_url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
        ];
    }
}
