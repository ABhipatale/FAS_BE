<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // If super admin, can optionally see all shifts or filter by company_id
            if ($user->isSuperAdmin() && $request->has('company_id')) {
                $shifts = Shift::where('company_id', $request->company_id)->get();
            } elseif (!$user->isSuperAdmin()) {
                // Regular admins can only see shifts from their company
                $shifts = Shift::where('company_id', $user->company_id)->get();
            } else {
                // Super admin without company_id filter - show all shifts
                $shifts = Shift::all();
            }
            
            return response()->json([
                'success' => true,
                'data' => $shifts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving shifts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'shift_name' => 'required|string|max:255|unique:shifts,shift_name',
                'punch_in_time' => 'required|date_format:H:i',
                'punch_out_time' => 'required|date_format:H:i|after:punch_in_time',
                'status' => 'required|in:active,inactive'
            ]);

            // Determine company_id
            $company_id = $request->company_id ?? $request->user()->company_id;
            
            // Validate that the user has access to this company
            if (!$request->user()->isSuperAdmin() && $company_id != $request->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to create shift for this company'
                ], 403);
            }
            
            $shift = Shift::create([
                'shift_name' => $request->shift_name,
                'punch_in_time' => $request->punch_in_time,
                'punch_out_time' => $request->punch_out_time,
                'status' => $request->status,
                'company_id' => $company_id
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Shift created successfully',
                'data' => $shift
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating shift: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $shift = Shift::findOrFail($id);
            
            // Check if user has permission to view this shift
            if (!$user->isSuperAdmin() && $shift->company_id != $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this shift'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'data' => $shift
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shift not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'shift_name' => 'sometimes|required|string|max:255|unique:shifts,shift_name,' . $id,
                'punch_in_time' => 'sometimes|required|date_format:H:i',
                'punch_out_time' => 'sometimes|required|date_format:H:i|after:punch_in_time',
                'status' => 'sometimes|required|in:active,inactive'
            ]);

            $shift = Shift::findOrFail($id);
            
            // Check if user has permission to update this shift
            $user = $request->user();
            if (!$user->isSuperAdmin() && $shift->company_id != $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this shift'
                ], 403);
            }
            
            // Handle company_id update (only super admin can change company)
            $updateData = [
                'shift_name' => $request->shift_name ?? $shift->shift_name,
                'punch_in_time' => $request->punch_in_time ?? $shift->punch_in_time,
                'punch_out_time' => $request->punch_out_time ?? $shift->punch_out_time,
                'status' => $request->status ?? $shift->status
            ];
            
            if ($user->isSuperAdmin() && $request->has('company_id')) {
                $updateData['company_id'] = $request->company_id;
            }

            $shift->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Shift updated successfully',
                'data' => $shift
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating shift: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $shift = Shift::findOrFail($id);
            
            // Check if user has permission to delete this shift
            $user = request()->user();
            if (!$user->isSuperAdmin() && $shift->company_id != $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this shift'
                ], 403);
            }

            // Check if any users are assigned to this shift
            if ($shift->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete shift because users are assigned to it'
                ], 400);
            }

            $shift->delete();

            return response()->json([
                'success' => true,
                'message' => 'Shift deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting shift: ' . $e->getMessage()
            ], 500);
        }
    }
}