<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FaceDescriptor;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class FaceDescriptorController extends Controller
{
    /**
     * Store a newly created face descriptor in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $authUser = Auth::user();
            
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'user_id' => 'sometimes|integer|exists:users,id', // Allow specifying a different user (for admin use)
                'face_descriptor' => 'required|array', // Face descriptor should be an array
                'face_descriptor.*' => 'numeric', // Each element should be numeric (float)
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Ensure the face descriptor has exactly 128 elements (typical for face recognition)
            $faceDescriptorData = $request->face_descriptor;
            if (count($faceDescriptorData) !== 128) {
                return response()->json([
                    'success' => false,
                    'message' => 'Face descriptor must contain exactly 128 numeric values',
                ], 422);
            }
            
            // Ensure all values are floats
            foreach ($faceDescriptorData as $value) {
                if (!is_numeric($value)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'All face descriptor values must be numeric',
                    ], 422);
                }
            }

            // Determine which user to associate the face descriptor with
            // If user_id is provided and the authenticated user is an admin, use the specified user
            // Otherwise, use the authenticated user
            $targetUserId = $request->user_id ?? $authUser->id;
            
            // Authorization check: only admin/superadmin can register faces for other users
            if ($targetUserId != $authUser->id && !($authUser->isAdmin() || $authUser->isSuperAdmin())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to register face for another user',
                ], 403);
            }
            // Check if the target user already has a face descriptor
            $existingDescriptor = FaceDescriptor::where('user_id', $targetUserId)->first();
            if ($existingDescriptor) {
                // Update existing descriptor
                $existingDescriptor->update([
                    'face_descriptor' => $request->face_descriptor, // This is now a 128-element array of floats
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Face descriptor updated successfully',
                    'data' => [
                        'id' => $existingDescriptor->id,
                        'user_id' => $existingDescriptor->user_id,
                        'updated_at' => $existingDescriptor->updated_at,
                    ]
                ]);
            }
            
            // Create new face descriptor for the target user
            $faceDescriptor = FaceDescriptor::create([
                'user_id' => $targetUserId,
                'face_descriptor' => $request->face_descriptor, // This is now a 128-element array of floats
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Face descriptor saved successfully',
                'data' => [
                    'id' => $faceDescriptor->id,
                    'user_id' => $faceDescriptor->user_id,
                    'created_at' => $faceDescriptor->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save face descriptor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified face descriptor.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $faceDescriptor = FaceDescriptor::with('user')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $faceDescriptor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Face descriptor not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    /**
     * Get the authenticated user's face descriptor
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserFaceDescriptor(Request $request)
    {
        try {
            $authUser = Auth::user();
            
            // Check if a specific user_id is requested
            $userId = $request->query('user_id');
            
            // Authorization: only admin/superadmin can access other users' face descriptors
            if ($userId && $userId != $authUser->id && !($authUser->isAdmin() || $authUser->isSuperAdmin())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to access face descriptor for another user',
                ], 403);
            }
            
            $targetUserId = $userId ?? $authUser->id;
            
            $faceDescriptor = FaceDescriptor::where('user_id', $targetUserId)->first();
            
            if (!$faceDescriptor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Face descriptor not found for user'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $faceDescriptor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get face descriptor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove the specified face descriptor from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        try {
            $authUser = Auth::user();
            
            // Check if a specific user_id is requested
            $userId = $request->query('user_id');
            
            // Authorization: only admin/superadmin can delete other users' face descriptors
            if ($userId && $userId != $authUser->id && !($authUser->isAdmin() || $authUser->isSuperAdmin())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete face descriptor for another user',
                ], 403);
            }
            
            $targetUserId = $userId ?? $authUser->id;
            
            $faceDescriptor = FaceDescriptor::where('user_id', $targetUserId)->first();
            
            if (!$faceDescriptor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Face descriptor not found for user'
                ], 404);
            }
            
            $faceDescriptor->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Face descriptor deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete face descriptor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
