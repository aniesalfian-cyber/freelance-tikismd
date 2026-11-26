<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $outlets = Outlet::query()->withCount('tasks')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")->orWhere('code', 'like', "%{$request->q}%")->orWhere('address', 'like', "%{$request->q}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->when($request->filled('type'), function ($q) use ($request) {
                if ($request->type === 'main_office') {
                    $q->where('is_main_office', true);
                } elseif ($request->type === Outlet::TYPE_CORPORATE) {
                    $q->where('type', Outlet::TYPE_CORPORATE);
                } elseif ($request->type === Outlet::TYPE_GERAI) {
                    $q->where('type', Outlet::TYPE_GERAI)->where('is_main_office', false);
                }
            })
            ->orderByDesc('is_main_office')->orderBy('name')->paginate(12)->withQueryString();

        $stats = [
            'total' => Outlet::count(),
            'gerai' => Outlet::where('is_main_office', false)->where('type', Outlet::TYPE_GERAI)->count(),
            'corporate' => Outlet::where('type', Outlet::TYPE_CORPORATE)->count(),
            'main_office' => Outlet::where('is_main_office', true)->count(),
        ];

        return view('hrd.outlets.index', [
            'outlets' => $outlets,
            'mapOutlets' => Outlet::active()->get(),
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('hrd.outlets.form', [
            'outlet' => new Outlet([
                'type' => Outlet::TYPE_GERAI,
                'radius_meters' => (int) Setting::get('default_radius', 50),
                'latitude' => -0.50210600,
                'longitude' => 117.15370900,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $outlet = DB::transaction(function () use ($data) {
            if ($data['is_main_office']) {
                Outlet::where('is_main_office', true)->update(['is_main_office' => false]);
            }

            return Outlet::create($data);
        });

        $typeLabel = $outlet->is_main_office ? 'Kantor Utama' : ($outlet->isCorporate() ? 'Corporate' : 'Gerai');
        ActivityLogger::log('outlet.created', "Membuat {$typeLabel} {$outlet->name}", $outlet);

        return redirect()->route('hrd.outlets.index')->with('success', "Data {$typeLabel} berhasil ditambahkan.");
    }

    public function edit(Outlet $outlet)
    {
        return view('hrd.outlets.form', ['outlet' => $outlet]);
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $data = $this->validated($request, $outlet);

        if ($outlet->is_main_office && ! $data['is_main_office']) {
            return back()->withInput()->with('error', 'Kantor Utama tidak dapat diubah menjadi lokasi biasa. Tetapkan lokasi lain sebagai Kantor Utama terlebih dahulu.');
        }
        if ($outlet->is_main_office && ! $data['is_active']) {
            return back()->withInput()->with('error', 'Kantor Utama tidak dapat dinonaktifkan karena menjadi titik presensi wajib.');
        }

        DB::transaction(function () use ($outlet, $data) {
            if ($data['is_main_office']) {
                Outlet::where('id', '!=', $outlet->id)->where('is_main_office', true)->update(['is_main_office' => false]);
            }
            $outlet->update($data);
        });

        $typeLabel = $outlet->is_main_office ? 'Kantor Utama' : ($outlet->isCorporate() ? 'Corporate' : 'Gerai');
        ActivityLogger::log('outlet.updated', "Memperbarui {$typeLabel} {$outlet->name}", $outlet);

        return redirect()->route('hrd.outlets.index')->with('success', "Data {$typeLabel} berhasil diperbarui.");
    }

    public function destroy(Outlet $outlet): RedirectResponse
    {
        if ($outlet->is_main_office) {
            return back()->with('error', 'Kantor Utama tidak dapat dihapus.');
        }
        if ($outlet->tasks()->exists() || $outlet->attendances()->exists()) {
            return back()->with('error', 'Lokasi memiliki riwayat tugas/presensi. Nonaktifkan lokasi alih-alih menghapus.');
        }

        $name = $outlet->name;
        $typeLabel = $outlet->isCorporate() ? 'Corporate' : 'Gerai';
        $outlet->delete();
        ActivityLogger::log('outlet.deleted', "Menghapus {$typeLabel} {$name}");

        return back()->with('success', "Data {$typeLabel} berhasil dihapus.");
    }

    protected function validated(Request $request, ?Outlet $outlet = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in([Outlet::TYPE_GERAI, Outlet::TYPE_CORPORATE])],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:1000'],
        ], [
            'code.alpha_dash' => 'Kode hanya boleh huruf, angka, strip, dan underscore.',
            'type.in' => 'Tipe lokasi harus berupa Gerai Cabang atau Corporate.',
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_main_office'] = $request->boolean('is_main_office');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
