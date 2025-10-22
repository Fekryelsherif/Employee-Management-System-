<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegionManagerController extends Controller
{
    public function index()
    {
        // عرض كل مديري المناطق مع المنطقة التابعة لهم
        return User::where('type', 'region-manager')->with('region')->get();
    }

    public function store(Request $request)
    {
        // ✅ التحقق من البيانات
        $validated = $request->validate([
            'fname' => 'required|string|max:100',
            'lname' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'salary' => 'required|numeric|min:0',
            'department'=> 'required|string|min:0',
            'position'  => 'required|string|min:0',
            'region_id' => 'required|exists:regions,id',
        ]);

        // ✅ إنشاء مدير المنطقة
        $manager = User::create([
            'fname' => $validated['fname'],
            'lname' => $validated['lname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
            'address' => $validated['address'] ?? null,
            'salary' => $validated['salary'],
            'department'=>$validated['department'],
            'position'=>$validated['position'],
            'type' => 'region-manager',
        ]);

        // ✅ ربطه بالمنطقة
        $region = Region::findOrFail($validated['region_id']);
        $region->update(['region_manager_id' => $manager->id]);

        return response()->json([
            'message' => 'Region Manager created successfully',
            'manager' => $manager,
            'region' => $region
        ]);
    }

    public function show($id)
    {
        $manager = User::where('type', 'region-manager')->with('region')->findOrFail($id);
        return $manager;
    }

    public function update(Request $request, $id)
    {
        $manager = User::where('type', 'region-manager')->findOrFail($id);

        $validated = $request->validate([
            'fname' => 'sometimes|string|max:100',
            'lname' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $manager->id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'nullable|string|max:255',
            'salary' => 'sometimes|numeric|min:0',
            'department'=> 'sometimes|string|max:100',
            'position' => 'sometimes|string|max:100',
            'region_id' => 'sometimes|exists:regions,id',
        ]);

        $manager->update($validated);

        if (isset($validated['region_id'])) {
            $region = Region::findOrFail($validated['region_id']);
            $region->update(['region_manager_id' => $manager->id]);
        }

        return response()->json([
            'message' => 'Region Manager updated successfully',
            'manager' => $manager
        ]);
    }

    public function destroy($id)
    {
        $manager = User::where('type', 'region-manager')->findOrFail($id);

        Region::where('region_manager_id', $manager->id)->update(['region_manager_id' => null]);

        $manager->delete();

        return response()->json(['message' => 'Region Manager deleted successfully']);
    }
}