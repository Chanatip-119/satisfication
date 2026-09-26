<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Counter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportReportController extends Controller
{
    public function index()
    {
        $counters = Counter::where('is_active', true)->get();
        return view('report.export', compact('counters'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'export_type' => 'required|in:pdf,excel',
            'time_range' => 'required',
        ]);

        return DB::transaction(function () use ($request) {
            $startDate = null;
            $endDate = null;

            switch ($request->time_range) {
                case 'today':
                    $startDate = Carbon::today()->startOfDay();
                    $endDate = Carbon::today()->endOfDay();
                    break;
                case 'week':
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now()->endOfWeek();
                    break;
                case 'month':
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                    break;
                case 'custom':
                    $request->validate([
                        'start_date' => 'required|date',
                        'end_date' => 'required|date|after_or_equal:start_date',
                    ]);
                    $startDate = Carbon::parse($request->start_date)->startOfDay();
                    $endDate = Carbon::parse($request->end_date)->endOfDay();
                    break;
            }

            $selectedCounters = [];
            if (!$request->has('select_all') && $request->has('counters')) {
                $selectedCounters = $request->counters;
            }

            $query = Evaluation::with(['checkin.staff', 'checkin.schedule.counterSub']);

            if ($startDate && $endDate) {
                $query->whereBetween('evaluation_at', [$startDate, $endDate]);
            }

            if (!empty($selectedCounters)) {
                $query->whereHas('checkin.schedule.counterSub', function ($q) use ($selectedCounters) {
                    $q->whereIn('counter_id', $selectedCounters);
                });
            }

            $evaluations = $query->orderBy('evaluation_at', 'desc')->get();

            if ($request->export_type === 'pdf') {
                return $this->exportPDF($evaluations, $startDate, $endDate);
            } else {
                return $this->exportExcel($evaluations);
            }
        });
    }

    private function exportPDF($evaluations, $startDate, $endDate)
    {
        $dateLabel = ($startDate && $endDate) 
            ? $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y') 
            : 'ทั้งหมด';

        $pdf = Pdf::loadView('report.export_pdf_template', compact('evaluations', 'dateLabel'));
        return $pdf->download('report_' . date('Ymd_His') . '.pdf');
    }

    private function exportExcel($evaluations)
    {
        $fileName = 'report_' . date('Ymd_His') . '.csv';
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($evaluations) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            
            fputcsv($file, ['วันที่', 'เวลา', 'เคาน์เตอร์', 'รายชื่อ', 'คะแนน', 'ความคิดเห็น']);

            foreach ($evaluations as $eval) {
                $counterId = $eval->checkin && $eval->checkin->schedule ? 'เคาน์เตอร์ ' . $eval->checkin->schedule->counter_sub_id : 'ไม่ระบุ';
                $staffName = $eval->checkin && $eval->checkin->staff ? $eval->checkin->staff->staff_name : 'ไม่ระบุ';
                $date = Carbon::parse($eval->evaluation_at)->format('d/m/Y');
                $time = Carbon::parse($eval->evaluation_at)->format('H:i');

                fputcsv($file, [
                    $date,
                    $time,
                    $counterId,
                    $staffName,
                    $eval->rating,
                    $eval->comment
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}