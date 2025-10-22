<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BranchManagerController extends Controller
{
    // ✅ عرض جميع مديري الفروع
    public function index()
    {
        return User::where('type', 'branch-manager')
            ->with('managedBranch')
            ->get();
    }

    // ✅ إنشاء مدير فرع جديد (فقط المالك أو مدير المنطقة)
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->type, ['owner', 'region-manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'fname' => 'required|string|max:100',
            'lname' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'salary' => 'required|numeric|min:0',
            'department' => 'required|string|max:100',
            'position' => 'required|string|max:100',
            'branch_id' => 'required|exists:branches,id',
            'region_id' => 'required|exists:regions,id',
        ]);

        $manager = User::create([
            'fname' => $validated['fname'],
            'lname' => $validated['lname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?? null,
            'salary' => $validated['salary'],
            'department' => $validated['department'],
            'position' => $validated['position'],
            'branch_id' => $validated['branch_id'],
            'region_id' => $validated['region_id'],
            'type' => 'branch-manager',
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);
        $branch->update(['branch_manager_id' => $manager->id]);

        return response()->json([
            'message' => 'Branch Manager created successfully',
            'manager' => $manager,
            'branch' => $branch
        ]);
    }

    // ✅ عرض مدير فرع معين
    public function show($id)
    {
        $manager = User::where('type', 'branch-manager')
            ->with('managedBranch')
            ->findOrFail($id);

        return response()->json($manager);
    }

    // ✅ تحديث بيانات مدير فرع (فقط المالك أو مدير المنطقة)
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if (!in_array($user->type, ['owner', 'region-manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $manager = User::where('type', 'branch-manager')->findOrFail($id);

        $validated = $request->validate([
            'fname' => 'sometimes|string|max:100',
            'lname' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $manager->id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'nullable|string|max:255',
            'salary' => 'sometimes|numeric|min:0',
            'department' => "sometimes|string|max:100",
            'position' => "sometimes|string|max:100",
            'branch_id' => 'sometimes|exists:branches,id',
            'region_id' => 'sometimes|exists:regions,id',
        ]);

        $manager->update($validated);

        if (isset($validated['branch_id'])) {
            $branch = Branch::findOrFail($validated['branch_id']);
            $branch->update(['branch_manager_id' => $manager->id] , ['region_id' => $validated['region_id']]);
            
        }

        return response()->json([
            'message' => 'Branch Manager updated successfully',
            'manager' => $manager
        ]);
    }

    // ✅ حذف مدير فرع (فقط المالك أو مدير المنطقة)
    public function destroy($id)
    {
        $user = auth()->user();

        if (!in_array($user->type, ['owner', 'region-manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $manager = User::where('type', 'branch-manager')->findOrFail($id);

        Branch::where('branch_manager_id', $manager->id)
            ->update(['branch_manager_id' => null]);

        $manager->delete();

        return response()->json(['message' => 'Branch Manager deleted successfully']);
    }
}