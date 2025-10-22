<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceCommission;

class ServiceCommissionController extends Controller
{
    // 🧩 عرض كل العمولات (لكل الخدمات)
    public function index(Request $request)
    {
        $user = $request->user();

        if (!in_array(strtolower($user->type), ['branch-manager', 'branch_manager'])) {
            return response()->json(['message' => 'غير مصرح لك بعرض العمولات.'], 403);
        }

        $commissions = ServiceCommission::with('service')->get();

        return response()->json($commissions);
    }

    // 📄 عرض عمولة خدمة واحدة
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!in_array(strtolower($user->type), ['branch-manager', 'branch_manager'])) {
            return response()->json(['message' => 'غير مصرح لك بعرض العمولة.'], 403);
        }

        $commission = ServiceCommission::with('service')->findOrFail($id);

        return response()->json($commission);
    }

    // 🆕 إضافة عمولة جديدة
    public function store(Request $request)
    {
        $user = $request->user();

        if (!in_array(strtolower($user->type), ['branch-manager', 'branch_manager'])) {
            return response()->json(['message' => 'غير مصرح لك بإضافة عمولات.'], 403);
        }

        $data = $request->validate([
            'service_ids'   => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $commissions = [];
        DB::beginTransaction();
        try {
            foreach ($data['service_ids'] as $serviceId) {
                $commissions[] = ServiceCommission::updateOrCreate(
                    [
                        'service_id' => $serviceId,
                        'branch_manager_id' => $user->id
                    ],
                    [
                        'commission_rate' => $data['commission_rate'],
                    ]
                );
            }

            DB::commit();
            return response()->json([
                'message' => 'تم إضافة / تحديث العمولات بنجاح.',
                'commissions' => $commissions
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'حدث خطأ أثناء حفظ العمولات.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✏️ تعديل عمولة موجودة
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!in_array(strtolower($user->type), ['branch-manager', 'branch_manager'])) {
            return response()->json(['message' => 'غير مصرح لك بتعديل العمولات.'], 403);
        }

        $data = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $commission = ServiceCommission::findOrFail($id);

        // تأكد أن مدير الفرع هو صاحب العمولة
        if ($commission->branch_manager_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذه العمولة.'], 403);
        }

        $commission->update([
            'commission_rate' => $data['commission_rate'],
        ]);

        return response()->json([
            'message' => 'تم تعديل العمولة بنجاح.',
            'commission' => $commission
        ]);
    }

    // 🗑️ حذف عمولة
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (!in_array(strtolower($user->type), ['branch-manager', 'branch_manager'])) {
            return response()->json(['message' => 'غير مصرح لك بحذف العمولات.'], 403);
        }

        $commission = ServiceCommission::findOrFail($id);

        if ($commission->branch_manager_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح لك بحذف هذه العمولة.'], 403);
        }

        $commission->delete();

        return response()->json(['message' => 'تم حذف العمولة بنجاح.']);
    }
}
