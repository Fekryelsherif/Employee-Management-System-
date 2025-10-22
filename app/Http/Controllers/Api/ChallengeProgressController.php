<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Challenge, ChallengeParticipant, ChallengeParticipantService};
use Illuminate\Support\Facades\DB;

class ChallengeProgressController extends Controller
{
    /**
     *  عرض تقدم جميع الموظفين فى تحدي معين (فرع المدير)
     */
    public function managerProgress($challengeId)
    {
        $managerId = auth()->id();

        $challenge = Challenge::where('branch_manager_id', $managerId)
            ->with(['participants.employee', 'services'])
            ->findOrFail($challengeId);

        $data = $challenge->participants->map(function ($p) {
            $totalTarget = $p->services->sum('target_quantity');
            $totalSold = $p->services->sum('sold');
            $progress = $totalTarget > 0 ? round(($totalSold / $totalTarget) * 100, 2) : 0;

            return [
                'employee' => $p->employee->fname . ' ' . $p->employee->lname,
                'progress_percent' => $progress,
                'sold' => $totalSold,
                'target' => $totalTarget,
                'status' => $p->status,
                'services' => $p->services->map(function ($s) {
                    return [
                        'service_name' => $s->service->name,
                        'sold' => $s->sold,
                        'target' => $s->target_quantity,
                        'progress_percent' => $s->target_quantity > 0 ? round(($s->sold / $s->target_quantity) * 100, 2) : 0,
                    ];
                })
            ];
        });

        return response()->json([
            'challenge' => $challenge->title,
            'participants' => $data
        ]);
    }

    /**
     *  عرض تقدم الموظف فى تحدي معين (لما يفتح من حسابه)
     */
    public function employeeProgress($challengeId)
    {
        $employeeId = auth()->id();

        $participant = ChallengeParticipant::where('challenge_id', $challengeId)
            ->where('employee_id', $employeeId)
            ->with(['challenge', 'services.service'])
            ->firstOrFail();

        $totalTarget = $participant->services->sum('target_quantity');
        $totalSold = $participant->services->sum('sold');
        $progress = $totalTarget > 0 ? round(($totalSold / $totalTarget) * 100, 2) : 0;

        return response()->json([
            'challenge' => $participant->challenge->title,
            'description' => $participant->challenge->description,
            'progress_percent' => $progress,
            'sold' => $totalSold,
            'target' => $totalTarget,
            'services' => $participant->services->map(function ($s) {
                return [
                    'service_name' => $s->service->name,
                    'sold' => $s->sold,
                    'target' => $s->target_quantity,
                    'progress_percent' => $s->target_quantity > 0 ? round(($s->sold / $s->target_quantity) * 100, 2) : 0,
                ];
            }),
        ]);
    }

    /**
     *  ملخص لكل التحديات الخاصة بالمدير ونسبة التقدم العامة
     */
    public function managerOverview()
    {
        $managerId = auth()->id();

        $challenges = Challenge::where('branch_manager_id', $managerId)
            ->with('participants.services')
            ->get()
            ->map(function ($ch) {
                $totalTarget = $ch->participants->flatMap->services->sum('target_quantity');
                $totalSold = $ch->participants->flatMap->services->sum('sold');
                $progress = $totalTarget > 0 ? round(($totalSold / $totalTarget) * 100, 2) : 0;

                return [
                    'challenge_id' => $ch->id,
                    'title' => $ch->title,
                    'progress_percent' => $progress,
                    'start_date' => $ch->start_date,
                    'end_date' => $ch->end_date,
                ];
            });

        return response()->json(['overview' => $challenges]);
    }

    /**
     *  التحديات الحالية للموظف
     */
    public function employeeActiveChallenges()
    {
        $employeeId = auth()->id();

        $today = now()->toDateString();
        $active = ChallengeParticipant::where('employee_id', $employeeId)
            ->whereHas('challenge', function ($q) use ($today) {
                $q->where('start_date', '<=', $today)
                  ->where('end_date', '>=', $today);
            })
            ->with('challenge')
            ->get()
            ->map(function ($p) {
                return [
                    'challenge_id' => $p->challenge->id,
                    'title' => $p->challenge->title,
                    'start_date' => $p->challenge->start_date,
                    'end_date' => $p->challenge->end_date,
                    'status' => $p->status,
                ];
            });

        return response()->json(['active_challenges' => $active]);
    }
}
