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
    public function index()
    {
        try {
            $shifts = Shift::all();
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

            $shift = Shift::create([
                'shift_name' => $request->shift_name,
                'punch_in_time' => $request->punch_in_time,
                'punch_out_time' => $request->punch_out_time,
                'status' => $request->status
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
    public function show($id)
    {
        try {
            $shift = Shift::findOrFail($id);
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

            $shift->update([
                'shift_name' => $request->shift_name ?? $shift->shift_name,
                'punch_in_time' => $request->punch_in_time ?? $shift->punch_in_time,
                'punch_out_time' => $request->punch_out_time ?? $shift->punch_out_time,
                'status' => $request->status ?? $shift->status
            ]);

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