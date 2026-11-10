<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Outlet;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(Request $request)
    {
        $report = $request->filled('type') ? $this->reports->build($request->type, $this->filters($request)) : null;

        return view('reports.index', [
            'report' => $report,
            'types' => ReportService::TYPES,
            'outlets' => Outlet::orderBy('name')->get(['id', 'name']),
            'freelancers' => User::freelancers()->orderBy('name')->get(['id', 'name']),
            'query' => $request->query(),
        ]);
    }

    public function pdf(Request $request)
    {
        $request->validate(['type' => ['required', 'in:'.implode(',', array_keys(ReportService::TYPES))]]);
        $report = $this->reports->build($request->type, $this->filters($request));
        ActivityLogger::log('report.export', "Ekspor PDF {$report['title']} ({$report['document_number']})");

        $orientation = count($report['columns']) > 8 ? 'landscape' : 'portrait';
        $pdf = Pdf::loadView('reports.pdf', ['report' => $report])->setPaper('a4', $orientation);

        return $pdf->download('HSM-'.$request->type.'-'.now()->format('Ymd_His').'.pdf');
    }

    public function excel(Request $request)
    {
        $request->validate(['type' => ['required', 'in:'.implode(',', array_keys(ReportService::TYPES))]]);
        $report = $this->reports->build($request->type, $this->filters($request));
        ActivityLogger::log('report.export', "Ekspor Excel {$report['title']} ({$report['document_number']})");

        return Excel::download(new ReportExport($report), 'HSM-'.$request->type.'-'.now()->format('Ymd_His').'.xlsx');
    }

    protected function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'grade' => ['nullable', 'in:A,B,C,D'],
        ]);
    }
}
