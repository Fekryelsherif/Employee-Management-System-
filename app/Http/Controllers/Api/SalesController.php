<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\{
    Sale,
    SaleItem,
    User,
    ServiceCommission,
    Challenge,
    Target,
    ChallengeParticipant,
    TargetParticipant
};
use App\Services\{TargetService, ChallengeService};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    // 📋 عرض كل عمليات البيع
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Sale::with(['items.service', 'employee']);

        if ($user->type === 'employee') {
            $query->where('employee_id', $user->id);
        } elseif ($user->type === 'branch-manager') {
            $query->where('branch_id', $user->branch_id);
        }

        $sales = $query->latest()->get();

        $sales = $sales->map(function ($sale) {
            $totalAmount = 0;
            $totalCommission = 0;

            foreach ($sale->items as $item) {
                $itemTotal = $item->price * $item->quantity;
                $commissionRate = $this->getServiceCommissionRate($sale->employee, $item->service_id);
                $commissionValue = ($item->price * $commissionRate / 100) * $item->quantity;

                $item->commission_value = $commissionValue;
                $item->total = $itemTotal;

                $totalAmount += $itemTotal;
                $totalCommission += $commissionValue;
            }

            $sale->setAttribute('total_amount', $totalAmount);
            $sale->setAttribute('total_commission', $totalCommission);

            return $sale;
        });

        return response()->json($sales);
    }

    // 🧾 إنشاء عملية بيع جديدة
 public function store(Request $request)
    {
        $user = $request->user();
        $employee_id=$request->user()->id;
        // ✅ لازم يكون موظف عشان يعمل عملية بيع
        if (!$user || $user->type !== 'employee') {
            abort(403, 'Only employees can create sales.');
        }

        // ✅ التحقق من البيانات
        $data = $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'source_type' => 'required|in:target,challenge',
            'source_id'   => 'required|integer',
            'items'       => 'required|array|min:1',
            'items.*.service_id' => 'required|exists:services,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.price'      => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $employee = $user;
            $today = now()->toDateString();

            // ✅ تحقق من صلاحية المشاركة (لو في target أو challenge)
            $this->validateParticipation($employee, $data, $today);

            // ✅ إنشاء البيع
            $sale = Sale::create([
                'employee_id' => $employee_id,
                'branch_id'   => $employee->branch_id,
                'client_id'   => $data['client_id'],
                'source_type' => $data['source_type'],
                'source_id'   => $data['source_id'],
                'total_amount' => 0,
                'total_commission' => 0,
            ]);

            $totalCommission = 0;
            $totalAmount = 0;

            foreach ($data['items'] as $item) {
                $commissionRate = $this->getServiceCommissionRate($employee, $item['service_id']);
                $commissionValue = ($item['price'] * $item['quantity'] * $commissionRate) / 100;
                $total = $item['price'] * $item['quantity'];

                $totalCommission += $commissionValue;
                $totalAmount += $total;

                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'service_id' => $item['service_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'total'      => $total,
                ]);
            }

            // ✅ تحديث إجمالي البيع
            $sale->update([
                'total_amount' => $totalAmount,
                'total_commission' => $totalCommission,
            ]);

            // ✅ تحديث رصيد العمولة عند الموظف
            $employee->increment('commission_salary', $totalCommission);

            // ✅ تحديث progress للتارجت
            $this->updateTargetProgress($employee);

            // ✅ تحديث الخدمات في التارجت والتحدي
            TargetService::processSale($sale);
            ChallengeService::processSale($sale);

            DB::commit();

            return response()->json([
                'message' => 'Sale created successfully.',
                'sale' => $sale->load('items.service'),
                'added_commission' => $totalCommission,
                'employee_total_commission' => $employee->commission_salary,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating sale.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // 📄 عرض عملية بيع واحدة
public function show(Request $request, $id)
{
    $user = $request->user();
    $sale = Sale::with('items.service')->findOrFail($id);

    if ($user->type === 'employee' && $sale->employee_id !== $user->id) {
        abort(403, 'غير مصرح لك بعرض هذه العملية.');
    }

    return response()->json($sale);
}

// ✏️ تعديل عملية بيع
public function update(Request $request, $id)
{
    $user = $request->user();
    $sale = Sale::with('items')->findOrFail($id);

    if ($user->type === 'employee' && $sale->employee_id !== $user->id) {
        abort(403, 'غير مصرح لك بتعديل هذه العملية.');
    }

    $data = $request->validate([
        'items' => 'required|array|min:1',
        'items.*.service_id' => 'required|exists:services,id',
        'items.*.quantity'   => 'required|integer|min:1',
        'items.*.price'      => 'required|numeric|min:0',
        'items.*.total'      => 'sometimes|numeric|min:0',
    ]);

    DB::beginTransaction();

    try {
        // خصم العمولة القديمة من راتب الموظف
        $oldCommission = $this->calculateSaleCommission($sale);
        $sale->employee->decrement('commission_salary', $oldCommission);

        // حذف العناصر القديمة
        $sale->items()->delete();

        // إضافة العناصر الجديدة وحساب المجموع والعمولة
        $totalAmount = 0;
        $totalCommission = 0;

        foreach ($data['items'] as $item) {
            $commissionRate = $this->getServiceCommissionRate($sale->employee, $item['service_id']);
            $commissionValue = ($item['price'] * $commissionRate / 100) * $item['quantity'];
            $totalCommission += $commissionValue;

            $total = $item['price'] * $item['quantity'];
            $totalAmount += $total;

            SaleItem::create([
                'sale_id'    => $sale->id,
                'service_id' => $item['service_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
                'total'      => $total,
            ]);
        }

        // تحديث إجماليات البيع
        $sale->update([
            'total_amount' => $totalAmount,
            'total_commission' => $totalCommission,
        ]);

        // إضافة العمولة الجديدة للموظف
        $sale->employee->increment('commission_salary', $totalCommission);

        DB::commit();

        TargetService::processSale($sale);
        ChallengeService::processSale($sale);

        return response()->json([
            'message' => 'Sale updated successfully.',
            'sale' => $sale->load('items.service'),
            'new_commission' => $totalCommission,
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error updating sale.', 'error' => $e->getMessage()], 500);
    }
}

// 🗑️ حذف عملية بيع
public function destroy(Request $request, $id)
{
    $user = $request->user();
    $sale = Sale::with('items')->findOrFail($id);

    if ($user->type === 'employee' && $sale->employee_id !== $user->id) {
        abort(403, 'غير مصرح لك بحذف هذه العملية.');
    }

    DB::beginTransaction();
    try {
        // خصم العمولة القديمة من راتب الموظف
        $oldCommission = $this->calculateSaleCommission($sale);
        $sale->employee->decrement('commission_salary', $oldCommission);

        $sale->items()->delete();
        $sale->delete();

        DB::commit();

        TargetService::processSale($sale);
        ChallengeService::processSale($sale);

        return response()->json(['message' => 'Sale deleted successfully and commission removed.']);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error deleting sale.', 'error' => $e->getMessage()], 500);
    }
}

// ✅ دالة لتحديث progress بتاع التارجت
    private function updateTargetProgress($employee)
    {
        $mainTarget = Target::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereNull('recreated_from_target_id')
            ->first();

        if (!$mainTarget) return;

        $allTargets = Target::where('employee_id', $employee->id)
            ->where(function ($q) use ($mainTarget) {
                $q->where('id', $mainTarget->id)
                  ->orWhere('recreated_from_target_id', $mainTarget->id);
            })->get();

        $totalServices = $allTargets->sum('target_services');
        $totalAchieved = $allTargets->sum('achieved_services');

        $mainTarget->update([
            'progress' => $totalServices > 0 ? round(($totalAchieved / $totalServices) * 100, 2) : 0
        ]);
    }
    // 🔍 دوال مساعدة وحساب العمولة
 private function getServiceCommissionRate($employee, $serviceId)
{
    $commission = \App\Models\ServiceCommission::query()
        ->where('service_id', $serviceId)
        ->where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
              ->orWhereNull('employee_id');
        })
        ->orderByRaw('CASE WHEN employee_id IS NOT NULL THEN 1 ELSE 2 END')
        ->first();

    if (!$commission) {
        Log::info("🔍 No commission found for service_id={$serviceId}, employee_id={$employee->id}");
        return 0;
    }

    Log::info("✅ Found commission rate={$commission->commission_rate} for service_id={$serviceId}, employee_id={$employee->id}");
    return (float) $commission->commission_rate;
}


    private function calculateSaleCommission($sale)
    {
        $total = 0;
        foreach ($sale->items as $item) {
            $rate = $this->getServiceCommissionRate($sale->employee, $item->service_id);
            $total += ($item->price * $rate / 100) * $item->quantity;
        }
        return $total;
    }

    private function validateParticipation($employee, $data, $today)
    {
        if ($data['source_type'] === 'challenge') {
            $challenge = Challenge::findOrFail($data['source_id']);
            if ($today < $challenge->start_date || $today > $challenge->end_date) {
                throw ValidationException::withMessages(['source_id' => 'لا يمكن تنفيذ البيع لأن التحدي خارج الفترة المحددة.']);
            }
            if (!ChallengeParticipant::where('employee_id', $employee->id)
                ->where('challenge_id', $data['source_id'])
                ->exists()) {
                throw ValidationException::withMessages(['source_id' => 'هذا الموظف غير مشارك في هذا التحدي.']);
            }
        } else {
            $target = Target::findOrFail($data['source_id']);
            if ($today < $target->start_date || $today > $target->end_date) {
                throw ValidationException::withMessages(['source_id' => 'لا يمكن تنفيذ البيع لأن التارجت خارج الفترة المحددة.']);
            }
            if (!TargetParticipant::where('employee_id', $employee->id)
                ->where('target_id', $data['source_id'])
                ->exists()) {
                throw ValidationException::withMessages(['source_id' => 'هذا الموظف غير مشارك في هذا التارجت.']);
            }
        }
    }





   public function exportCsv(Request $request)
{
    $user = $request->user();

    if ($user->type === 'branch_manager') {
        $employeeIds = User::where('branch_id', $user->branch_id)
            ->where('type', 'employee')
            ->pluck('id');

        $sales = Sale::with(['employee', 'client', 'items.service'])
            ->whereIn('employee_id', $employeeIds)
            ->get();
    } elseif ($user->type === 'employee') {
        $sales = Sale::with(['items.service', 'client'])
            ->where('employee_id', $user->id)
            ->get();
    } else {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }

    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="sales.csv"',
    ];

    $callback = function () use ($sales) {
        $file = fopen('php://output', 'w');
        fputcsv($file, [
            'الموظف', 'العميل', 'الخدمات', 'الكميات', 'أسعار الوحدة', 'الإجمالي لكل خدمة',
            'المجموع الكلي', 'عمولة الخدمة', 'إجمالي العمولة', 'تاريخ البيع'
        ]);

        foreach ($sales as $sale) {
            $services = $sale->items->pluck('service.name')->join(' | ');
            $quantities = $sale->items->pluck('quantity')->join(' | ');
            $unitPrices = $sale->items->pluck('price')->join(' | ');
            $totals = $sale->items->pluck('total')->join(' | ');

            fputcsv($file, [
                $sale->employee->fname ?? '-',
                $sale->client->name ?? '-',
                $services,
                $quantities,
                $unitPrices,
                $totals,
                $sale->total_amount,
                $sale->items->pluck('total_commission')->join(' | '),
                $sale->total_commission,
                $sale->created_at->format('Y-m-d H:i'),
            ]);
        }

        fclose($file);
    };

    return new StreamedResponse($callback, 200, $headers);
}

public function exportPdf(Request $request)
{
    $user = $request->user();

    if ($user->type === 'branch_manager') {
        $employeeIds = User::where('branch_id', $user->branch_id)
            ->where('type', 'employee')
            ->pluck('id');

        $sales = Sale::with(['employee', 'client', 'items.service'])
            ->whereIn('employee_id', $employeeIds)
            ->get();
    } elseif ($user->type === 'employee') {
        $sales = Sale::with(['items.service', 'client'])
            ->where('employee_id', $user->id)
            ->get();
    } else {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }

    $totalSales = $sales->sum('total_amount');

    $html = '
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; text-align: right; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #000; padding: 6px; }
            th { background-color: #f2f2f2; }
            h2 { text-align: center; }
        </style>
    </head>
    <body>
        <h2>تقرير المبيعات</h2>
        <table>
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>العميل</th>
                    <th>الخدمات</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>الإجمالي لكل خدمة</th>
                    <th>المجموع الكلي</th>
                    <th>عمولة الخدمة</th>
                    <th>إجمالي العمولة</th>
                    <th>تاريخ البيع</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($sales as $sale) {
        $services = $sale->items->pluck('service.name')->join(' | ');
        $quantities = $sale->items->pluck('quantity')->join(' | ');
        $unitPrices = $sale->items->pluck('price')->join(' | ');
        $totals = $sale->items->pluck('total')->join(' | ');
        $commissions = $sale->items->pluck('total_commission')->join(' | ');

        $html .= '<tr>
            <td>' . ($sale->employee->fname ?? '-') . '</td>
            <td>' . ($sale->client->name ?? '-') . '</td>
            <td>' . $services . '</td>
            <td>' . $quantities . '</td>
            <td>' . $unitPrices . '</td>
            <td>' . $totals . '</td>
            <td>' . $sale->total_amount . '</td>
            <td>' . $commissions . '</td>
            <td>' . $sale->total_commission . '</td>
            <td>' . Carbon::parse($sale->created_at)->format('Y-m-d H:i') . '</td>
        </tr>';
    }

    $html .= '</tbody></table>
        <h3 style="margin-top:20px;">إجمالي المبيعات: ' . number_format($totalSales, 2) . ' ج.م</h3>
    </body></html>';

    $pdf = Pdf::loadHTML($html);
    return $pdf->download('sales-report.pdf');
}
}
