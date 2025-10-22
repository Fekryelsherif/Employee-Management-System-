<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private function registerUser(Request $request, $type)
    {
        $rules = [
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|confirmed|min:6',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
        ];

        // 👇 تحقق خاص لكل نوع مدير
        if ($type == 'branch_manager') {
            $rules['branch_id'] = 'required|exists:branches,id';
        }

        if ($type == 'region_manager') {
            $rules['region_id'] = 'required|exists:regions,id';
        }

        $fields = $request->validate($rules);

        $user = User::create([
            'fname' => $fields['fname'],
            'lname' => $fields['lname'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'phone' => $fields['phone'],
            'address' => $fields['address'],
            'department' => $fields['department'],
            'position' => $fields['position'],
            'branch_id' => $fields['branch_id'] ?? null,
            'region_id' => $fields['region_id'] ?? null,
            'type' => $type,
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    // 👇 نفس الدوال لكن تستخدم registerUser()
    public function registerEmployee(Request $request)
    {
        return $this->registerUser($request, 'employee');
    }

    public function registerBranchManager(Request $request)
    {
        return $this->registerUser($request, 'branch_manager');
    }

    public function registerRegionManager(Request $request)
    {
        return $this->registerUser($request, 'region_manager');
    }

    public function registerOwner(Request $request)
    {
        return $this->registerUser($request, 'owner');
    }



    //   Login
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

         $token = $user->createToken('auth_token', [$user->type])->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    //   Logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    //   Forgot Password (Send 6-digit code)
    // public function forgotPassword(Request $request)
    // {
    //     $request->validate(['email' => 'required|email']);

    //     $user = User::where('email', $request->email)->first();
    //     if (!$user) {
    //         return response()->json(['message' => 'Email not found'], 404);
    //     }

    //     $code = rand(100000, 999999);
    //     $expiresAt = Carbon::now()->addMinutes(10);

    //     DB::table('password_resets')->updateOrInsert(
    //         ['email' => $user->email],
    //         ['token' => $code, 'created_at' => $expiresAt]
    //     );

    //     Mail::raw("Your password reset code is: $code", function ($message) use ($user) {
    //         $message->to($user->email)->subject('Password Reset Code');
    //     });

    //     return response()->json(['message' => 'Reset code sent to email']);
    // }

    // //   Reset Password using code
    // public function resetPassword(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'code' => 'required',
    //         'password' => 'required|min:6|confirmed'
    //     ]);

    //     $reset = DB::table('password_resets')
    //         ->where('email', $request->email)
    //         ->where('token', $request->code)
    //         ->first();

    //     if (!$reset) {
    //         return response()->json(['message' => 'Invalid or expired code'], 400);
    //     }

    //     if (Carbon::parse($reset->created_at)->addMinutes(10)->isPast()) {
    //         return response()->json(['message' => 'Code expired'], 400);
    //     }

    //     $user = User::where('email', $request->email)->first();
    //     if (!$user) {
    //         return response()->json(['message' => 'User not found'], 404);
    //     }

    //     $user->update(['password' => Hash::make($request->password)]);
    //     DB::table('password_resets')->where('email', $request->email)->delete();

    //     return response()->json(['message' => 'Password reset successfully']);
    // }
}
