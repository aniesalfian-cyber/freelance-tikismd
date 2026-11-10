<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\PerformanceLog;
use App\Services\PerformanceScoringService;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function show(Request $request, PerformanceScoringService $scoring)
    {
        $user = $request->user();
        $logs = PerformanceLog::where('user_id', $user->id)->where('evaluation_date', '>=', today()->subDays(29))->orderBy('evaluation_date')->get();
        $avg = round((float) $logs->avg('final_kpi_score'), 2);
        $latest = $logs->last();

        return view('pwa.performance', [
            'logs' => $logs,
            'latest' => $latest,
            'avg' => $avg,
            'avgGrade' => PerformanceScoringService::gradeFor($avg),
            'weights' => $scoring->weights(),
            'tips' => $this->tips($latest),
        ]);
    }

    /** Saran perbaikan sederhana berbasis sub-skor terendah hari terakhir. */
    protected function tips(?PerformanceLog $log): array
    {
        if (! $log) {
            return ['Belum ada penilaian. Selesaikan tugas hari ini untuk melihat skor kinerja Anda.'];
        }

        $tips = [];
        if ($log->attendance_score < 100) {
            $tips[] = 'Lakukan presensi sebelum jam mulai kerja agar skor kedisiplinan maksimal.';
        }
        if ($log->time_score < 85) {
            $tips[] = 'Durasi pengerjaan melebihi SLA. Mulai tugas tepat waktu dan fokus menyelesaikannya sesuai estimasi.';
        }
        if ($log->location_score < 100) {
            $tips[] = 'Pastikan memulai dan menyelesaikan tugas di dalam radius gerai agar lokasi tervalidasi.';
        }
        if ($log->completion_score < 100) {
            $tips[] = 'Masih ada tugas yang belum diselesaikan. Selesaikan semua tugas harian Anda.';
        }

        return $tips ?: ['Luar biasa! Pertahankan performa Anda.'];
    }
}
