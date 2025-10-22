<?php

namespace App\Http\Controllers\Api\BranchManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $branch_manager = $request->user();

        if ($branch_manager->type !== 'branch_manager') {
            return response()->json(['message' => 'غير مصرح لك بالوصول'], 403);
        }

        $employees = User::where('type', 'employee')
            ->where('branch_manager_id', $branch_manager->id)
            ->get(['id', 'fname', 'lname', 'email', 'phone', 'department', 'position','salary','commission_salary']);

        return response()->json([
            'message' => 'قائمة الموظفين التابعين لك',
            'employees' => $employees
        ]);
    }

    //  إضافة موظف جديد
    public function store(Request $request)
    {
        $branch_manager = $request->user();

        if ($branch_manager->type !== 'branch_manager') {
            return response()->json(['message' => 'غير مصرح لك بإضافة موظفين'], 403);
        }

        $data = $request->validate([
            'fname' => 'required|string',
            'lname' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'required|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'salary'   =>  'required | min:4',
            'branch_id' => 'required|exists:branches,id',
            
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['type'] = 'employee';
         $data['branch_id'] = $branch_manager->branch_id; // ✅ يتجاب تلقائي

        $employee = User::create($data);

        return response()->json([
            'message' => 'تم إضافة الموظف بنجاح',
            'employee' => $employee
        ], 201);
    }

    //  عرض بيانات موظف محدد
    public function show(Request $request, $id)
    {
        $branch_manager = $request->user();

        if ($branch_manager->type !== 'branch_manager') {
            return response()->json(['message' => 'غير مصرح لك بالوصول'], 403);
        }

        $employee = User::where('id', $id)
            ->where('branch_manager_id', $branch_manager->id)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'الموظف غير موجود أو لا يتبعك'], 404);
        }

        return response()->json($employee);
    }

    //  تعديل بيانات موظف
    public function update(Request $request, $id)
    {
        $branch_manager = $request->user();

        if ($branch_manager->type !== 'branch_manager') {
            return response()->json(['message' => 'غير مصرح لك بالتعديل'], 403);
        }

        $employee = User::where('id', $id)
            ->where('branch_manager_id', $branch_manager->id)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'الموظف غير موجود أو لا يتبعك'], 404);
        }

        $data = $request->validate([
            'fname' => 'sometimes|string',
            'lname' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $employee->id,
            'password' => 'sometimes|string|min:6',
            'phone' => 'sometimes|string',
            'department' => 'sometimes|string',
            'position' => 'sometimes|string',
            'salary'   =>  'sometimes | min:4 ',
            'br anch_id' => 'required|exists:branches,id',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $employee->update($data);

        return response()->json([
            'message' => 'تم تحديث بيانات الموظف بنجاح',
            'employee' => $employee
        ]);
    }

    //  حذف موظف
    public function destroy(Request $request, $id)
    {
        $branch_manager = $request->user();

        if ($branch_manager->type !== 'branch_manager') {
            return response()->json(['message' => 'غير مصرح لك بالحذف'], 403);
        }

        $employee = User::where('id', $id)
            ->where('branch_manager_id', $branch_manager->id)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'الموظف غير موجود أو لا يتبعك'], 404);
        }

        $employee->delete();

        return response()->json(['message' => 'تم حذف الموظف بنجاح']);
    }
}