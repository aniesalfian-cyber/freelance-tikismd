@extends('layouts.app', ['title' => 'Monitoring & Validasi Presensi GPS', 'subtitle' => 'Daftar kehadiran di Kantor Utama HSM dan input manual terverifikasi'])

@section('content')
<div class="glass p-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('hrd.attendances.index') }}" class="flex items-center gap-2">
        <input type="date" name="date" value="{{ $date->toDateString() }}" class="input">
        <button type="submit" class="btn btn-ghost">Tampilkan Tanggal</button>
    </form>
    <div class="text-xs text-slate-300">
        Hadir: <b class="text-emerald-400">{{ $attendances->count() }}</b> &bull;
        Belum Presensi: <b class="text-amber-400">{{ $absent->count() }}</b>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Attendance List -->
    <div class="lg:col-span-2 table-surface">
        <div>
            <div class="orn-line mb-1"></div>
            <h2 class="text-base font-extrabold text-white">Daftar Presensi Masuk & Pulang ({{ $date->translatedFormat('d F Y') }})</h2>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Jarak GPS</th>
                        <th>Keterlambatan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $a)
                        <tr>
                            <td data-card-header="1" data-label="Karyawan">
                                <div class="flex items-center justify-between w-full gap-2">
                                    <div>
                                        <div class="font-extrabold text-white text-sm">{{ $a->user?->name }}</div>
                                        <div class="font-mono text-xs text-orange-300">NIK: {{ $a->user?->nik }}</div>
                                    </div>
                                    <span class="badge {{ $a->statusBadge() }} md:hidden">{{ $a->statusLabel() }}</span>
                                </div>
                            </td>
                            <td data-label="Jam Masuk" class="font-mono text-xs font-bold text-white">{{ $a->check_in_at->format('H:i:s') }} WITA</td>
                            <td data-label="Jam Pulang" class="font-mono text-xs text-slate-300">{{ $a->check_out_at ? $a->check_out_at->format('H:i:s').' WITA' : 'Belum Pulang' }}</td>
                            <td data-label="Jarak ke Kantor Utama" class="text-xs font-semibold text-white">{{ $a->check_in_distance_meters }} meter</td>
                            <td data-label="Keterlambatan" class="text-xs {{ $a->late_minutes > 0 ? 'text-amber-400 font-bold' : 'text-emerald-400' }}">
                                {{ $a->late_minutes > 0 ? $a->late_minutes.' menit' : 'Tepat waktu' }}
                            </td>
                            <td data-label="Status & Catatan">
                                <div>
                                    <span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span>
                                    @if($a->notes)
                                        <div class="text-[11px] text-slate-400 mt-1">{{ $a->notes }}</div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada presensi pada tanggal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Manual Bypass Entry (HRD / SuperAdmin) -->
    <div class="space-y-6">
        @if(auth()->user()->canManage())
            <div class="glass p-5 space-y-4">
                <div class="orn-line mb-1"></div>
                <h3 class="text-base font-extrabold text-white">Input Presensi Manual (Bypass Kendala GPS)</h3>
                <form method="POST" action="{{ route('hrd.attendances.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <div>
                        <label class="label">Pilih Karyawan (Belum Presensi)</label>
                        <select name="user_id" required class="input">
                            <option value="">-- Pilih Karyawan --</option>
                            @foreach($absent as $ab)
                                <option value="{{ $ab->id }}">{{ $ab->name }} ({{ $ab->nik }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Jam Masuk</label>
                            <input type="time" name="check_in_time" value="08:00" required class="input">
                        </div>
                        <div>
                            <label class="label">Jam Pulang (Opsional)</label>
                            <input type="time" name="check_out_time" class="input">
                        </div>
                    </div>
                    <div>
                        <label class="label">Alasan Validasi Manual</label>
                        <input type="text" name="notes" placeholder="Kendala perangkat GPS di kantor utama" required class="input text-xs">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-sm">Simpan Presensi Manual</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
