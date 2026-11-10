@extends('layouts.app', ['title' => 'Manajemen Pengguna & Reset Password', 'subtitle' => 'Kelola akun SuperAdmin, HRD, Freelancer, dan Owner'])

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

<div class="glass p-5">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Nama & Email</th>
                    <th>NIK / WhatsApp</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th class="text-right">Aksi & Quick Reset</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>
                            <div class="font-bold text-white">{{ $u->name }}</div>
                            <div class="text-xs text-slate-400">{{ $u->email }}</div>
                        </td>
                        <td class="text-xs">
                            <div class="font-mono text-white">{{ $u->nik ?: '-' }}</div>
                            <div class="text-slate-400">{{ $u->phone ?: '-' }}</div>
                        </td>
                        <td><span class="badge badge-orange">{{ $u->roleLabel() }}</span></td>
                        <td>
                            @if($u->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="text-xs text-slate-400">{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Belum pernah' }}</td>
                        <td>
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
