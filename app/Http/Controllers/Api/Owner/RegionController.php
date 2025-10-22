<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    // 📜 عرض كل المناطق
    public function index()
    {
        $regions = Region::with('manager')->get();

        return response()->json([
            'regions' => $regions
        ]);
    }

    // ➕ إنشاء منطقة جديدة
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:regions,name',
            'region_manager_id' => 'nullable|exists:users,id',
        ]);

        $region = Region::create($validated);

        return response()->json([
            'message' => 'Region created successfully',
            'region' => $region->load('manager')
        ], 201);
    }

    // 🧾 عرض تفاصيل منطقة واحدة
    public function show($id)
    {
        $region = Region::with('manager')->findOrFail($id);

        return response()->json($region);
    }

    // ✏️ تحديث منطقة
    public function update(Request $request, $id)
    {
        $region = Region::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:regions,name,' . $id,
            'region_manager_id' => 'sometimes|nullable|exists:users,id',
        ]);

        $region->update($validated);

        return response()->json([
            'message' => 'Region updated successfully',
            'region' => $region->load('manager')
        ]);
    }

    // 🗑️ حذف منطقة
    public function destroy($id)
    {
        $region = Region::findOrFail($id);
        $region->delete();

        return response()->json([
            'message' => 'Region deleted successfully'
        ]);
    }
}