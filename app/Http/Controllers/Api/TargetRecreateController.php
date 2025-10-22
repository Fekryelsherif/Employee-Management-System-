<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Target;
use Illuminate\Http\Request;

class TargetRecreateController extends Controller
{
    /**
     * عرض كل التارجتات الإضافية الخاصة بالموظف
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'employee') {
            return response()->json(['message' => 'غير مصرح لك بالوصول لهذه البيانات.'], 403);
        }

        $targets = Target::where('employee_id', $user->id)
            ->whereNotNull('recreated_from_target_id')
            ->get();

        return response()->json($targets);
    }

    /**
     * عرض تفاصيل تارجت إضافي واحد
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $target = Target::where('id', $id)
            ->where('employee_id', $user->id)
            ->whereNotNull('recreated_from_target_id')
            ->firstOrFail();

        return response()->json($target);
    }

    /**
     * إنشاء تارجت إضافي جديد بناءً على هدف مالي
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'employee') {
            return response()->json(['message' => 'غير مصرح لك بإنشاء تارجت إضافي.'], 403);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $mainTarget = Target::where('employee_id', $user->id)
            ->where('status', 'active')
            ->whereNull('recreated_from_target_id')
            ->firstOrFail();

        $commissionRate = $user->commission_rate ?? 10;
        $averageServicePrice = 1000;
        $targetServices = ceil($data['amount'] / (($commissionRate / 100) * $averageServicePrice));

        $recreatedTarget = Target::create([
            'employee_id' => $user->id,
            'branch_id' => $user->branch_id,
            'target_services' => $targetServices,
            'achieved_services' => 0,
            'progress' => 0,
            'recreated_goal_amount' => $data['amount'],
            'recreated_from_target_id' => $mainTarget->id,
            'status' => 'active',
            'title' => "نسخة من: {$mainTarget->title} - {$data['amount']} جنيه",
            'start_date' => $mainTarget->start_date,
            'end_date' => $mainTarget->end_date,
        ]);

        return response()->json([
            'message' => '✅ تم إنشاء تارجت إضافي جديد بنجاح.',
            'target' => $recreatedTarget,
        ], 201);
    }

    /**
     * تعديل التارجت الإضافي (مثلاً تغيير المبلغ أو الاسم)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $target = Target::where('id', $id)
            ->where('employee_id', $user->id)
            ->whereNotNull('recreated_from_target_id')
            ->firstOrFail();

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:100',
            'status' => 'nullable|in:active,completed,expired',
        ]);

        if (isset($data['amount'])) {
            $commissionRate = $user->commission_rate ?? 10;
            $averageServicePrice = 1000;
            $target->target_services = ceil($data['amount'] / (($commissionRate / 100) * $averageServicePrice));
            $target->recreated_goal_amount = $data['amount'];
        }

        if (isset($data['status'])) {
            $target->status = $data['status'];
        }

        $target->save();

        return response()->json([
            'message' => '✅ تم تحديث التارجت بنجاح.',
            'target' => $target,
        ]);
    }

    /**
     * حذف تارجت إضافي
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $target = Target::where('id', $id)
            ->where('employee_id', $user->id)
            ->whereNotNull('recreated_from_target_id')
            ->firstOrFail();

        $target->delete();

        return response()->json(['message' => '🗑️ تم حذف التارجت بنجاح.']);
    }
}