<?php

namespace App\Services;

use App\Models\ChallengeParticipant;
use App\Models\ChallengeParticipantService;
use App\Notifications\ChallengeCompletedNotification;

class ChallengeService
{
    public static function processSale($sale)
    {
        $employeeId = $sale->employee_id;
        $now = now()->toDateString();

        // هات كل التحديات النشطة للموظف ده
        $participants = ChallengeParticipant::where('employee_id', $employeeId)
            ->whereHas('challenge', function ($q) use ($now) {
                $q->whereDate('start_date', '<=', $now)
                  ->whereDate('end_date', '>=', $now);
            })
            ->whereIn('status', ['accepted', 'in_progress', 'pending'])
            ->get();

        foreach ($participants as $participant) {
            foreach ($sale->items as $item) {
                $pService = ChallengeParticipantService::where('challenge_participant_id', $participant->id)
                    ->where('service_id', $item->service_id)
                    ->first();

                if (!$pService) continue;

                // تحديث المبيعات
                $newSold = $pService->sold + $item->quantity;
                $pService->update([
                    'sold' => min($newSold, $pService->target_quantity)
                ]);
            }

            // حساب التقدم
            $totalTarget = ChallengeParticipantService::where('challenge_participant_id', $participant->id)->sum('target_quantity');
            $totalSold   = ChallengeParticipantService::where('challenge_participant_id', $participant->id)->sum('sold');
            $progress    = $totalTarget > 0 ? round(($totalSold / $totalTarget) * 100, 2) : 0;

            // تحديث الحالة
            if ($progress >= 100) {
                $newStatus = 'completed';
            } elseif ($progress > 0) {
                $newStatus = 'in_progress';
            } else {
                $newStatus = 'pending';
            }

            $participant->update([
                'progress' => $progress,
                'status' => $newStatus,
            ]);

            // إشعار لو اكتمل
            if ($newStatus === 'completed') {
                $challenge = $participant->challenge;
                $employee  = $participant->employee;

                if ($challenge?->manager) {
                    $challenge->manager->notify(new ChallengeCompletedNotification($challenge, $employee));
                }
                $employee->notify(new ChallengeCompletedNotification($challenge, $employee));
            }
        }
    }
}
