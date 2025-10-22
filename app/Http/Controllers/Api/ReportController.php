<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Sale, Service, User, Target, TargetParticipant};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * عرض تقرير شامل للفرع
     */
     public function branchReport($branchId)
    {
        $user = Auth::user();
        if ($user->type === 'employee' && $user->branch_id != $branchId) {
            return response()->json(['message'=>'غير مصرح لك'],403);
        }

        $sales = Sale::with('employee', 'service')
            ->when($user->type === 'employee', fn($q) => $q->where('employee_id',$user->id))
            ->when($user->type === 'branch_manager', fn($q) => $q->whereHas('employee', fn($q2) => $q2->where('branch_id',$user->branch_id)))
            ->get();

        $totalSales = $sales->count() ?: 1;
        $totalRevenue = $sales->sum(fn($s) => $s->price * $s->quantity);

        $services = $sales->groupBy('service_id')->map(fn($s)=>[
            'service_name' => $s->first()->service->name ?? '-',
            'count' => $s->sum('quantity'),
            'percentage' => round(($s->sum('quantity')/$totalSales)*100,2)
        ])->values();

        $employees = $sales->groupBy('employee_id')->map(fn($s)=>[
            'employee_name' => $s->first()->employee->fname.' '.$s->first()->employee->lname,
            'sales_count' => $s->count(),
            'percentage' => round(($s->count()/$totalSales)*100,2),
            'total_revenue' => $s->sum(fn($x) => $x->price * $x->quantity)
        ])->values();

        $targets = TargetParticipant::with('employee','target')
            ->when($user->type === 'employee', fn($q)=>$q->where('employee_id',$user->id))
            ->when($user->type === 'branch_manager', fn($q)=>$q->whereHas('employee',fn($q2)=>$q2->where('branch_id',$user->branch_id)))
            ->get()
            ->map(fn($tp)=>[
                'employee' => $tp->employee->fname.' '.$tp->employee->lname,
                'target_title' => $tp->target->title ?? '-',
                'progress' => $tp->progress,
                'status' => $tp->status
            ]);

        return response()->json([
            'total_sales'=>$totalSales,
            'total_revenue'=>$totalRevenue,
            'services'=>$services,
            'employees'=>$employees,
            'targets_progress'=>$targets
        ]);
    }

    /**
     * تصدير تقرير CSV
     */
    public function exportCsv($branchId)
    {
        $report = $this->getBranchReportData($branchId);

        $filename = "branch-report-$branchId.csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Employee', 'Sales Count', 'Percentage', 'Total Revenue', 'Target Progress', 'Target Status'];

        $callback = function() use ($report, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($report['employees'] as $emp) {
                $target = collect($report['targets_progress'])->firstWhere('employee', $emp['employee_name']);
                fputcsv($file, [
                    $emp['employee_name'],
                    $emp['sales_count'],
                    $emp['percentage'] . '%',
                    $emp['total_revenue'],
                    $target['progress'] ?? 0,
                    $target['status'] ?? '-'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * تصدير تقرير PDF (بدون View)
     */
    public function exportPdf($branchId)
    {
        $report = $this->getBranchReportData($branchId);

        $html = "<h2>تقرير الفرع</h2>
        <p>إجمالى المبيعات: {$report['total_sales']}</p>
        <p>إجمالى الإيرادات: {$report['total_revenue']}</p><br>
        <h3>الخدمات</h3><ul>";

        foreach ($report['services'] as $service) {
            $html .= "<li>{$service['service_name']}: {$service['count']} عملية ({$service['percentage']}%)</li>";
        }

        $html .= "</ul><h3>الموظفون</h3><ul>";
        foreach ($report['employees'] as $emp) {
            $html .= "<li>{$emp['employee_name']} - {$emp['sales_count']} مبيعات ({$emp['percentage']}%) - الإيراد: {$emp['total_revenue']}</li>";
        }
        $html .= "</ul>";

        $pdf = Pdf::loadHTML($html);
        return $pdf->download("branch-report-$branchId.pdf");
    }

    /**
     * دالة مشتركة لاسترجاع بيانات التقرير (للاستخدام الداخلى)
     */
    private function getBranchReportData($branchId)
    {
        $sales = Sale::with('employee', 'service')
            ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
            ->get();

        $totalSales = $sales->count() ?: 1;
        $totalRevenue = $sales->sum(fn($s) => $s->price * $s->quantity);

        $services = $sales->groupBy('service_id')->map(fn($s) => [
            'service_name' => $s->first()->service->name ?? 'غير معروف',
            'count' => $s->count(),
            'percentage' => round(($s->count() / $totalSales) * 100, 2)
        ])->values();

        $employees = $sales->groupBy('employee_id')->map(fn($s) => [
            'employee_name' => $s->first()->employee->fname . ' ' . $s->first()->employee->lname,
            'sales_count' => $s->count(),
            'percentage' => round(($s->count() / $totalSales) * 100, 2),
            'total_revenue' => $s->sum(fn($x) => $x->price * $x->quantity)
        ])->values();

        $targets = TargetParticipant::with('employee', 'target')
            ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
            ->get()
            ->map(fn($tp) => [
                'employee' => $tp->employee->fname . ' ' . $tp->employee->lname,
                'target_title' => $tp->target->title ?? '-',
                'progress' => $tp->progress,
                'status' => $tp->status
            ]);

        return [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'services' => $services,
            'employees' => $employees,
            'targets_progress' => $targets
        ];
    }
}
