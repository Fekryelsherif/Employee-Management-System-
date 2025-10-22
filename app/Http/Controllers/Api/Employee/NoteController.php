<?php

namespace App\Http\Controllers\Api\Employee;

use App\Models\Note;
use App\Models\User;
use App\Notifications\NewNoteNotification;
use App\Notifications\NoteReviewedNotification;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

class NoteController extends Controller{
    public function store(Request $request)
    {
        $employee = $request->user();
        $branch_manager = User::where('type', 'branch-manager')->first();

        $note = Note::create([
            'employee_id' => $employee->id,
            'branch_manager_id' => $branch_manager->id,
            'title' => $request->title,
            'description' => $request->description,
        ]);

        $branch_manager->notify(new NewNoteNotification($note));

        return response()->json(['message' => 'Note submitted successfully'], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'branch-manager') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(Note::with('employee')->get());
    }

    public function markReviewed($id)
    {
        $note = Note::findOrFail($id);
        $note->status = 'reviewed';
        $note->save();

        $note->employee->notify(new NoteReviewedNotification($note));

        return response()->json(['message' => 'Note marked as reviewed']);
    }
}
