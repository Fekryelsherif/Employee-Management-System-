<?php

namespace App\Http\Controllers\Api\BranchManager;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Target;
use App\Models\User;
use Illuminate\Http\Request;

class BranchDashboardController extends Controller
{
    /**
     * 📊 1️⃣ ملخص الفرع (عدد الموظفين - عدد المبيعات - نسبة الإنجاز)
     */
   public function branchSummary(Request $request)
{
    $manager = $request->user();

    if ($manager->type !== 'branch-manager') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    $branchId = $manager->branch_id;

    // عدد الموظفين في الفرع
    $totalEmployees = User::where('branch_id', $branchId)
        ->where('type', 'employee')
        ->count();

    // عدد العمليات (المبيعات)
    $totalSales = Sale::where('branch_id', $branchId)->count();

    // إجمالي التارجت من جدول targets
    $totalTarget = \App\Models\Target::where('branch_id', $branchId)->sum('target_services');

    // مجموع الخدمات اللي اتباعَت (achieved_services)
    $totalAchieved = DB::table('target_participant_services')
        ->join('target_participants', 'target_participant_services.target_participant_id', '=', 'target_participants.id')
        ->join('users', 'target_participants.employee_id', '=', 'users.id')
        ->where('users.branch_id', $branchId)
        ->sum('target_participant_services.sold');

    // متوسط أو إجمالي نسبة التقدم (progress_percent)
    $progressSum = DB::table('target_participants')
        ->join('users', 'target_participants.employee_id', '=', 'users.id')
        ->where('users.branch_id', $branchId)
        ->sum('target_participants.progress');

    $employeesWithProgress = DB::table('target_participants')
        ->join('users', 'target_participants.employee_id', '=', 'users.id')
        ->where('users.branch_id', $branchId)
        ->distinct('target_participants.employee_id')
        ->count('target_participants.employee_id');

    // نحسب المتوسط (لو مفيش بيانات يبقى صفر)
    $progressPercent = $employeesWithProgress > 0
        ? round($progressSum / $employeesWithProgress, 2)
        : 0;

    return response()->json([
        'branch_id' => $branchId,
        'branch_name' => $manager->branch->name ?? '',
        'city' => $manager->branch->city->name ?? '',
        'total_employees' => $totalEmployees,
        'total_sales_operations' => $totalSales,
        'target_services' => $totalTarget,
        'achieved_services' => $totalAchieved,
        'progress_percent' => $progressPercent,
    ]);
}


    /**
     * 👥 2️⃣ تفاصيل الموظفين داخل الفرع
     * (الراتب - العمولة - التارجت - التحدى - عدد العمليات)
     */
   public function branchEmployeesDetails(Request $request)
{
    $manager = $request->user();

    if ($manager->type !== 'branch-manager') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    $employees = User::where('branch_id', $manager->branch_id)
        ->where('type', 'employee')
        ->get()
        ->map(function ($emp) {
            // 🔹 إجمالي الخدمات المباعة من جدول target_participant_services
            $servicesSold = DB::table('target_participant_services')
                ->join('target_participants', 'target_participant_services.target_participant_id', '=', 'target_participants.id')
                ->where('target_participants.employee_id', $emp->id)
                ->sum('target_participant_services.sold');

            // 🔹 إجمالي النسبة المئوية من جدول target_participants
            $progressPercent = DB::table('target_participants')
                ->where('employee_id', $emp->id)
                ->avg('progress') ?? 0;

            // 🔹 إجمالي الخدمات المستهدفة من جدول targets الخاصة بفرع الموظف
            $totalAssignedServices = DB::table('targets')
                ->where('branch_id', $emp->branch_id)
                ->sum('target_services');

            // 🔹 هل الموظف مشارك في تحديات
            $isInChallenge = $emp->challenges()->exists();

            // 🔹 عدد المبيعات الخاصة بالموظف
            $salesCount = \App\Models\Sale::where('employee_id', $emp->id)->count();

            return [
                'employee_id' => $emp->id,
                'employee_name' => trim(($emp->fname ?? '') . ' ' . ($emp->lname ?? '')),
                'salary' => $emp->salary ?? 0,
                'commission_salary' => $emp->commission_salary ?? 0,
                'services_sold' => (int) $servicesSold,
                'target_assigned_services' => (int) $totalAssignedServices,
                'progress_percent' => round($progressPercent, 2),
                'is_in_challenge' => (bool) $isInChallenge,
                'sales_count' => $salesCount,
            ];
        });

    return response()->json(['employees' => $employees]);
}


    /**
     * 💰 3️⃣ مبيعات الموظفين داخل الفرع
     * (اسم الموظف - العميل - الخدمة - الكمية - السعر - التاريخ)
     */
    public function branchEmployeesSales(Request $request)
    {
        $manager = $request->user();

        if ($manager->type !== 'branch-manager') {
            return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
        }

        $sales = Sale::with(['employee', 'client', 'items.service'])
            ->where('branch_id', $manager->branch_id)
            ->latest()
            ->get()
            ->flatMap(function ($sale) {
                return $sale->items->map(function ($item) use ($sale) {
                    return [
                        'employee_name' => trim(($sale->employee->fname ?? '') . ' ' . ($sale->employee->lname ?? '')),
                        'client_name' => $sale->client->name ?? 'غير محدد',
                        'service_name' => $item->service->name ?? '',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->quantity * $item->price,
                        'sale_date' => $sale->created_at->format('Y-m-d H:i'),
                        'total_commission' => $sale->total_commission,
                        'salary'=>$sale->employee->salary,
                        'commission_salary'=>$sale->employee->commission_salary,
                    ];
                });
            });

        return response()->json(['sales' => $sales]);
    }


