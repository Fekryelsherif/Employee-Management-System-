<?php
namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Target;

class EmployeeTargetController extends Controller
{
    public function index(Request $request) {
        $employee = $request->user();
        $targets = Target::with('services')->where('employee_id', $employee->id)->get()->map(function($t){
            return [
                'target' => $t,
                'total_assigned' => $t->totalAssigned(),
                'total_sold' => $t->totalSold(),
                'percent' => $t->percentCompleted(),
            ];
        });
        return response()->json($targets);
    }

    public function show(Request $request, $id) {
        $employee = $request->user();
        $target = Target::with('services')->where('employee_id',$employee->id)->findOrFail($id);
        return response()->json([
            'target'=>$target,
            'total_assigned' => $target->totalAssigned(),
            'total_sold' => $target->totalSold(),
            'percent' => $target->percentCompleted(),
        ]);
    }
}
