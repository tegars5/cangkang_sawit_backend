<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProfileController extends Controller
{
    /**
     * Get current user profile
     */
    public function show()
    {
        $user = auth()->user();
        
        // Add full URL for profile photo
        if ($user->profile_photo && !str_starts_with($user->profile_photo, 'http')) {
            $user->profile_photo = Storage::disk('public')->url($user->profile_photo);
        }
        
        return response()->json($user);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string',
        ]);
        
        $user->update($request->only(['name', 'email', 'phone', 'address']));
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        
        $user = auth()->user();
        
        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }
        
        // Update password
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);
        
        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Upload profile photo with optimization
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);
        
        $user = auth()->user();
        
        // Delete old photo if exists
        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }
        
        // Optimize and save image
        $image = $request->file('photo');
        $filename = 'user_' . $user->id . '_' . time() . '.jpg';
        
        // Create image manager with GD driver
        $manager = new ImageManager(new Driver());
        
        // Read and resize image
        $img = $manager->read($image->getPathname());
        $img->scale(width: 400); // Resize to max width 400px, maintain aspect ratio
        
        // Encode to JPEG with 80% quality
        $encoded = $img->toJpeg(80);
        
        // Save to storage (profile_photos directory as requested by frontend)
        $path = 'profile_photos/' . $filename;
        Storage::disk('public')->put($path, (string) $encoded);
        
        // Update user
        $user->update(['profile_photo' => $path]);
        
        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated successfully',
            'data' => [
                'profile_picture' => $path,
                'profile_picture_url' => Storage::disk('public')->url($path)
            ]
        ]);
    }
}
