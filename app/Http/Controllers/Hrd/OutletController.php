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
            ->orderByDesc('is_main_office')->orderBy('name')->paginate(12)->withQueryString();

        return view('hrd.outlets.index', ['outlets' => $outlets, 'mapOutlets' => Outlet::active()->get()]);
    }

    public function create()
    {
        return view('hrd.outlets.form', [
            'outlet' => new Outlet([
                'radius_meters' => (int) Setting::get('default_radius', 50),
                'latitude' => -0.5022, 'longitude' => 117.1536, 'is_active' => true,
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
        ActivityLogger::log('outlet.created', "Membuat gerai {$outlet->name}", $outlet);

        return redirect()->route('hrd.outlets.index')->with('success', 'Gerai berhasil ditambahkan.');
    }

    public function edit(Outlet $outlet)
    {
        return view('hrd.outlets.form', ['outlet' => $outlet]);
    }

    public function update(Request $request, Outlet $outlet): RedirectResponse
    {
        $data = $this->validated($request, $outlet);

        if ($outlet->is_main_office && ! $data['is_main_office']) {
            return back()->withInput()->with('error', 'Kantor Utama tidak dapat diubah menjadi gerai biasa. Tetapkan lokasi lain sebagai Kantor Utama terlebih dahulu.');
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
        ActivityLogger::log('outlet.updated', "Memperbarui gerai {$outlet->name}", $outlet);

        return redirect()->route('hrd.outlets.index')->with('success', 'Gerai berhasil diperbarui.');
    }

    public function destroy(Outlet $outlet): RedirectResponse
    {
        if ($outlet->is_main_office) {
            return back()->with('error', 'Kantor Utama tidak dapat dihapus.');
        }
        if ($outlet->tasks()->exists() || $outlet->attendances()->exists()) {
            return back()->with('error', 'Gerai memiliki riwayat tugas/presensi. Nonaktifkan gerai alih-alih menghapus.');
        }

        $name = $outlet->name;
        $outlet->delete();
        ActivityLogger::log('outlet.deleted', "Menghapus gerai {$name}");

        return back()->with('success', 'Gerai berhasil dihapus.');
    }

    protected function validated(Request $request, ?Outlet $outlet = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('outlets', 'code')->ignore($outlet?->id)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:1000'],
        ], ['code.unique' => 'Kode gerai sudah digunakan.', 'code.alpha_dash' => 'Kode hanya boleh huruf, angka, strip, dan underscore.']);

        $data['code'] = strtoupper($data['code']);
        $data['is_main_office'] = $request->boolean('is_main_office');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
