<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\TargetParticipantService;
use App\Models\{Branch, User, Target, Sale, SaleItem, Service};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegionStatsController extends Controller
{
    // الخدمات اللي عايز تحسب لها أعداد منفردة (تقدر تعدل المصفوفة دي)
    private $servicesToCount = ['DCL', 'H4C', 'بونيت', 'اورنج كاش', 'اجهزة'];

    /**
     * ملخص الفروع: اسم الفرع، المدينة، عدد الموظفين، إجمالي target_services
     * وعدد كل خدمة من servicesToCount ونسبة التقدم لكل فرع.
     */
   public function branchesSummary()
{
    $branches = Branch::with(['city', 'users' => function($q) {
        $q->where('type', 'employee')->with('targetParticipants.services');
    }])->get();

    $summary = $branches->map(function ($branch) {
        $branchTargets = Target::where('branch_id', $branch->id)
            ->where('status', 'active')
            ->with('participants.services')
            ->get();

        $totalTargetServices = 0;
        $achievedServices = 0;
        $progressPercent = 0;

        if ($branchTargets->count() > 0) {
            foreach ($branchTargets as $target) {
                foreach ($target->participants as $participant) {
                    $totalTargetServices += $participant->services->sum('target_quantity');
                    $achievedServices += $participant->services->sum('sold');
                }
            }

            if ($totalTargetServices > 0) {
                $progressPercent = round(($achievedServices / $totalTargetServices) * 100, 2);
            }
        }

        return [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'city_name' => $branch->city->name ?? null,
            'employees_count' => $branch->users->count(),
            'target_services' => $totalTargetServices,
            'achieved_services' => $achievedServices,
            'progress_percent' => $progressPercent,
        ];
    });

    return response()->json([
        'branches_summary' => $summary,
    ]);
}


    /**
     * قائمة الموظفين لكل فرع: لكل موظف عدد العمليات، عدد الخدمات المباعة، ونسبة تقدمه
     */
 public function branchEmployees($branchId)
{
    // جلب الموظفين فقط من الفرع
    $employees = User::where('branch_id', $branchId)
        ->where('type', 'employee')
        ->with([
            'targetParticipants.services',
            'challenges',
        ])
        ->get();

    $result = $employees->map(function($emp) {
        // جلب أحدث مشاركة في تارجت
        $participant = $emp->targetParticipants->sortByDesc('created_at')->first();

        $servicesSold = 0;
        $progressPercent = 0;
        $totalAssignedServices = 0;
        $hasSubTarget = false;

        if ($participant) {
            $servicesSold = $participant->services->sum('sold');
            $progressPercent = $participant->progress ?? 0;
            $totalAssignedServices = $participant->services->sum('target_quantity');
            $hasSubTarget = $participant->services->count() > 0;
        }

        // هل الموظف مشارك في أي تحدي؟
        $isInChallenge = $emp->challenges && $emp->challenges->count() > 0;

        return [
            'employee_id' => $emp->id,
            'employee_name' => trim($emp->fname . ' ' . $emp->lname),
            'salary' => $emp->salary ?? 0,
            'commission_salary' => $emp->commission_salary ?? 0,
            'services_sold' => $servicesSold,
            'total_assigned_services' => $totalAssignedServices,
            'progress_percent' => $progressPercent,
            'has_sub_target' => $hasSubTarget,
            'is_in_challenge' => $isInChallenge,
        ];
    });

    return response()->json([
        'branch_id' => $branchId,
        'employees' => $result,
    ]);
}


public function branchSales($branchId)
{
    // جلب الموظفين في الفرع
    $employeeIds = User::where('branch_id', $branchId)
        ->where('type', 'employee')
        ->pluck('id');

    // جلب المبيعات لكل الموظفين مع العميل والخدمات
    $sales = Sale::with(['client', 'items.service', 'employee'])
        ->whereIn('employee_id', $employeeIds)
        ->get();

    $result = [];

    foreach ($sales as $sale) {
        foreach ($sale->items as $item) {
            $result[] = [
                'employee_name' => trim($sale->employee->fname ?? '-') . ' ' . trim($sale->employee->lname ?? ''),
                'client_name'   => trim($sale->client->name ?? '-'),
                'service_name'  => trim($item->service->name ?? '-'),
                'quantity'      => $item->quantity,
                'unit_price'    => $item->price,
                'total'         => $item->total,
                'sold_at'       => $sale->created_at->format('Y-m-d H:i'),
            ];
        }
    }

    return response()->json($result);
}







//________________________________________________________________


 public function exportRegionDashboardCsv(Request $request)
{
    $manager = $request->user();

    if ($manager->type !== 'region-manager') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    // البيانات من الدوال الثلاثة
    $branchesSummary = $this->branchesSummary()->getData()->branches_summary ?? [];
    $csvData = "📊 Branches Summary\n";
    $csvData .= "Branch ID,Branch Name,City,Employees Count,Target Services,Achieved Services,Progress %\n";

    foreach ($branchesSummary as $branch) {
        $csvData .= "{$branch->branch_id},{$branch->branch_name},{$branch->city_name},{$branch->employees_count},{$branch->target_services},{$branch->achieved_services},{$branch->progress_percent}\n";
    }

    $csvData .= "\n👥 Employees Details Per Branch\n";
    $csvData .= "Branch ID,Employee ID,Employee Name,Salary,Commission Salary,Services Sold,Assigned Services,Progress %,Has Sub Target,In Challenge\n";

    foreach ($branchesSummary as $branch) {
        $employees = $this->branchEmployees($branch->branch_id)->getData()->employees ?? [];
        foreach ($employees as $e) {
            $csvData .= "{$branch->branch_id},{$e->employee_id},{$e->employee_name},{$e->salary},{$e->commission_salary},{$e->services_sold},{$e->total_assigned_services},{$e->progress_percent}," . ($e->has_sub_target ? 'Yes' : 'No') . "," . ($e->is_in_challenge ? 'Yes' : 'No') . "\n";
        }
    }

    $csvData .= "\n💰 Branch Sales Details\n";
    $csvData .= "Branch ID,Employee Name,Client Name,Service Name,Quantity,Unit Price,Total,Sold At\n";

    foreach ($branchesSummary as $branch) {
        $sales = $this->branchSales($branch->branch_id)->getData() ?? [];
        foreach ($sales as $s) {
            $csvData .= "{$branch->branch_id},{$s->employee_name},{$s->client_name},{$s->service_name},{$s->quantity},{$s->unit_price},{$s->total},{$s->sold_at}\n";
        }
    }

    return response($csvData, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename=\"region_dashboard.csv\"',
    ]);
}


