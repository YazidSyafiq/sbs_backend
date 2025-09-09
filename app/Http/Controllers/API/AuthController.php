<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
        ], [
            'email.required' => 'Email Is Required.',
            'password.required' => 'Password Is Required.',
        ]);

        // Jika validasi gagal, kembalikan response dengan format yang diinginkan
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if(!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Email or Password.'
            ], 422);
        }

        $token = $user->createToken('Api Token')->plainTextToken;
        $role = $user->getRoleNames()->first();

        $user->setAttribute('token', $token);
        $user->setAttribute('role', $role);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => 'Login Successful.',
        ], 200);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout Successful',
        ], 200);
    }

    // Change Password
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required',
        ], [
            'current_password.required' => 'Current Password Is Required.',
            'new_password.required' => 'New Password Is Required.',
        ]);

        // Jika validasi gagal, kembalikan response dengan format yang diinginkan
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = $request->user();

        if(!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Current Password'
            ], 422);
        } elseif ($request->current_password == $request->new_password) {
            return response()->json([
                'success' => false,
                'message' => 'New Password Cannot Be The Same As Current Password'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password Changed Successfully',
        ], 200);
    }

    // FCM Token Register
    public function registerFCMToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required',
        ], [
            'fcm_token.required' => 'FCM Token Is Required.',
        ]);

        // Jika validasi gagal, kembalikan response dengan format yang diinginkan
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = $request->user();

        $user->update([
            'fcm_token' => $request->fcm_token
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FCM Token Registered',
        ], 200);
    }
}
