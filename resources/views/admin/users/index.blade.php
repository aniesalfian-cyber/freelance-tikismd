@extends('layouts.app', ['title' => 'Manajemen Pengguna & Reset Password', 'subtitle' => 'Kelola akun SuperAdmin, HRD, Karyawan, dan Owner'])

@section('content')
<div class="glass p-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, email, NIK..." class="input sm:max-w-xs">
        <select name="role" class="input sm:w-48">
            <option value="">Semua Role</option>
            @foreach(\App\Models\User::ROLES as $rk => $rl)
                <option value="{{ $rk }}" @selected(request('role') === $rk)>{{ $rl }}</option>
            @endforeach
        </select>
        <select name="status" class="input sm:w-40">
            <option value="">Semua Status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
    </form>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <x-icon name="plus" class="w-4 h-4" /> Tambah Pengguna
    </a>
</div>

<div class="table-surface">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Nama & Email</th>
                    <th>NIK & WhatsApp</th>
                    <th>Role & Spesialisasi</th>
                    <th>Status Akun</th>
                    <th>Login Terakhir</th>
                    <th class="text-right">Aksi & Quick Reset</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td data-card-header="1" data-label="Identitas Pengguna">
                            <div class="flex items-center justify-between w-full gap-2">
                                <div>
                                    <div class="font-extrabold text-white text-sm">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-400">{{ $u->email }}</div>
                                </div>
                                <span class="badge {{ $u->is_active ? 'badge-success' : 'badge-danger' }} md:hidden">
                                    {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </td>
                        <td data-label="NIK & WhatsApp" class="text-xs">
                            <div>
                                <div class="font-mono font-semibold text-white">{{ $u->nik ?: '-' }}</div>
                                <div class="text-slate-400">{{ $u->phone ?: '-' }}</div>
                            </div>
                        </td>
                        <td data-label="Role & Bidang">
                            <div>
                                <span class="badge badge-orange">{{ $u->roleLabel() }}</span>
                                @if($u->specialization)
                                    <div class="text-[11px] text-slate-400 mt-1">{{ $u->specialization }}</div>
                                @endif
                            </div>
                        </td>
                        <td data-label="Status Akun">
                            @if($u->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td data-label="Login Terakhir" class="text-xs text-slate-300">
                            {{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Belum pernah login' }}
                        </td>
                        <td data-card-actions="1" data-label="Tindakan Manajemen">
                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                <form method="POST" action="{{ route('admin.users.reset', $u) }}"
                                      data-confirm="Reset password akun {{ $u->name }} dan tampilkan template pesan WhatsApp?"
                                      data-confirm-title="Quick Reset Password">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Reset Pass</button>
                                </form>
                                <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-ghost btn-sm">Edit</a>
                                @if($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm">
                                            {{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                          data-confirm="Hapus permanen akun {{ $u->name }}?"
                                          data-confirm-title="Hapus Akun">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-400 py-8">Tidak ada pengguna ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection
