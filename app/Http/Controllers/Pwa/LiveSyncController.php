<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSyncController extends Controller
{
    /**
     * Endpoint sinkronisasi berkala:
     * 1. Jika karyawan berstatus masuk kerja hari ini (sudah check-in & belum check-out),
     *    otomatis simpan titik koordinat GPS terkini ke server untuk dipantau Admin HRD.
     * 2. Ambil daftar notifikasi terbaru & antrean push notifikasi tugas baru.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();

        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', today())
            ->first();

        $isWorking = $todayAttendance !== null && $todayAttendance->check_out_at === null;
        $locationSaved = false;

        if ($user->isFreelancer() && $isWorking && $request->filled(['latitude', 'longitude'])) {
            $lat = (float) $request->input('latitude');
            $lng = (float) $request->input('longitude');
            $acc = $request->filled('accuracy') ? (float) $request->input('accuracy') : null;

            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat != 0 || $lng != 0)) {
                $user->recordLiveLocation($lat, $lng, $acc);
                $locationSaved = true;
            }
        }

        // Ambil notifikasi yang belum pernah di-push ke OS browser
        $unpushed = AppNotification::where('user_id', $user->id)
            ->whereNull('pushed_at')
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($unpushed->isNotEmpty()) {
            AppNotification::whereIn('id', $unpushed->pluck('id'))
                ->update(['pushed_at' => now()]);
        }

        $unreadCount = AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $recent = AppNotification::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url ?: route('pwa.workspace'),
                'is_unread' => $n->read_at === null,
                'time_ago' => $n->created_at?->diffForHumans() ?? 'Baru saja',
            ]);

        return response()->json([
            'ok' => true,
            'is_working' => $isWorking,
            'location_saved' => $locationSaved,
            'last_location_at' => $user->last_location_at?->format('H:i:s'),
            'unread_count' => $unreadCount,
            'notifications' => $recent,
            'push_queue' => $unpushed->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url ?: route('pwa.workspace'),
            ])->values(),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = AppNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notif->update(['read_at' => now(), 'pushed_at' => $notif->pushed_at ?? now()]);

        return response()->json(['ok' => true, 'url' => $notif->url]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'pushed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
