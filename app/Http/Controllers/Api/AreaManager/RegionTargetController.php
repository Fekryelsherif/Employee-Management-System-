<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\Target;
use Illuminate\Http\Request;

class RegionTargetController extends Controller
{
    // ✅ عرض كل التارجتس
    public function index()
    {
        $targets = Target::with(['branch', 'regionManager'])->get();
        return response()->json($targets);
    }

    // ✅ عرض تارجت واحد
    public function show($id)
    {
        $target = Target::with(['branch', 'regionManager'])->find($id);
        if (!$target) {
            return response()->json(['message' => '❌ التارجت غير موجود'], 404);
        }

        return response()->json($target);
    }

    // ✅ إنشاء تارجت جديد
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'region-manager') {
            return response()->json(['message' => '❌ غير مصرح لك بإنشاء تارجت.'], 403);
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'target_services' => 'required|integer|min:1',
        ]);

        // 🔹 تحقق من وجود تارجت نشط لنفس الفرع ولنفس مدير المنطقة
        $existingTarget = Target::where('branch_id', $validated['branch_id'])
            ->where('region_manager_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($existingTarget) {
            return response()->json([
                'message' => "⚠️ لا يمكن إنشاء تارجت جديد، يوجد تارجت نشط بالفعل لهذا الفرع تحت إدارتك."
            ], 400);
        }

        // ✅ إنشاء التارجت الجديد
        $target = Target::create([
            'branch_id' => $validated['branch_id'],
            'region_manager_id' => $user->id,
            'target_services' => $validated['target_services'],
            'achieved_services' => 0,
            'progress' => 0,
            'status' => 'active',
            'title' => 'تارجت فرع ' . $validated['branch_id'],
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        return response()->json([
            'message' => '✅ تم إنشاء التارجت بنجاح.',
            'target' => $target
        ], 201);
    }

    // ✅ تعديل تارجت موجود
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user->type !== 'region-manager') {
            return response()->json(['message' => '❌ غير مصرح لك بتعديل التارجت.'], 403);
        }

        $target = Target::find($id);
        if (!$target) {
            return response()->json(['message' => '❌ التارجت غير موجود'], 404);
        }

        $validated = $request->validate([
            'branch_id' => 'sometimes|exists:branches,id',
            'target_services' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:active,inactive',
        ]);

        // 🔹 التحقق من الخدمات المباعة فعلاً
        $totalSold = $target->sales()
            ->with('items')
            ->get()
            ->flatMap
            ->items
            ->sum('quantity');

        if (isset($validated['target_services']) && $validated['target_services'] < $totalSold) {
            return response()->json([
                'message' => "❌ لا يمكن تقليل عدد التارجت. تم بيع {$totalSold} خدمات بالفعل، وهو أكبر من العدد الجديد ({$validated['target_services']})."
            ], 400);
        }

        $target->update($validated);

        // ✅ تحديث نسبة الإنجاز بعد التعديل
        $this->updateTargetProgress($target);

        return response()->json([
            'message' => '✅ تم تحديث التارجت بنجاح.',
            'target' => $target
        ]);
    }

    // ✅ حذف تارجت
    public function destroy($id)
    {
        $user = auth()->user();

        if ($user->type !== 'region-manager') {
            return response()->json(['message' => '❌ غير مصرح لك بحذف التارجت.'], 403);
        }

        $target = Target::find($id);
        if (!$target) {
            return response()->json(['message' => '❌ التارجت غير موجود'], 404);
        }

        $target->delete();

        return response()->json(['message' => '✅ تم حذف التارجت بنجاح.']);
    }

    // 🔁 تحديث نسبة التارجت (progress)
    private function updateTargetProgress(Target $target)
    {
        $totalServices = $target->target_services;
        $achieved = $target->sales()
            ->with('items')
            ->get()
            ->flatMap
            ->items
            ->sum('quantity');

        $progress = $totalServices > 0
            ? round(($achieved / $totalServices) * 100, 2)
            : 0;

        $target->update([
            'achieved_services' => $achieved,
            'progress' => $progress,
        ]);
    }
}
