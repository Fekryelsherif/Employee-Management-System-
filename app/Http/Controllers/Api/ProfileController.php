<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * 🧾 عرض بيانات المستخدم الحالي
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // استثناء الحقول الحساسة
        $filtered = collect($user)->except([
            'email_verified_at',
            'remember_token',
            'created_at',
            'updated_at'
        ]);

        return response()->json([
            'user' => $filtered
        ]);
    }

    /**
     * ✏️ تحديث بيانات المستخدم الحالي
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'fname' => 'sometimes|string|max:255',
            'lname' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'salary' => 'sometimes|numeric|min:0',
            'commission_salary' => 'sometimes|numeric|min:0',
            'password' => 'sometimes|string|min:6|confirmed'
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => collect($user)->except([
                'email_verified_at',
                'remember_token',
                'created_at',
                'updated_at'
            ])
        ]);
    }

    /**
     * 🗑️ حذف الحساب الحالي
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }
}