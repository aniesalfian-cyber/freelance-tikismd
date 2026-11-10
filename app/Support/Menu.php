<?php

namespace App\Support;

use App\Models\User;

/**
 * Definisi menu navigasi per role (dipakai sidebar desktop & bubble dock mobile).
 * `dock` => true menandai item yang tampil langsung di dock mobile (maks 4 + "Lainnya").
 */
class Menu
{
    public static function for(User $user): array
    {
        $items = match ($user->role) {
            User::ROLE_SUPERADMIN => [
                ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Executive', 'icon' => 'chart', 'route' => 'owner.dashboard', 'match' => 'owner.dashboard', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Pengguna', 'icon' => 'users', 'route' => 'admin.users.index', 'match' => 'admin.users.*', 'dock' => true, 'group' => 'Superadmin'],
                ['label' => 'Gerai', 'icon' => 'pin', 'route' => 'hrd.outlets.index', 'match' => 'hrd.outlets.*', 'dock' => false, 'group' => 'Data Master'],
                ['label' => 'Karyawan', 'icon' => 'badge', 'route' => 'hrd.freelancers.index', 'match' => 'hrd.freelancers.*', 'dock' => false, 'group' => 'Data Master'],
                ['label' => 'Template Tugas', 'icon' => 'repeat', 'route' => 'hrd.templates.index', 'match' => 'hrd.templates.*', 'dock' => false, 'group' => 'Operasional'],
                ['label' => 'Penugasan', 'icon' => 'clipboard', 'route' => 'hrd.tasks.index', 'match' => 'hrd.tasks.*', 'dock' => true, 'group' => 'Operasional'],
                ['label' => 'Presensi', 'icon' => 'clock', 'route' => 'hrd.attendances.index', 'match' => 'hrd.attendances.*', 'dock' => false, 'group' => 'Operasional'],
                ['label' => 'Penilaian KPI', 'icon' => 'star', 'route' => 'hrd.performance.index', 'match' => 'hrd.performance.*', 'dock' => false, 'group' => 'Operasional'],
                ['label' => 'Laporan', 'icon' => 'document', 'route' => 'reports.index', 'match' => 'reports.*', 'dock' => false, 'group' => 'Laporan'],
                ['label' => 'Backup & Restore', 'icon' => 'database', 'route' => 'admin.backups.index', 'match' => 'admin.backups.*', 'dock' => false, 'group' => 'Superadmin'],
                ['label' => 'Pengaturan', 'icon' => 'cog', 'route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'dock' => false, 'group' => 'Superadmin'],
                ['label' => 'Log Aktivitas', 'icon' => 'shield', 'route' => 'admin.logs.index', 'match' => 'admin.logs.*', 'dock' => false, 'group' => 'Superadmin'],
            ],
            User::ROLE_HRD => [
                ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'hrd.dashboard', 'match' => 'hrd.dashboard', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Penugasan', 'icon' => 'clipboard', 'route' => 'hrd.tasks.index', 'match' => 'hrd.tasks.*', 'dock' => true, 'group' => 'Operasional'],
                ['label' => 'Presensi', 'icon' => 'clock', 'route' => 'hrd.attendances.index', 'match' => 'hrd.attendances.*', 'dock' => true, 'group' => 'Operasional'],
                ['label' => 'Karyawan', 'icon' => 'badge', 'route' => 'hrd.freelancers.index', 'match' => 'hrd.freelancers.*', 'dock' => true, 'group' => 'Data Master'],
                ['label' => 'Gerai', 'icon' => 'pin', 'route' => 'hrd.outlets.index', 'match' => 'hrd.outlets.*', 'dock' => false, 'group' => 'Data Master'],
                ['label' => 'Template Tugas', 'icon' => 'repeat', 'route' => 'hrd.templates.index', 'match' => 'hrd.templates.*', 'dock' => false, 'group' => 'Operasional'],
                ['label' => 'Penilaian KPI', 'icon' => 'star', 'route' => 'hrd.performance.index', 'match' => 'hrd.performance.*', 'dock' => false, 'group' => 'Operasional'],
                ['label' => 'Executive', 'icon' => 'chart', 'route' => 'owner.dashboard', 'match' => 'owner.dashboard', 'dock' => false, 'group' => 'Laporan'],
                ['label' => 'Laporan', 'icon' => 'document', 'route' => 'reports.index', 'match' => 'reports.*', 'dock' => false, 'group' => 'Laporan'],
            ],
            User::ROLE_OWNER => [
                ['label' => 'Executive', 'icon' => 'chart', 'route' => 'owner.dashboard', 'match' => 'owner.dashboard', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Laporan', 'icon' => 'document', 'route' => 'reports.index', 'match' => 'reports.*', 'dock' => true, 'group' => 'Laporan'],
                ['label' => 'Performa', 'icon' => 'star', 'route' => 'hrd.performance.index', 'match' => 'hrd.performance.*', 'dock' => true, 'group' => 'Laporan'],
                ['label' => 'Penugasan', 'icon' => 'clipboard', 'route' => 'hrd.tasks.index', 'match' => 'hrd.tasks.*', 'dock' => true, 'group' => 'Monitoring'],
                ['label' => 'Presensi', 'icon' => 'clock', 'route' => 'hrd.attendances.index', 'match' => 'hrd.attendances.*', 'dock' => false, 'group' => 'Monitoring'],
                ['label' => 'Karyawan', 'icon' => 'badge', 'route' => 'hrd.freelancers.index', 'match' => 'hrd.freelancers.*', 'dock' => false, 'group' => 'Monitoring'],
                ['label' => 'Gerai', 'icon' => 'pin', 'route' => 'hrd.outlets.index', 'match' => 'hrd.outlets.*', 'dock' => false, 'group' => 'Monitoring'],
            ],
            default => [ // Karyawan
                ['label' => 'Beranda', 'icon' => 'home', 'route' => 'pwa.workspace', 'match' => 'pwa.workspace', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Tugas', 'icon' => 'clipboard', 'route' => 'pwa.tasks.index', 'match' => 'pwa.tasks.*', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Presensi', 'icon' => 'pin', 'route' => 'pwa.attendance', 'match' => 'pwa.attendance*', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Kinerja', 'icon' => 'star', 'route' => 'pwa.performance', 'match' => 'pwa.performance', 'dock' => true, 'group' => 'Utama'],
                ['label' => 'Profil', 'icon' => 'user', 'route' => 'profile.edit', 'match' => 'profile.*', 'dock' => true, 'group' => 'Utama'],
            ],
        };

        return array_map(function ($item) {
            $item['url'] = route($item['route']);
            $item['active'] = request()->routeIs($item['match']);

            return $item;
        }, $items);
    }

    /** Pisahkan item dock (maks 4 bila ada overflow) dan sisanya untuk panel "Lainnya". */
    public static function dock(array $items, User $user): array
    {
        $dock = array_values(array_filter($items, fn ($i) => $i['dock']));
        $rest = array_values(array_filter($items, fn ($i) => ! $i['dock']));

        if ($user->role === User::ROLE_FREELANCER) {
            return ['dock' => $dock, 'more' => []];
        }

        // Role manajemen: selalu tambahkan profil ke panel "Lainnya".
        $rest[] = [
            'label' => 'Profil Saya', 'icon' => 'user', 'route' => 'profile.edit', 'match' => 'profile.*',
            'dock' => false, 'group' => 'Akun', 'url' => route('profile.edit'), 'active' => request()->routeIs('profile.*'),
        ];

        return ['dock' => array_slice($dock, 0, 4), 'more' => $rest];
    }
}
