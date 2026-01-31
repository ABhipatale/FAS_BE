<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FaceDescriptorController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\CompanyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::middleware('api')->group(function () {
    // User registration endpoint
    Route::post('/register', [UserController::class, 'register']);
    
    // User login endpoint
    Route::post('/login', [UserController::class, 'login'])->name('login');
    
    // Company registration endpoint
    Route::post('/companies/register', [CompanyController::class, 'register']);
    
    // Test endpoint
    Route::get('/test', function () {
        return response()->json(['message' => 'API is working!']);
    });
});

// Protected routes
Route::middleware(['api', 'auth:sanctum'])->group(function () {
    // Get authenticated user
    Route::get('/me', [UserController::class, 'me']);
    
    // User logout endpoint
    Route::post('/logout', [UserController::class, 'logout']);
    
    // Get all users (admin only)
    Route::get('/users', [UserController::class, 'index']);
    // Create new user (admin only)
    Route::post('/users', [UserController::class, 'store']);
    
    // Get specific user
    Route::get('/users/{id}', [UserController::class, 'show']);
    
    // Face descriptor routes
    Route::post('/face-descriptor', [FaceDescriptorController::class, 'store']);
    Route::get('/face-descriptor', [FaceDescriptorController::class, 'getUserFaceDescriptor']);
    Route::delete('/face-descriptor', [FaceDescriptorController::class, 'destroy']);
    
    // Attendance routes
    Route::post('/attendance/mark', [AttendanceController::class, 'markAttendance']);
    Route::get('/attendance/user', [AttendanceController::class, 'getUserAttendance']);
    Route::get('/attendance/user/{userId}', [AttendanceController::class, 'getUserAttendanceById']);
    Route::get('/dashboard/stats', [AttendanceController::class, 'getDashboardStats']); // Dashboard statistics
    Route::get('/attendance/raw', [AttendanceController::class, 'getRawAttendanceData']); // Raw attendance data
    
    // Shift routes
    Route::resource('/shifts', ShiftController::class)->except(['create', 'edit']);
    
    // Company routes (protected)
    Route::get('/company/details', [CompanyController::class, 'getCompanyDetails']);
    Route::put('/company/update', [CompanyController::class, 'updateCompany']);
    Route::get('/companies', [CompanyController::class, 'index']); // Superadmin only
    Route::get('/companies/{id}', [CompanyController::class, 'show']); // Superadmin only
});
