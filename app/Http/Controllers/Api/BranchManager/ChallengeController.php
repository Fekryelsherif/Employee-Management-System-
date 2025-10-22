<?php

namespace App\Http\Controllers\Api\BranchManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{
    Challenge,
    ChallengeParticipant,
    ChallengeParticipantService,
    Service,
    User,
    Branch
};
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    protected function ensureIsBranchManager($user)
    {
        if (!$user || $user->type !== 'branch-manager') {
            abort(403, 'Only branch managers can access this.');
        }
    }

    // 📋 عرض كل التحديات الخاصة بفرع المدير
    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensureIsBranchManager($user);

        $challenges = Challenge::with([
            'branch',
            'services',
            'participants.employee',
            'participants.services.service'
        ])
            ->where('branch-manager_id', $user->id)
            ->latest()
            ->get();

        return response()->json($challenges);
    }

    // 🧩 إنشاء تحدي جديد
    public function store(Request $request)
    {
        $user = $request->user();
        $this->ensureIsBranchManager($user);

        $data = $request->validate([
            'title' => 'unique:challenges,title|required|string',
            'description' => 'nullable|string',
            'reward' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:users,id',
            'services' => 'required|array|min:1',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.target_quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // ✅ جلب فرع المدير
            $branch = $user->branch; // ✅ تلقائي
            if (!$branch) {
                return response()->json(['message' => 'No branch found for this manager.'], 404);
            }

            // ⚠️ التحقق من أن كل الموظفين تابعين لنفس الفرع
            $invalidEmployees = User::whereIn('id', $data['employee_ids'])
                ->where('branch_id', '!=', $branch->id)
                ->pluck('id');

            if ($invalidEmployees->count() > 0) {
                return response()->json([
                    'message' => 'Some employees are not in your branch.',
                    'invalid_employee_ids' => $invalidEmployees
                ], 422);
            }

            // ✅ إنشاء التحدي
            $challenge = Challenge::create([
                'branch_id' =>  $branch->id,
                'branch-manager_id' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'reward' => $data['reward'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'active',
                'branch_manager_id' => auth()->id(),

            ]);

            // ✅ الخدمات داخل التحدي
            foreach ($data['services'] as $svc) {
                $challenge->services()->attach($svc['service_id'], [
                    'target_quantity' => $svc['target_quantity']
                ]);
            }

            // ✅ المشاركين (من نفس الفرع فقط)
            foreach ($data['employee_ids'] as $empId) {
                $participant = ChallengeParticipant::create([
                    'challenge_id' => $challenge->id,
                    'employee_id' => $empId,
                    'status' => 'pending',
                    'progress' => 0
                ]);

                // نسخ الخدمات من التحدي إلى كل موظف
                foreach ($data['services'] as $svc) {
                    ChallengeParticipantService::create([
                        'challenge_participant_id' => $participant->id,
                        'service_id' => $svc['service_id'],
                        'target_quantity' => $svc['target_quantity'],
                        'sold' => 0
                    ]);
                }

                // إشعار الموظف (اختياري)
                $employee = User::find($empId);
                if ($employee) {
                    $employee->notify(new \App\Notifications\ChallengeCreatedNotification($challenge));
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Challenge created successfully.',
                'challenge' => $challenge->load('services', 'participants.employee', 'participants.services')
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating challenge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 📄 عرض تحدي واحد
    public function show(Request $request, $id)
{
    $user = $request->user();

    // نحمل التحدي مع العلاقات
    $challenge = Challenge::with([
        'branch',
        'services',
        'participants.employee',
        'participants.services.service'
    ])->findOrFail($id);

    // ✅ لو المستخدم مدير فرع
    if ($user->type === 'branch-manager') {
        // لازم يكون مدير الفرع صاحب التحدي
        if ($challenge->branch_manager_id !== $user->id) {
            return response()->json(['message' => 'You are not authorized to view this challenge.'], 403);
        }
    }
    // ✅ لو المستخدم موظف
    elseif ($user->type === 'employee') {
        // لازم يكون مشارك في التحدي
        $isParticipant = $challenge->participants()
            ->where('employee_id', $user->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'You are not authorized to view this challenge.'], 403);
        }
    }
    // 🚫 أي نوع مستخدم تاني
    else {
        return response()->json(['message' => 'You are not authorized to view this challenge.'], 403);
    }

    // ✅ لو التحقق نجح، نرجع بيانات التحدي
    return response()->json($challenge);
}


    // ✏️ تعديل التحدي
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $this->ensureIsBranchManager($user);

        $challenge = Challenge::where('id', $id)
            ->where('branch-manager_id', $user->id)
            ->firstOrFail();

        $data = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'reward' => 'sometimes|numeric',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|in:draft,active,finished',
        ]);

        $challenge->update($data);

        return response()->json([
            'message' => 'Challenge updated successfully',
            'challenge' => $challenge->fresh()
        ]);
    }

    // ❌ حذف التحدي
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $this->ensureIsBranchManager($user);

        $challenge = Challenge::where('id', $id)
            ->where('branch-manager_id', $user->id)
            ->firstOrFail();

        $challenge->delete();

        return response()->json(['message' => 'Challenge deleted successfully']);
    }

    // ➕ إضافة مشاركين (من نفس فرع المدير فقط)
    public function addParticipants(Request $request, $id)
    {
        $user = $request->user();
        $this->ensureIsBranchManager($user);

        $data = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:users,id'
        ]);

        $challenge = Challenge::where('id', $id)
            ->where('branch-manager_id', $user->id)
            ->firstOrFail();

        $branchId = $challenge->branch_id;

        // ✅ التحقق أن الموظفين من نفس الفرع
        $invalidEmployees = User::whereIn('id', $data['employee_ids'])
            ->where('branch_id', '!=', $branchId)
            ->pluck('id');

        if ($invalidEmployees->count() > 0) {
            return response()->json([
                'message' => 'Some employees are not in your branch.',
                'invalid_employee_ids' => $invalidEmployees
            ], 422);
        }

        foreach ($data['employee_ids'] as $empId) {
            if ($challenge->participants()->where('employee_id', $empId)->exists()) continue;

            $participant = ChallengeParticipant::create([
                'challenge_id' => $challenge->id,
                'employee_id' => $empId,
                'status' => 'pending',
                'progress' => 0
            ]);

            foreach ($challenge->services as $cs) {
                ChallengeParticipantService::create([
                    'challenge_participant_id' => $participant->id,
                    'service_id' => $cs->id,
                    'target_quantity' => $cs->pivot->target_quantity,
                    'sold' => 0
                ]);
            }

            $employee = User::find($empId);
            if ($employee) {
                $employee->notify(new \App\Notifications\ChallengeCreatedNotification($challenge));
            }
        }

        return response()->json(['message' => 'Participants added successfully']);
    }

    // 🗑️ حذف مشارك
    public function removeParticipant(Request $request, $id, $employeeId)
    {
        $user = $request->user();
        $this->ensureIsBranchManager($user);

        $challenge = Challenge::where('id', $id)
            ->where('branch-manager_id', $user->id)
            ->firstOrFail();

        $participant = $challenge->participants()->where('employee_id', $employeeId)->first();

        if ($participant) {
            $participant->delete();
        }

        return response()->json(['message' => 'Participant removed successfully']);
    }
}