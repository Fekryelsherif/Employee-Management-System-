<?php

namespace App\Http\Controllers\Api\BranchManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{
    Target,
    TargetService,
    TargetParticipant,
    TargetParticipantService,
    Branch,
    User
};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TargetController extends Controller
{
    /**
     * 🔹 عرض جميع التارجتات
     */
    public function index()
    {
        $user = Auth::user();

        if (!in_array($user->type, ['admin', 'branch-manager'])) {
            return response()->json(['message' => 'غير مصرح لك بعرض التارجتات.'], 403);
        }

        $query = Target::with([
            'services.service',
            'participants.employee',
            'participants.services.service',
            'branch'
        ])->latest();

        if ($user->type === 'branch-manager') {
            $query->where('branch_id', $user->branch_id);
        }

        return response()->json($query->get());
    }

    /**
     * 🔹 عرض تارجت واحد بالتفصيل
     */
  public function show(Request $request, $id)
{
    $user = $request->user();

    // استعلام الأساس
    $query = Target::with(['branch', 'services', 'participants.employee', 'participants.services.service'])
        ->where('id', $id);

    if ($user->type === 'branch-manager') {
        // مدير الفرع: يشوف بس التارجت بتاع فرعه
        $query->where('branch_id', $user->branch_id);
    } elseif ($user->type === 'employee') {
        // الموظف: يشوف فقط لو هو مشارك في التارجت
        $query->whereHas('participants', function ($q) use ($user) {
            $q->where('employee_id', $user->id);
        });
    } else {
        // أي نوع مستخدم تاني غير مصرح له
        return response()->json(['message' => 'غير مصرح لك بالوصول إلى هذا التارجت.'], 403);
    }

    $target = $query->first();

    if (!$target) {
        return response()->json(['message' => 'غير مصرح لك بمشاهدة هذا التارجت أو غير موجود.'], 403);
    }

    return response()->json($target);
}


    /**
     * 🔹 إنشاء تارجت جديد
     */
 public function store(Request $request)
{
    $manager = Auth::user();

    if ($manager->type !== 'branch-manager' && $manager->type !== 'admin') {
        return response()->json(['message' => 'غير مصرح لك بإنشاء تارجت.'], 403);
    }

    // التحقق إن المدير مرتبط بفرع
    if ($manager->type === 'branch-manager' && !$manager->branch_id) {
        return response()->json(['message' => 'مدير الفرع ليس له فرع مرتبط.'], 400);
    }

    $validated = $request->validate([
        'title' => 'unique:targets,title|required|string|max:255',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'services' => 'required|array|min:1',
        'services.*.service_id' => 'required|exists:services,id',
        'services.*.target_quantity' => 'required|numeric|min:1',
        'participants' => 'required|array|min:1',
        'participants.*.employee_id' => 'required|exists:users,id',
        'participants.*.services' => 'required|array|min:1',
        'participants.*.services.*.service_id' => 'required|exists:services,id',
        'participants.*.services.*.target_quantity' => 'required|numeric|min:1',
    ]);

    DB::beginTransaction();

    try {
        // إنشاء التارجت وربط الفرع تلقائيًا
        $target = Target::create([
            'branch_id' => $manager->branch_id ?? null,
            'title' => $validated['title'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => 'active'
        ]);

        // إضافة الخدمات العامة للتارجت
        foreach ($validated['services'] as $service) {
            TargetService::create([
                'target_id' => $target->id,
                'service_id' => $service['service_id'],
                'target_quantity' => $service['target_quantity'],
                'sold' => 0
            ]);
        }

        // إضافة المشاركين (الموظفين) والتأكد أنهم تابعين للمدير
        foreach ($validated['participants'] as $participant) {
            $employee = User::where('id', $participant['employee_id'])
                            ->where('branch_manager_id', $manager->id)
                            ->first();

            if (!$employee) {
                return response()->json([
                    'message' => "الموظف ID {$participant['employee_id']} ليس تابعًا لفرعك."
                ], 403);
            }

            $participantRow = TargetParticipant::create([
                'target_id' => $target->id,
                'employee_id' => $employee->id,
                'progress' => 0,
                'status' => 'pending',
            ]);

            foreach ($participant['services'] as $svc) {
                TargetParticipantService::create([
                    'target_participant_id' => $participantRow->id,
                    'service_id' => $svc['service_id'],
                    'target_quantity' => $svc['target_quantity'],
                    'sold' => 0
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'تم إنشاء التارجت بنجاح',
            'data' => $target->load([
                'services.service',
                'participants.employee',
                'participants.services.service'
            ])
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'حدث خطأ أثناء إنشاء التارجت',
            'error' => $e->getMessage()
        ], 500);
    }
}



    /**
     * 🔹 تعديل تارجت
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $target = Target::with(['participants.services'])->find($id);

        if (!$target) {
            return response()->json(['message' => 'التارجت غير موجود.'], 404);
        }

        if (!in_array($user->type, ['admin', 'branch-manager'])) {
            return response()->json(['message' => 'غير مصرح لك بتعديل التارجت.'], 403);
        }

        if ($user->type === 'branch-manager' && $target->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'غير مصرح لك بتعديل هذا التارجت.'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|string|in:active,completed,expired',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.target_quantity' => 'required|numeric|min:1',
            'participants' => 'array',
            'participants.*.employee_id' => 'required|exists:users,id',
            'participants.*.services' => 'array',
            'participants.*.services.*.service_id' => 'required|exists:services,id',
            'participants.*.services.*.target_quantity' => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();

        try {
            $target->update($validated);

            // 🔸 تحديث الخدمات
            if (isset($validated['services'])) {
                TargetService::where('target_id', $target->id)->delete();
                foreach ($validated['services'] as $service) {
                    TargetService::create([
                        'target_id' => $target->id,
                        'service_id' => $service['service_id'],
                        'target_quantity' => $service['target_quantity'],
                        'sold' => 0
                    ]);
                }
            }

            // 🔸 تحديث المشاركين
            if (isset($validated['participants'])) {
                foreach ($target->participants as $oldParticipant) {
                    $oldParticipant->services()->delete();
                    $oldParticipant->delete();
                }

                foreach ($validated['participants'] as $participant) {
                    $participantRow = TargetParticipant::create([
                        'target_id' => $target->id,
                        'employee_id' => $participant['employee_id'],
                        'progress' => 0,
                        'status' => 'pending',
                    ]);

                    foreach ($participant['services'] as $svc) {
                        TargetParticipantService::create([
                            'target_participant_id' => $participantRow->id,
                            'service_id' => $svc['service_id'],
                            'target_quantity' => $svc['target_quantity'],
                            'sold' => 0
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'تم تعديل التارجت بنجاح',
                'data' => $target->load([
                    'services.service',
                    'participants.employee',
                    'participants.services.service'
                ])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'حدث خطأ أثناء تعديل التارجت',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔹 حذف تارجت
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $target = Target::with('participants.services')->find($id);

        if (!$target) {
            return response()->json(['message' => 'التارجت غير موجود.'], 404);
        }

        if (!in_array($user->type, ['admin', 'branch-manager'])) {
            return response()->json(['message' => 'غير مصرح لك بحذف التارجت.'], 403);
        }

        if ($user->type === 'branch-manager' && $target->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'غير مصرح لك بحذف هذا التارجت.'], 403);
        }

        DB::transaction(function () use ($target) {
            $target->services()->delete();
            foreach ($target->participants as $p) {
                $p->services()->delete();
                $p->delete();
            }
            $target->delete();
        });

        return response()->json(['message' => 'تم حذف التارجت بنجاح']);
    }
}
