<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Register a new company with initial admin user
     */
    public function register(Request $request)
    {
        try {
            // Log the incoming request for debugging
            \Log::info('Company registration attempt', [
                'request_data' => $request->all(),
                'validation_errors' => []
            ]);
            
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'company_name' => 'required|string|max:255',
                'company_email' => 'required|email|unique:companies,email|max:255',
                'company_address' => 'nullable|string|max:500',
                'company_phone' => 'nullable|string|max:20',
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|unique:users,email|max:255',
                'admin_password' => 'required|string|min:6',
                'role' => ['required', Rule::in(['admin', 'superadmin'])], // Initial user must be admin or superadmin
            ]);

            if ($validator->fails()) {
                \Log::error('Company registration validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create the company first
            \Log::info('Creating company', [
                'name' => $request->company_name,
                'email' => $request->company_email
            ]);
            
            $company = Company::create([
                'name' => $request->company_name,
                'email' => $request->company_email,
                'address' => $request->company_address,
                'phone' => $request->company_phone,
                'status' => 'active'
            ]);

            \Log::info('Company created successfully', ['company_id' => $company->id]);

            // Create the admin user for this company
            \Log::info('Creating admin user', [
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'role' => $request->role,
                'company_id' => $company->id
            ]);
            
            $adminUser = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => $request->admin_password,
                'role' => $request->role,
                'company_id' => $company->id,
                'face_descriptor' => null,
            ]);

            \Log::info('Admin user created successfully', ['user_id' => $adminUser->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company and admin user registered successfully',
                'data' => [
                    'company' => $company,
                    'admin_user' => [
                        'id' => $adminUser->id,
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                        'role' => $adminUser->role,
                        'company_id' => $adminUser->company_id,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Company registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Company registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get company details for authenticated user
     */
    public function getCompanyDetails(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || !$user->company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user->company
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update company details
     */
    public function updateCompany(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || !$user->company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            // Only allow admin or superadmin to update company details
            if (!in_array($user->role, ['admin', 'superadmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update company details'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255|unique:companies,email,' . $user->company->id,
                'address' => 'nullable|string|max:500',
                'phone' => 'nullable|string|max:20',
                'logo' => 'nullable|string',
                'status' => 'sometimes|required|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->company->update($request->only(['name', 'email', 'address', 'phone', 'logo', 'status']));

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => $user->company
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all companies (for superadmin only)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Only superadmin can view all companies
            if ($user->role !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view all companies'
                ], 403);
            }

            $companies = Company::all();

            return response()->json([
                'success' => true,
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve companies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get company by ID (for superadmin only)
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Only superadmin can view specific company
            if ($user->role !== 'superadmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view company details'
                ], 403);
            }

            $company = Company::find($id);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $company
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}