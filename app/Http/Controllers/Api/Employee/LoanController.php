<?php

namespace App\Http\Controllers\Api\Employee;

use App\Models\Loan;
use App\Models\User;
use App\Notifications\LoanRequestNotification;
use App\Notifications\LoanStatusNotification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LoanController extends  Controller
{
    public function store(Request $request)
    {
        $employee = $request->user();
        $branch_manager = User::where('type', 'branch-manager')->first();

        $loan = Loan::create([
            'employee_id' => $employee->id,
            'branch_manager_id' => $branch_manager->id,
            'amount' => $request->amount,
            'reason' => $request->reason,
        ]);

        $branch_manager->notify(new LoanRequestNotification($loan));

        return response()->json(['message' => 'Loan request sent successfully'], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'branch-manager') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(Loan::with('employee')->get());
    }

    public function approve($id)
    {
        $loan = Loan::findOrFail($id);
        $loan->status = 'approved';
        $loan->save();

        $loan->employee->notify(new LoanStatusNotification($loan));

        return response()->json(['message' => 'Loan approved']);
    }

    public function reject($id)
    {
        $loan = Loan::findOrFail($id);
        $loan->status = 'rejected';
        $loan->save();

        $loan->employee->notify(new LoanStatusNotification($loan));

        return response()->json(['message' => 'Loan rejected']);
    }
}
