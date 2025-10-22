<?php

// app/Http/Controllers/Api/Employee/ChallengeController.php
namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Challenge, ChallengeParticipant, ChallengeParticipantService};

class ChallengeRespondController extends Controller
{
    protected function ensureIsEmployee($user) {
        if (!$user || $user->type !== 'employee') abort(403, 'Only employee can access');
    }

    // list my assigned challenges (all statuses)
    public function myChallenges(Request $request) {
        $user = $request->user();
        $this->ensureIsEmployee($user);

        $items = ChallengeParticipant::with('challenge.services','serviceProgress.service')
            ->where('employee_id', $user->id)
            ->get();

        return response()->json($items);
    }

    // respond accept/reject
    public function respond(Request $request, $challengeId) {
        $user = $request->user();
        $this->ensureIsEmployee($user);

        $data = $request->validate(['status'=>'required|in:accepted,rejected']);
        $participant = ChallengeParticipant::where('challenge_id',$challengeId)->where('employee_id',$user->id)->firstOrFail();
        $participant->status = $data['status'];
        $participant->save();

        // notify manager
        $manager = $participant->challenge->manager;
        $manager->notify(new \App\Notifications\ChallengeRespondedNotification($participant->challenge, $user, $data['status']));

        return response()->json(['message'=>'Response saved','status'=>$data['status']]);
    }

    // mark as completed manually (optional) - usually completion handled by ChallengeService::processSale
    public function markCompleted(Request $request, $challengeId) {
        $user = $request->user();
        $this->ensureIsEmployee($user);

        $participant = ChallengeParticipant::where('challenge_id',$challengeId)->where('employee_id',$user->id)->firstOrFail();
        $participant->status = 'completed';
        $participant->progress = 100;
        $participant->save();

        $manager = $participant->challenge->manager;
        $manager->notify(new \App\Notifications\ChallengeCompletedNotification($participant->challenge, $user));
        $user->notify(new \App\Notifications\ChallengeCompletedNotification($participant->challenge, $user));

        return response()->json(['message'=>'Marked completed']);
    }
}
