<?php

namespace App\Services;

use App\Models\TargetParticipant;
use App\Models\TargetParticipantService;
use App\Notifications\TargetCompletedNotification;

class TargetService
{
    public static function processSale($sale)
    {
        $employeeId = $sale->employee_id;
        $now = now()->toDateString();

        // هات كل المشاركين في تارجتات نشطة للموظف ده
        $participants = TargetParticipant::where('employee_id', $employeeId)
            ->whereHas('target', function ($q) use ($now) {
                $q->whereDate('start_date', '<=', $now)
                  ->whereDate('end_date', '>=', $now);
            })
            ->whereIn('status', ['accepted', 'in_progress', 'pending'])
            ->get();

        foreach ($participants as $participant) {
            foreach ($sale->items as $item) {

                // لو الخدمة دي موجودة في التارجت
                $pService = TargetParticipantService::where('target_participant_id', $participant->id)
                    ->where('service_id', $item->service_id)
                    ->first();

                if (!$pService) continue;

                // تحديث الكمية المباعة
                $newSold = $pService->sold + $item->quantity;
                $pService->update([
                    'sold' => min($newSold, $pService->target_quantity)
                ]);
            }

            // حساب التقدم
            $totalTarget = TargetParticipantService::where('target_participant_id', $participant->id)->sum('target_quantity');
            $totalSold   = TargetParticipantService::where('target_participant_id', $participant->id)->sum('sold');
            $progress    = $totalTarget > 0 ? round(($totalSold / $totalTarget) * 100, 2) : 0;

            // تحديد الحالة
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

            // إشعار عند الاكتمال
            if ($newStatus === 'completed') {
                $target = $participant->target;
                $employee = $participant->employee;
                if ($target?->branch?->manager) {
                    $target->branch->manager->notify(new TargetCompletedNotification($target, $employee));
                }
                $employee->notify(new TargetCompletedNotification($target, $employee));
            }
        }
    }
}
