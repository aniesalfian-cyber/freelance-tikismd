<?php

namespace App\Services;

use App\Models\Setting;

class GeofencingService
{
    /** Jari-jari bumi rata-rata dalam meter. */
    public const EARTH_RADIUS_METERS = 6371000;

    /**
     * Jarak dua koordinat (meter) memakai formula Haversine.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lon2 - $lon1);

        $a = sin($deltaPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1, sqrt($a)));
    }

    public function isWithinRadius(float $lat1, float $lon1, float $lat2, float $lon2, int $radiusMeters): bool
    {
        return $this->calculateDistance($lat1, $lon1, $lat2, $lon2) <= $radiusMeters;
    }

    /**
     * Validasi akurasi hardware GPS (indikasi sinyal buruk / mock location).
     *
     * @return array{0: bool, 1: string|null} [valid, pesan]
     */
    public function validateHardwareAccuracy(?float $accuracy): array
    {
        $max = (float) Setting::get('max_gps_accuracy', 50);

        if ($accuracy === null) {
            return [false, 'Akurasi GPS perangkat tidak terbaca. Aktifkan izin lokasi presisi tinggi lalu coba lagi.'];
        }

        if ($accuracy <= 0) {
            return [false, 'Data GPS tidak valid (akurasi 0 mengindikasikan lokasi palsu). Gunakan GPS asli perangkat.'];
        }

        if ($accuracy > $max) {
            return [false, sprintf('Akurasi GPS terlalu rendah (±%d m, batas %d m). Pindah ke area terbuka lalu coba lagi.', round($accuracy), $max)];
        }

        return [true, null];
    }
}
