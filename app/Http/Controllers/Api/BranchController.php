<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Models\City;
use App\Models\Region;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * ✅ التحقق من أن المستخدم له صلاحية (مالك أو مدير منطقة)
     */
    private function authorizeAccess()
    {
        $user = auth()->user();
        if (!in_array($user->type, ['owner', 'region-manager'])) {
            abort(403, 'Access denied. Only owner or region-manager can manage branches.');
        }
    }

    /**
     * 📋 عرض جميع الفروع
     */
    public function index()
    {
        $this->authorizeAccess();

        $branches = Branch::with(['city', 'region', 'manager'])->get();

        return response()->json(['branches' => $branches]);
    }

    /**
     * ➕ إنشاء فرع جديد
     */
    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'unique:branches,name|required|string|max:255|unique:branches,name',
            'city_id' => 'required|exists:cities,id',
            'region_id' => 'required|exists:regions,id',
            'branch_manager_id' => 'nullable|exists:users,id',
            'address' => 'required|string|max:255',
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Branch created successfully',
            'branch' => $branch->load(['city', 'region', 'manager']),
            'address' => $validated['address'],
        ], 201);
    }

    /**
     * 🔍 عرض فرع معين
     */
    public function show($id)
    {
        $this->authorizeAccess();

        $branch = Branch::with(['city', 'region', 'manager'])->findOrFail($id);

        return response()->json($branch);
    }

    /**
     * ✏️ تعديل فرع
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAccess();

        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:branches,name,' . $branch->id,
            'city_id' => 'sometimes|exists:cities,id',
            'region_id' => 'sometimes|exists:regions,id',
            'branch_manager_id' => 'nullable|exists:users,id',
            'address' => 'sometimes|string|max:255',
        ]);

        $branch->update($validated);

        return response()->json([
            'message' => 'Branch updated successfully',
            'branch' => $branch->load(['city', 'region', 'manager']),
            'address' => $validated['address'],
        ]);
    }

    /**
     * 🗑️ حذف فرع
     */
    public function destroy($id)
    {
        $this->authorizeAccess();

        $branch = Branch::findOrFail($id);
        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }
}