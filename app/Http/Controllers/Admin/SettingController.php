<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = collect(Setting::DEFAULTS)->map(fn ($meta, $key) => [
            'key' => $key, 'label' => $meta[1], 'group' => $meta[2], 'value' => Setting::get($key),
        ])->groupBy('group');

        return view('admin.settings', ['groups' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['required', 'string', 'max:255'],
            'company_contact' => ['required', 'string', 'max:150'],
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i', 'after:work_start_time'],
            'max_gps_accuracy' => ['required', 'integer', 'min:5', 'max:500'],
            'default_radius' => ['required', 'integer', 'min:10', 'max:1000'],
            'weight_completion' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_time' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_location' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_attendance' => ['required', 'integer', 'min:0', 'max:100'],
        ], ['work_end_time.after' => 'Jam pulang harus setelah jam mulai kerja.']);

        $sum = $data['weight_completion'] + $data['weight_time'] + $data['weight_location'] + $data['weight_attendance'];
        if ($sum !== 100) {
            return back()->withInput()->withErrors(['weight_completion' => "Total bobot KPI harus 100% (saat ini {$sum}%)."]);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }
        ActivityLogger::log('settings.updated', 'Memperbarui konfigurasi sistem');

        return back()->with('success', 'Pengaturan sistem disimpan. Bobot baru berlaku pada penilaian berikutnya.');
    }
}