public function exportRegionDashboardPdf(Request $request)
{
    $manager = $request->user();

    if ($manager->type !== 'region-manager') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    $branchesSummary = $this->branchesSummary()->getData()->branches_summary ?? [];

    $html = '<html dir="rtl" lang="ar">
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; text-align: right; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #000; padding: 6px; }
            th { background-color: #f2f2f2; }
            h2, h3 { text-align: center; margin-top: 20px; }
        </style>
    </head>
    <body>
        <h2>📊 لوحة تحكم مدير المنطقة</h2>
        <h3>ملخص الفروع</h3>
        <table>
            <thead>
                <tr>
                    <th>الفرع</th>
                    <th>المدينة</th>
                    <th>عدد الموظفين</th>
                    <th>إجمالي التارجت</th>
                    <th>الخدمات المحققة</th>
                    <th>نسبة الإنجاز</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($branchesSummary as $branch) {
        $html .= '<tr>
            <td>' . $branch->branch_name . '</td>
            <td>' . $branch->city_name . '</td>
            <td>' . $branch->employees_count . '</td>
            <td>' . $branch->target_services . '</td>
            <td>' . $branch->achieved_services . '</td>
            <td>' . $branch->progress_percent . '%</td>
        </tr>';
    }

    $html .= '</tbody></table>';

    // تفاصيل الموظفين والمبيعات لكل فرع
    foreach ($branchesSummary as $branch) {
        $employees = $this->branchEmployees($branch->branch_id)->getData()->employees ?? [];
        $sales = $this->branchSales($branch->branch_id)->getData() ?? [];

        $html .= '<h3>👥 موظفي الفرع: ' . $branch->branch_name . '</h3>
        <table>
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>الراتب</th>
                    <th>العمولة</th>
                    <th>الخدمات المباعة</th>
                    <th>الخدمات المحددة</th>
                    <th>الإنجاز %</th>
                    <th>تارجت فرعي؟</th>
                    <th>تحدي؟</th>
                </tr>
            </thead><tbody>';

        foreach ($employees as $e) {
            $html .= '<tr>
                <td>' . $e->employee_name . '</td>
                <td>' . $e->salary . '</td>
                <td>' . $e->commission_salary . '</td>
                <td>' . $e->services_sold . '</td>
                <td>' . $e->total_assigned_services . '</td>
                <td>' . $e->progress_percent . '%</td>
                <td>' . ($e->has_sub_target ? 'نعم' : 'لا') . '</td>
                <td>' . ($e->is_in_challenge ? 'نعم' : 'لا') . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<h3>💰 مبيعات الفرع: ' . $branch->branch_name . '</h3>
        <table>
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>العميل</th>
                    <th>الخدمة</th>
                    <th>الكمية</th>
                    <th>السعر</th>
                    <th>الإجمالي</th>
                    <th>تاريخ البيع</th>
                </tr>
            </thead><tbody>';

        foreach ($sales as $s) {
            $html .= '<tr>
                <td>' . $s->employee_name . '</td>
                <td>' . $s->client_name . '</td>
                <td>' . $s->service_name . '</td>
                <td>' . $s->quantity . '</td>
                <td>' . $s->unit_price . '</td>
                <td>' . $s->total . '</td>
                <td>' . $s->sold_at . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
    }

    $html .= '</body></html>';

    $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
    return $pdf->stream('region_dashboard.pdf');
}


}

    /**
     * Export summary as CSV (all branches of this region manager)
     */
