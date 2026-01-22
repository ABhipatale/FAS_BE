<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FaceDescriptor;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Mark attendance by recognizing face
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAttendance(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'face_descriptor' => 'required|array',
                'face_descriptor.*' => 'numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $incomingFaceDescriptor = $request->face_descriptor;
            
            // Ensure the incoming face descriptor has exactly 128 elements
            if (count($incomingFaceDescriptor) !== 128) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incoming face descriptor must contain exactly 128 numeric values',
                ], 422);
            }
            
            // Get all stored face descriptors from the database
            $storedFaceDescriptors = FaceDescriptor::with('user')->get();
            
            if ($storedFaceDescriptors->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No face descriptors registered in the system',
                ], 404);
            }
            
            // Compare the incoming face descriptor with all stored descriptors
            $matchedUser = null;
            $minDistance = PHP_FLOAT_MAX;
            
            foreach ($storedFaceDescriptors as $storedDescriptor) {
                $storedDescriptorArray = $storedDescriptor->face_descriptor;
                
                // Calculate Euclidean distance between descriptors
                $distance = $this->calculateEuclideanDistance($incomingFaceDescriptor, $storedDescriptorArray);
                
                // If this distance is smaller than the current minimum, update the match
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    // Set threshold for considering a match (this can be adjusted based on accuracy needs)
                    if ($distance < 0.6) { // Threshold can be tuned based on testing
                        $matchedUser = $storedDescriptor->user;
                    }
                }
            }
            
            if ($matchedUser) {
                // Check if attendance for today already exists
                $today = Carbon::today();
                $existingAttendance = Attendance::where('user_id', $matchedUser->id)
                    ->whereDate('date', $today)
                    ->first();

                if ($existingAttendance) {
                    // If already punched in today, just update punch-out time
                    if (!$existingAttendance->punch_out_time) {
                        $existingAttendance->update([
                            'punch_out_time' => Carbon::now(),
                            'status' => 'present'
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'message' => 'Attendance updated successfully',
                            'data' => [
                                'user' => [
                                    'id' => $matchedUser->id,
                                    'name' => $matchedUser->name,
                                    'email' => $matchedUser->email,
                                ],
                                'action' => 'punch_out',
                                'punch_out_time' => Carbon::now()->format('Y-m-d H:i:s'),
                                'confidence' => round((1 - $minDistance) * 100, 2),
                                'distance' => round($minDistance, 4),
                            ]
                        ]);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Attendance already recorded for today',
                        ], 409);
                    }
                } else {
                    // Create new attendance record with punch-in time
                    $punchInTime = Carbon::now();
                    $attendance = Attendance::create([
                        'user_id' => $matchedUser->id,
                        'date' => $today,
                        'punch_in_time' => $punchInTime,
                        'status' => 'present'
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Attendance marked successfully',
                        'data' => [
                            'user' => [
                                'id' => $matchedUser->id,
                                'name' => $matchedUser->name,
                                'email' => $matchedUser->email,
                            ],
                            'action' => 'punch_in',
                            'punch_in_time' => $punchInTime->format('Y-m-d H:i:s'),
                            'confidence' => round((1 - $minDistance) * 100, 2),
                            'distance' => round($minDistance, 4),
                        ]
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching face found in the system',
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calculate Euclidean distance between two face descriptors
     *
     * @param array $desc1
     * @param array $desc2
     * @return float
     */
    private function calculateEuclideanDistance($desc1, $desc2)
    {
        if (count($desc1) !== count($desc2)) {
            throw new \InvalidArgumentException('Face descriptors must have the same length');
        }
        
        $sum = 0;
        for ($i = 0; $i < count($desc1); $i++) {
            $diff = $desc1[$i] - $desc2[$i];
            $sum += $diff * $diff;
        }
        
        return sqrt($sum);
    }
    
    /**
     * Get attendance records for a user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getUserAttendance(Request $request)
    {
        try {
            $user = Auth::user();
            
            // In a real implementation, you would have an Attendance model to track attendance records
            // For now, we'll just return a placeholder response
            
            return response()->json([
                'success' => true,
                'message' => 'Attendance records retrieved successfully',
                'data' => [
                    'user_id' => $user->id,
                    'attendance_records' => [] // Placeholder for actual attendance records
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get attendance records for a specific user by ID
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userId
     * @return \Illuminate\Http\Response
     */
    public function getUserAttendanceById(Request $request, $userId)
    {
        try {
            // Check if the authenticated user has permission to view this user's attendance
            $authUser = Auth::user();
            
            // Allow if the user is viewing their own attendance or is an admin/superadmin
            if ($authUser->id != $userId && !in_array($authUser->role, ['admin', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this user\'s attendance',
                ], 403);
            }
            
            // Find the user whose attendance is being requested
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }
            
            // Extract month and year from query parameters, default to current month/year
            $month = $request->query('month', date('n')); // Current month
            $year = $request->query('year', date('Y')); // Current year
            
            // In a real implementation, you would have an Attendance model to track attendance records
            // For now, we'll return a placeholder response with mock data
            
            // Generate mock attendance data for the specified month
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $monthlyAttendance = [];
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $dateObj = new \DateTime($date);
                
                // Skip weekends (Saturday and Sunday)
                $dayOfWeek = $dateObj->format('w');
                if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                    continue;
                }
                
                // Randomly assign attendance status for demo purposes
                $statuses = ['present', 'absent', 'late', 'leave'];
                $randomStatus = $statuses[array_rand($statuses)];
                
                $attendanceRecord = [
                    'date' => $date,
                    'status' => $randomStatus,
                    'punch_in_time' => null,
                    'punch_out_time' => null,
                    'hours_worked' => null,
                ];
                
                // If present or late, add punch times
                if ($randomStatus === 'present' || $randomStatus === 'late') {
                    $punchInHour = rand(7, 10); // Between 7-10 AM
                    $punchInMinute = rand(0, 59);
                    $attendanceRecord['punch_in_time'] = sprintf('%02d:%02d', $punchInHour, $punchInMinute);
                    
                    $punchOutHour = rand(16, 18); // Between 4-6 PM
                    $punchOutMinute = rand(0, 59);
                    $attendanceRecord['punch_out_time'] = sprintf('%02d:%02d', $punchOutHour, $punchOutMinute);
                    
                    // Calculate hours worked
                    $inTime = new \DateTime($attendanceRecord['punch_in_time']);
                    $outTime = new \DateTime($attendanceRecord['punch_out_time']);
                    $interval = $inTime->diff($outTime);
                    $attendanceRecord['hours_worked'] = $interval->format('%h.%i');
                }
                
                $monthlyAttendance[] = $attendanceRecord;
            }
            
            // Generate mock weekly and yearly data
            $weeklyAttendance = [];
            $yearlyAttendance = [];
            
            return response()->json([
                'success' => true,
                'message' => 'Attendance records retrieved successfully',
                'data' => [
                    'user' => $user,
                    'monthly' => $monthlyAttendance,
                    'weekly' => $weeklyAttendance,
                    'yearly' => $yearlyAttendance,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance records',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get dashboard statistics
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $authUser = Auth::user();
            
            // Get total employees
            $totalEmployees = User::count();
            
            // Check if attendances table exists
            $attendanceTableExists = \Schema::hasTable('attendances');
            
            if ($attendanceTableExists) {
                // Get today's attendance stats
                $today = Carbon::today();
                $todayPresentCount = Attendance::whereDate('date', $today)
                    ->where('status', 'present')
                    ->count();
                
                $todayAbsentCount = $totalEmployees - $todayPresentCount;
                
                // Get weekly attendance data (last 7 days)
                $startDate = Carbon::now()->subDays(6); // Last 7 days including today
                $endDate = Carbon::now();
                
                $weeklyAttendanceData = [];
                $currentDate = clone $startDate;
                
                while ($currentDate <= $endDate) {
                    $dateStr = $currentDate->format('Y-m-d');
                    $dayName = $currentDate->format('D');
                    
                    $presentCount = Attendance::whereDate('date', $dateStr)
                        ->where('status', 'present')
                        ->count();
                    
                    $absentCount = $totalEmployees - $presentCount;
                    
                    $weeklyAttendanceData[] = [
                        'name' => $dayName,
                        'present' => $presentCount,
                        'absent' => $absentCount,
                        'date' => $dateStr
                    ];
                    
                    $currentDate->addDay();
                }
                
                // Get today's attendance list
                $todayAttendanceList = Attendance::whereDate('date', $today)
                    ->with('user')
                    ->get()
                    ->map(function ($attendance) {
                        return [
                            'id' => $attendance->id,
                            'name' => $attendance->user->name,
                            'shift' => $attendance->user->shift ? $attendance->user->shift->name : 'N/A',
                            'punchIn' => $attendance->punch_in_time ? $attendance->punch_in_time->format('h:i A') : 'N/A',
                            'punchOut' => $attendance->punch_out_time ? $attendance->punch_out_time->format('h:i A') : 'N/A',
                            'status' => $attendance->status
                        ];
                    });
            } else {
                // Fallback to mock data if table doesn't exist
                $todayPresentCount = 0;
                $todayAbsentCount = $totalEmployees;
                
                $weeklyAttendanceData = [
                    ['name' => 'Mon', 'present' => 0, 'absent' => $totalEmployees, 'date' => Carbon::now()->subDays(6)->format('Y-m-d')],
                    ['name' => 'Tue', 'present' => 0, 'absent' => $totalEmployees, 'date' => Carbon::now()->subDays(5)->format('Y-m-d')],
                    ['name' => 'Wed', 'present' => 0, 'absent' => $totalEmployees, 'date' => Carbon::now()->subDays(4)->format('Y-m-d')],
                    ['name' => 'Thu', 'present' => 0, 'absent' => $totalEmployees, 'date' => Carbon::now()->subDays(3)->format('Y-m-d')],
                    ['name' => 'Fri', 'present' => 0, 'absent' => $totalEmployees, 'date' => Carbon::now()->subDays(2)->format('Y-m-d')],
                    ['name' => 'Sat', 'present' => 0, 'absent' => $totalEmployees, 'date' => Carbon::now()->subDays(1)->format('Y-m-d')],
                    ['name' => 'Sun', 'present' => 0, 'absent' => $totalEmployees, 'date' => Carbon::now()->format('Y-m-d')],
                ];
                
                $todayAttendanceList = collect(); // Empty collection
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'stats' => [
                        'totalEmployees' => $totalEmployees,
                        'todayPresent' => $todayPresentCount,
                        'todayAbsent' => $todayAbsentCount
                    ],
                    'attendanceData' => $weeklyAttendanceData,
                    'attendanceList' => $todayAttendanceList
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get raw attendance data without processing
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getRawAttendanceData(Request $request)
    {
        try {
            $authUser = Auth::user();
            
            // Check if attendances table exists
            $attendanceTableExists = \Schema::hasTable('attendances');
            
            if (!$attendanceTableExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance table does not exist',
                    'data' => []
                ], 404);
            }
            
            // Get filter parameters
            $filter = $request->query('filter', 'today'); // today, yesterday, custom
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            
            $query = Attendance::with('user', 'user.shift');
            
            // Apply date filters
            if ($filter === 'today') {
                $query->whereDate('date', Carbon::today());
            } elseif ($filter === 'yesterday') {
                $query->whereDate('date', Carbon::yesterday());
            } elseif ($filter === 'custom' && $startDate && $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }
            
            // Get raw attendance records
            $attendanceRecords = $query->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Raw attendance data retrieved successfully',
                'data' => $attendanceRecords
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve raw attendance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
