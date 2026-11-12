<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value', 'label', 'group'];

    /** Nilai bawaan seluruh konfigurasi sistem. */
    public const DEFAULTS = [
        'company_name' => ['PT. Hubungan Saudara Mulia (HSM)', 'Nama Perusahaan', 'umum'],
        'company_address' => ['Samarinda, Kalimantan Timur', 'Alamat Perusahaan', 'umum'],
        'company_contact' => ['info@hsm.id | (0541) 000000', 'Kontak Perusahaan (Telp/Email)', 'umum'],
        'work_start_time' => ['08:00', 'Jam Mulai Kerja (batas tepat waktu presensi)', 'presensi'],
        'work_end_time' => ['17:00', 'Jam Pulang Kerja', 'presensi'],
        'max_gps_accuracy' => ['50', 'Batas Akurasi GPS Maksimum (meter)', 'presensi'],
        'default_radius' => ['50', 'Radius Geofencing Bawaan (meter)', 'presensi'],
        'weight_completion' => ['35', 'Bobot KPI: Penyelesaian Tugas (%)', 'kpi'],
        'weight_time' => ['30', 'Bobot KPI: Ketepatan Waktu (%)', 'kpi'],
        'weight_location' => ['20', 'Bobot KPI: Kepatuhan Lokasi (%)', 'kpi'],
        'weight_attendance' => ['15', 'Bobot KPI: Kedisiplinan Presensi (%)', 'kpi'],
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::DEFAULTS[$key][0] ?? null);
    }

    public static function set(string $key, mixed $value): void
    {
        $meta = self::DEFAULTS[$key] ?? [null, $key, 'umum'];
        static::updateOrCreate(['key' => $key], [
            'value' => (string) $value,
            'label' => $meta[1],
            'group' => $meta[2],
        ]);
        Cache::forget('hsm.settings');
    }

    public static function allCached(): array
    {
        return Cache::remember('hsm.settings', 3600, fn () => static::query()->pluck('value', 'key')->all());
    }
}