    public function exportBranchDashboardCsv(Request $request)
{
    $manager = $request->user();

    if ($manager->type !== 'branch-manager') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    // البيانات من الدوال
    $summary = $this->branchSummary($request)->getData();
    $employees = $this->branchEmployeesDetails($request)->getData()->employees;
    $sales = $this->branchEmployeesSales($request)->getData()->sales;

    $csvData = "📊 Branch Summary\n";
    $csvData .= "Branch ID,Branch Name,City,Total Employees,Total Sales,Target Services,Achieved Services,Progress %\n";
    $csvData .= "{$summary->branch_id},{$summary->branch_name},{$summary->city},{$summary->total_employees},{$summary->total_sales_operations},{$summary->target_services},{$summary->achieved_services},{$summary->progress_percent}\n\n";

    $csvData .= "👥 Employees Details\n";
    $csvData .= "Employee ID,Employee Name,Salary,Commission Salary,Services Sold,Target Assigned,Progress %,In Challenge,Sales Count\n";
    foreach ($employees as $e) {
        $csvData .= "{$e->employee_id},{$e->employee_name},{$e->salary},{$e->commission_salary},{$e->services_sold},{$e->target_assigned_services},{$e->progress_percent}," . ($e->is_in_challenge ? 'Yes' : 'No') . ",{$e->sales_count}\n";
    }

    $csvData .= "\n💰 Employees Sales\n";
    $csvData .= "Employee Name,Client Name,Service Name,Quantity,Price,Total,Sale Date,Total Commission,Salary,Commission Salary\n";
    foreach ($sales as $s) {
        $csvData .= "{$s->employee_name},{$s->client_name},{$s->service_name},{$s->quantity},{$s->price},{$s->total},{$s->sale_date},{$s->total_commission},{$s->salary},{$s->commission_salary}\n";
    }

    return response($csvData, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="branch_dashboard.csv"',
    ]);
}


public function exportBranchDashboardPdf(Request $request)
{
    $manager = $request->user();

    if ($manager->type !== 'branch-manager') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    $summary = $this->branchSummary($request)->getData();
    $employees = $this->branchEmployeesDetails($request)->getData()->employees;
    $sales = $this->branchEmployeesSales($request)->getData()->sales;

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
        <h2>لوحة تحكم مدير الفرع</h2>

        <h3>📊 ملخص الفرع</h3>
        <table>
            <tr><th>اسم الفرع</th><td>'. $summary->branch_name .'</td></tr>
            <tr><th>المدينة</th><td>'. $summary->city .'</td></tr>
            <tr><th>عدد الموظفين</th><td>'. $summary->total_employees .'</td></tr>
            <tr><th>عدد العمليات</th><td>'. $summary->total_sales_operations .'</td></tr>
            <tr><th>إجمالي التارجت</th><td>'. $summary->target_services .'</td></tr>
            <tr><th>الخدمات المحققة</th><td>'. $summary->achieved_services .'</td></tr>
            <tr><th>نسبة الإنجاز</th><td>'. $summary->progress_percent .'%</td></tr>
        </table>

        <h3>👥 تفاصيل الموظفين</h3>
        <table>
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>الراتب</th>
                    <th>العمولة</th>
                    <th>الخدمات المباعة</th>
                    <th>التارجت</th>
                    <th>الإنجاز %</th>
                    <th>تحدي؟</th>
                    <th>عدد المبيعات</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($employees as $e) {
        $html .= '<tr>
            <td>'. $e->employee_name .'</td>
            <td>'. $e->salary .'</td>
            <td>'. $e->commission_salary .'</td>
            <td>'. $e->services_sold .'</td>
            <td>'. $e->target_assigned_services .'</td>
            <td>'. $e->progress_percent .'%</td>
            <td>'. ($e->is_in_challenge ? 'نعم' : 'لا') .'</td>
            <td>'. $e->sales_count .'</td>
        </tr>';
    }

    $html .= '</tbody></table>

        <h3>💰 مبيعات الموظفين</h3>
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
                    <th>العمولة الكلية</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($sales as $s) {
        $html .= '<tr>
            <td>'. $s->employee_name .'</td>
            <td>'. $s->client_name .'</td>
            <td>'. $s->service_name .'</td>
            <td>'. $s->quantity .'</td>
            <td>'. $s->price .'</td>
            <td>'. $s->total .'</td>
            <td>'. $s->sale_date .'</td>
            <td>'. $s->total_commission .'</td>
        </tr>';
    }

    $html .= '</tbody></table></body></html>';

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');
    return $pdf->stream('branch_dashboard.pdf');
}

}