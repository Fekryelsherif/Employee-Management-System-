<?php

namespace App\Listeners;

use App\Events\SaleCreated;
use App\Services\{ChallengeService, TargetService};

class UpdateChallengeAndTargetProgress
{
    public function handle(SaleCreated $event)
    {
        $sale = $event->sale;

        // ✅ تحديث التارجت
        TargetService::processSale($sale);

        // ✅ تحديث التحديات
        ChallengeService::processSale($sale);
    }
}
