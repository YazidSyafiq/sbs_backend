<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\ProfileResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    // Profile
    public function getProfile()
    {
        $user = auth()->user();

        $role = $user->getRoleNames()->first();

        $user->setAttribute('role', $role);

        return response()->json([
            'status' => true,
            'data' => new ProfileResource($user),
            'message' => 'Success Get Profile',
        ]);
    }

    // Update Profile
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $oldPhoto = $user->image_url;

        // Handle base64 image upload
        if($request->has('image_url') && !empty($request->image_url))
        {
            $validator = Validator::make($request->all(), [
                'image_url' => 'required|string',
            ], [
                'image_url.required' => 'Image Is Required.',
            ]);

            // Jika validasi gagal, kembalikan response dengan format yang diinginkan
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            try {
                // Decode base64
                $imageData = $request->image_url;

                // Check if it's a data URL (data:image/jpeg;base64,...)
                if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                    // Extract the base64 encoded binary data
                    $imageData = substr($imageData, strpos($imageData, ',') + 1);
                    $type = strtolower($type[1]); // jpg, png, gif

                    // Validate image type
                    if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid Image Type. Only JPG, JPEG, PNG and GIF Are Allowed.',
                        ], 422);
                    }
                } else {
                    // If no data URL prefix, assume it's raw base64
                    $type = 'jpg'; // default type
                }

                // Decode base64
                $imageData = base64_decode($imageData);

                if ($imageData === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Base64 Image Data.',
                    ], 422);
                }

                // Generate unique filename
                $fileName = 'profile_' . $user->id . '_' . time() . '.' . $type;
                $path = 'profile/' . $fileName;

                // Store the image
                $stored = Storage::disk('public')->put($path, $imageData);

                if ($stored) {
                    $user->image_url = $path;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed To Store Image.',
                    ], 422);
                }

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error Processing Image: ' . $e->getMessage(),
                ], 422);
            }
        }

        // Update other fields
        $user->name = $request->name ? $request->name : $user->name;
        $user->email = $request->email ? $request->email : $user->email;
        $user->telepon = $request->phone ? $request->phone : $user->telepon;

        if($user->save())
        {
            // Delete old photo if exists and different from new one
            if($oldPhoto !== $user->image_url && $oldPhoto !== null)
            {
                Storage::disk('public')->delete($oldPhoto);
            }

            $role = $user->getRoleNames()->first();

            $user->setAttribute('role', $role);

            return response()->json([
                'success' => true,
                'data' => new ProfileResource($user),
                'message' => 'Profile Update Successful'
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Profile Update Failed',
            ], 422);
        }
    }
}
