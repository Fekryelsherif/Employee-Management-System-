<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Target;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class EmployeeDashboardController extends Controller
{
    /**
     * 1️⃣ عرض ملخص الموظف الأساسي
     */
    public function employeeOverview(Request $request)
    {
        $employee = $request->user();

        if ($employee->type !== 'employee') {
            return response()->json(['error' => 'غير مصرح لك بالوصول'], 403);
        }

        // الفرع والمنطقة
        $branch = $employee->branch;
        $city = $branch->city ?? null;

        // التارجت الأساسي
        $mainTarget = DB::table('targets')
            ->where('branch_id', $employee->branch_id)
            ->whereNull('recreated_from_target_id')
            ->first();

        // التارجت الإضافي
        $subTarget = DB::table('targets')
            ->where('branch_id', $employee->branch_id)
            ->whereNotNull('recreated_from_target_id')
            ->where('recreated_goal_amount', '>', 0)
            ->first();

        // التحدي (لو الموظف مشارك)
        $challenge = DB::table('challenge_participants')
            ->join('challenges', 'challenge_participants.challenge_id', '=', 'challenges.id')
            ->where('challenge_participants.employee_id', $employee->id)
            //->select('challenges.title', 'challenges.goal', 'challenges.reward')
            ->first();

        return response()->json([
            'employee_name' => trim(($employee->fname ?? '') . ' ' . ($employee->lname ?? '')),
            'branch_name' => $branch->name ?? '',
            'city_name' => $city->name ?? '',
            'salary' => $employee->salary ?? 0,
            'commission_salary' => $employee->commission_salary ?? 0,
            'main_target_value' => $mainTarget->target_services ?? 0,
            'sub_target_value' => $subTarget->recreated_goal_amount ?? 0,
            'challenge' => $challenge ?? null,
        ]);
    }

    /**
     * 2️⃣ تفاصيل التارجت (رئيسي + إضافي + تحدي)
     */
    public function employeePerformanceDetails(Request $request)
    {
        $employee = $request->user();

        $main = DB::table('target_participants')
            ->join('targets', 'target_participants.target_id', '=', 'targets.id')
            ->where('target_participants.employee_id', $employee->id)
            ->whereNull('targets.recreated_from_target_id')
            ->select('targets.target_services', 'target_participants.progress')
            ->first();

        $mainSold = DB::table('target_participant_services')
            ->join('target_participants', 'target_participant_services.target_participant_id', '=', 'target_participants.id')
            ->where('target_participants.employee_id', $employee->id)
            ->sum('target_participant_services.sold');

        $sub = DB::table('targets')
            ->whereNotNull('recreated_from_target_id')
            ->where('branch_id', $employee->branch_id)
            ->first();

        $challenge = DB::table('challenge_participants')
            ->where('employee_id', $employee->id)
            ->join('challenges', 'challenge_participants.challenge_id', '=', 'challenges.id')
            //->select('challenges.goal', 'challenge_participants.progress')
            ->first();

            // $mainTarget = Target::where('employee_id', $employee->id)
            // ->whereNull('recreated_from_target_id') // ناخد التارجت الرئيسي مش الفرعي
            // ->where('status', 'active')
            //  ->first();

            //  $assigned = $mainTarget->target_services ?? 0;


        return response()->json([
            'main_target' => [
                //'assigned' => $assigned,
                'sold' => $mainSold,
                'progress' => round($main->progress ?? 0, 2),
                'remaining' => max(($main->target_services ?? 0) - $mainSold, 0),
            ],
            'sub_target' => [
                'goal_amount' => $sub->recreated_goal_amount ?? 0,
                'progress' => $sub->progress ?? 0,
            ],
            'challenge' => $challenge ?? null,
        ]);
    }

    /**
     * 3️⃣ أداء الموظف في الخدمات (أسماء الخدمات وعددها ونسبتها)
     */
    public function employeeServicePerformance(Request $request)
    {
        $employee = $request->user();

        $services = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('services', 'sale_items.service_id', '=', 'services.id')
            ->where('sales.employee_id', $employee->id)
            ->select('services.name', DB::raw('SUM(sale_items.quantity) as total_quantity'))
            ->groupBy('services.name')
            ->get();

        $totalSold = $services->sum('total_quantity');

        $services = $services->map(function ($s) use ($totalSold) {
            $s->percent = $totalSold > 0 ? round(($s->total_quantity / $totalSold) * 100, 2) : 0;
            return $s;
        });

        $topService = $services->sortByDesc('total_quantity')->first();

        return response()->json([
            'services' => $services,
            'top_service' => $topService,
            'total_sold' => $totalSold,
        ]);
    }

    /**
     * 4️⃣ تفاصيل المبيعات للموظف
     */
    public function employeeSalesDetails(Request $request)
    {
        $employee = $request->user();

        $sales = Sale::with(['client', 'items.service'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->get()
            ->flatMap(function ($sale) {
                return $sale->items->map(function ($item) use ($sale) {
                    return [
                        'client_name' => $sale->client->name ?? 'غير محدد',
                        'service_name' => $item->service->name ?? '',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->quantity * $item->price,
                        'sale_date' => $sale->created_at->format('Y-m-d H:i'),
                    ];
                });
            });

        return response()->json(['sales' => $sales]);
    }




    public function exportEmployeeDashboardCsv(Request $request)
    {
        $employee = $request->user();

        $overview = (new EmployeeDashboardController)->employeeOverview($request)->getData();
        $performance = (new EmployeeDashboardController)->employeePerformanceDetails($request)->getData();
        $services = (new EmployeeDashboardController)->employeeServicePerformance($request)->getData();
        $sales = (new EmployeeDashboardController)->employeeSalesDetails($request)->getData();

        $csv = "Section,Key,Value\n";

        $convert = function($value) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            return $value;
        };

        // Overview
        foreach ($overview as $key => $value) {
            $csv .= "Overview,$key," . $convert($value) . "\n";
        }

        // Performance
        foreach ($performance as $type => $data) {
            foreach ($data as $key => $value) {
                $csv .= "Performance [$type],$key," . $convert($value) . "\n";
            }
        }

        // Services
        foreach ($services->services ?? [] as $s) {
            $csv .= "Service," . $s->name . "," . $s->total_quantity . "," . $s->percent . "%\n";
        }
        $topServiceName = $services->top_service->name ?? '';
        $csv .= "Top Service,,{$topServiceName}\n";

        // Sales
        foreach ($sales->sales ?? [] as $sale) {
            foreach ($sale as $key => $value) {
                $csv .= "Sale,$key," . $convert($value) . "\n";
            }
        }

        return response()->make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee_dashboard.csv"',
        ]);
    }

    /**
     * تصدير بيانات الموظف بصيغة PDF منسقة وجميلة
     **/
    public function exportEmployeeDashboardPdf(Request $request)
    {
        $employee = $request->user();

        $overview = (new EmployeeDashboardController)->employeeOverview($request)->getData();
        $performance = (new EmployeeDashboardController)->employeePerformanceDetails($request)->getData();
        $services = (new EmployeeDashboardController)->employeeServicePerformance($request)->getData();
        $sales = (new EmployeeDashboardController)->employeeSalesDetails($request)->getData();

        $convert = function($value) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            return $value;
        };

        $html = '<html dir="rtl" lang="ar">
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; text-align: right; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #000; padding: 6px; }
                th { background-color: #f2f2f2; }
                h2 { text-align: center; margin-top: 20px; }
            </style>
        </head>
        <body>';

        // Overview
        $html .= '<h2>ملخص الموظف</h2><table>';
        foreach ($overview as $key => $value) {
            $html .= "<tr><th>$key</th><td>{$convert($value)}</td></tr>";
        }
        $html .= '</table>';

        // Performance
        $html .= '<h2>تفاصيل الأداء</h2><table>';
        foreach ($performance as $type => $data) {
            foreach ($data as $key => $value) {
                $html .= "<tr><th>{$type} - $key</th><td>{$convert($value)}</td></tr>";
            }
        }
        $html .= '</table>';

        // Services
        $html .= '<h2>أداء الخدمات</h2><table>
            <thead><tr><th>الخدمة</th><th>الكمية</th><th>النسبة %</th></tr></thead><tbody>';
        foreach ($services->services ?? [] as $s) {
            $html .= "<tr><td>{$s->name}</td><td>{$s->total_quantity}</td><td>{$s->percent}</td></tr>";
        }
        $topServiceName = $services->top_service->name ?? '';
        $html .= "<tr><th>أكثر خدمة مبيعاً</th><td colspan='2'>{$topServiceName}</td></tr>";
        $html .= '</tbody></table>';

        // Sales
        $html .= '<h2>تفاصيل المبيعات</h2><table>
            <thead><tr><th>العميل</th><th>الخدمة</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th><th>تاريخ البيع</th></tr></thead><tbody>';
        foreach ($sales->sales ?? [] as $sale) {
            $html .= "<tr>
                <td>{$convert($sale->client_name)}</td>
                <td>{$convert($sale->service_name)}</td>
                <td>{$convert($sale->quantity)}</td>
                <td>{$convert($sale->price)}</td>
                <td>{$convert($sale->total)}</td>
                <td>{$convert($sale->sale_date)}</td>
            </tr>";
        }
        $html .= '</tbody></table></body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->stream('employee_dashboard.pdf');
    }
}
