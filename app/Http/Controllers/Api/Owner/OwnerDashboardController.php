<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Target;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class OwnerDashboardController extends Controller
{
    public function allBranchesSummaryForOwner()
{
    // كل الفروع مع المدينة والمديرين
    $branches = Branch::with(['city', 'regionManager', 'users' => function($q) {
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
            'region_manager' => $branch->regionManager ? trim($branch->regionManager->fname . ' ' . $branch->regionManager->lname) : '-',
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



public function allBranchEmployeesForOwner()
{
    // جلب كل مديري المناطق
    $regionManagers = User::where('type', 'region-manager')
        ->with([
            'managedBranches.city',
            // لجلب الموظفين بداخل كل فرع عند الحاجة سنعتمد على Branch->users
        ])->get();

    $regions = $regionManagers->map(function($rm) {
        $branches = $rm->managedBranches->map(function($branch) use ($rm) {
            // جلب موظفين الفرع (نوع employee)
            $employees = $branch->users()->where('type', 'employee')
                ->with(['targetParticipants.services', 'challenges'])
                ->get()
                ->map(function($emp) {
                    // أحدث مشاركة للتارجت
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

                    $isInChallenge = $emp->challenges && $emp->challenges->count() > 0;

                    return [
                        'employee_id' => $emp->id,
                        'employee_name' => trim(($emp->fname ?? '') . ' ' . ($emp->lname ?? '')),
                        'salary' => $emp->salary ?? '0.00',
                        'commission_salary' => $emp->commission_salary ?? '0.00',
                        'services_sold' => (int) $servicesSold,
                        'total_assigned_services' => (int) $totalAssignedServices,
                        'progress_percent' => (float) $progressPercent,
                        'has_sub_target' => (bool) $hasSubTarget,
                        'is_in_challenge' => (bool) $isInChallenge,
                    ];
                });

            // حساب إجماليات التارجت الخاصة بالفرع و المدير الحالي (region manager = $rm)
            $targetServicesTotal = Target::where('branch_id', $branch->id)
                ->where('region_manager_id', $rm->id)
                ->sum('target_services');

            $achieved = Target::where('branch_id', $branch->id)
                ->where('region_manager_id', $rm->id)
                ->sum('achieved_services');

            $progress = $targetServicesTotal > 0 ? round(($achieved / $targetServicesTotal) * 100, 2) : 0;

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name ?? '',
                'city_name' => $branch->city->name ?? '',
                'employees_count' => $branch->users()->where('type','employee')->count(),
                'target_services' => (int) $targetServicesTotal,
                'achieved_services' => (int) $achieved,
                'progress_percent' => (float) $progress,
                'employees' => $employees,
            ];
        })->values(); // ensure array-like indexes

        return [
            'region_id' => $rm->id,
            // لو عندك حقل اسم المنطقة في user مثلاً region_name استخدمه، وإلا استخدم اسم المدير كرابط للمنطقة
            'region_name' => $rm->region_name ?? trim(($rm->fname ?? '') . ' ' . ($rm->lname ?? '')),
            'region_manager_id' => $rm->id,
            'region_manager_name' => trim(($rm->fname ?? '') . ' ' . ($rm->lname ?? '')),
            'branches' => $branches,
        ];
    })->values();

    return response()->json(['regions' => $regions]);
}



public function allBranchSalesForOwner()
{
    $branches = Branch::with(['users' => function($q) {
        $q->where('type', 'employee');
    }])->get();

    $result = [];

    foreach ($branches as $branch) {
        $employeeIds = $branch->users->pluck('id');

        $sales = Sale::with(['client', 'items.service', 'employee'])
            ->whereIn('employee_id', $employeeIds)
            ->get();

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $result[] = [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'employee_name' => trim($sale->employee->fname ?? '-') . ' ' . trim($sale->employee->lname ?? ''),
                    'client_name' => trim($sale->client->name ?? '-'),
                    'service_name' => trim($item->service->name ?? '-'),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'total' => $item->total,
                    'sold_at' => $sale->created_at->format('Y-m-d H:i'),
                ];
            }
        }
    }

    return response()->json($result);
}








//_____________________________________________________________


public function exportOwnerDashboardCsv(Request $request)
{
    $user = $request->user();

    if ($user->type !== 'owner') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    // استدعاء الدوال الثلاثة
    $branchesSummary = $this->allBranchesSummaryForOwner()->getData()->branches_summary ?? [];
    $regions = $this->allBranchEmployeesForOwner()->getData()->regions ?? [];
    $salesData = $this->allBranchSalesForOwner()->getData() ?? [];

    $csv = "🏢 Branches Summary\n";
    $csv .= "Branch ID,Branch Name,City,Region Manager,Employees Count,Target Services,Achieved Services,Progress %\n";
    foreach ($branchesSummary as $branch) {
        $csv .= "{$branch->branch_id},{$branch->branch_name},{$branch->city_name},{$branch->region_manager},{$branch->employees_count},{$branch->target_services},{$branch->achieved_services},{$branch->progress_percent}\n";
    }

    $csv .= "\n👥 Employees Per Region\n";
    $csv .= "Region ID,Region Name,Branch ID,Branch Name,Employee ID,Employee Name,Salary,Commission Salary,Services Sold,Assigned Services,Progress %,Has Sub Target,In Challenge\n";

    foreach ($regions as $region) {
        foreach ($region->branches as $branch) {
            foreach ($branch->employees as $e) {
                $csv .= "{$region->region_id},{$region->region_name},{$branch->branch_id},{$branch->branch_name},{$e->employee_id},{$e->employee_name},{$e->salary},{$e->commission_salary},{$e->services_sold},{$e->total_assigned_services},{$e->progress_percent}," . ($e->has_sub_target ? 'Yes' : 'No') . "," . ($e->is_in_challenge ? 'Yes' : 'No') . "\n";
            }
        }
    }

    $csv .= "\n💰 All Branch Sales\n";
    $csv .= "Branch ID,Branch Name,Employee Name,Client Name,Service Name,Quantity,Unit Price,Total,Sold At\n";

    foreach ($salesData as $s) {
        $csv .= "{$s->branch_id},{$s->branch_name},{$s->employee_name},{$s->client_name},{$s->service_name},{$s->quantity},{$s->unit_price},{$s->total},{$s->sold_at}\n";
    }

    return response($csv, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="owner_dashboard.csv"',
    ]);
}


public function exportOwnerDashboardPdf(Request $request)
{
    $user = $request->user();

    if ($user->type !== 'owner') {
        return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
    }

    $branchesSummary = $this->allBranchesSummaryForOwner()->getData()->branches_summary ?? [];
    $regions = $this->allBranchEmployeesForOwner()->getData()->regions ?? [];
    $salesData = $this->allBranchSalesForOwner()->getData() ?? [];

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
        <h2>🏢 لوحة تحكم المالك</h2>

        <h3>📊 ملخص الفروع</h3>
        <table>
            <thead>
                <tr>
                    <th>الفرع</th>
                    <th>المدينة</th>
                    <th>مدير المنطقة</th>
                    <th>عدد الموظفين</th>
                    <th>التارجت</th>
                    <th>المحقق</th>
                    <th>نسبة الإنجاز</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($branchesSummary as $b) {
        $html .= '<tr>
            <td>' . $b->branch_name . '</td>
            <td>' . $b->city_name . '</td>
            <td>' . $b->region_manager . '</td>
            <td>' . $b->employees_count . '</td>
            <td>' . $b->target_services . '</td>
            <td>' . $b->achieved_services . '</td>
            <td>' . $b->progress_percent . '%</td>
        </tr>';
    }

    $html .= '</tbody></table>';

    foreach ($regions as $region) {
        $html .= '<h3>👥 المنطقة: ' . $region->region_name . '</h3>';

        foreach ($region->branches as $branch) {
            $html .= '<h4>فرع: ' . $branch->branch_name . ' (' . $branch->city_name . ')</h4>';
            $html .= '<table>
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>الراتب</th>
                        <th>العمولة</th>
                        <th>المباعة</th>
                        <th>المكلفة</th>
                        <th>الإنجاز %</th>
                        <th>تارجت فرعي؟</th>
                        <th>تحدي؟</th>
                    </tr>
                </thead><tbody>';
            foreach ($branch->employees as $e) {
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
        }
    }

    $html .= '<h3>💰 جميع المبيعات</h3>
    <table>
        <thead>
            <tr>
                <th>الفرع</th>
                <th>الموظف</th>
                <th>العميل</th>
                <th>الخدمة</th>
                <th>الكمية</th>
                <th>السعر</th>
                <th>الإجمالي</th>
                <th>تاريخ البيع</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($salesData as $s) {
        $html .= '<tr>
            <td>' . $s->branch_name . '</td>
            <td>' . $s->employee_name . '</td>
            <td>' . $s->client_name . '</td>
            <td>' . $s->service_name . '</td>
            <td>' . $s->quantity . '</td>
            <td>' . $s->unit_price . '</td>
            <td>' . $s->total . '</td>
            <td>' . $s->sold_at . '</td>
        </tr>';
    }

    $html .= '</tbody></table></body></html>';

    $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
    return $pdf->stream('owner_dashboard.pdf');
}

}