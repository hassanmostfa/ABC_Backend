<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Traits\ChecksPermissions;

class AdminController extends Controller
{
    use ChecksPermissions;

    /**
     * Admin login with phone/email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string', // Can be email or phone
            'password' => 'required|string',
        ]);

        // Determine if login is email or phone
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        // Attempt to authenticate
        $credentials = [
            $loginField => $request->login,
            'password' => $request->password,
        ];

        // Try to find admin by email or phone
        $admin = Admin::where($loginField, $request->login)->first();
        
        if ($admin && Hash::check($request->password, $admin->password)) {
            // Check if admin is active
            if (!$admin->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'حسابك غير مفعل الرجاء التواصل مع الادارة'
                ], 401);
            }

            // Create Sanctum token
            $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;
            
            $admin->load('role');

            return response()->json([
                'success' => true,
                'data' => [
                    'admin' => $admin,
                    'token' => $token,
                    'token_type' => 'Bearer'
                ],
                'message' => 'تم تسجيل الدخول بنجاح'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'هذه البيانات غير صحيحة'
        ], 401);
    }

    /**
     * Admin logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if ($admin) {
            // Revoke current token
            $admin->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    /**
     * Get current authenticated admin profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $admin = $request->user();
        $admin->load('role');

        return response()->json([
            'success' => true,
            'data' => $admin,
            'message' => 'Profile retrieved successfully'
        ]);
    }

    /**
     * Get all roles for admin creation/editing.
     */
    public function getRoles(Request $request): JsonResponse
    {
        $roles = Role::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles
        ]);
    }

    /**
     * Display a listing of the admins.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10); // Default 10 items per page
        $query = Admin::with('role');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filter by role
        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        $admins = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $admins->items(),
            'pagination' => [
                'current_page' => $admins->currentPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
                'last_page' => $admins->lastPage(),
                'from' => $admins->firstItem(),
                'to' => $admins->lastItem(),
            ],
            'message' => 'Admins retrieved successfully'
        ]);
    }


    /**
     * Store a newly created admin in storage.
     */
    public function store(Request $request): JsonResponse
    {

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:admins,email',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:6',
                'role_id' => 'required|exists:roles,id',
            ]);

            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'is_active' => true,
            ]);

            $admin->load('role');

            // Log activity
            logAdminActivity('created', 'Admin', $admin->id);

            return response()->json([
                'success' => true,
                'data' => $admin,
                'message' => 'Admin created successfully'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified admin.
     */
    public function show(Request $request, $id): JsonResponse
    {

        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        $admin->load('role');

        return response()->json([
            'success' => true,
            'data' => $admin,
            'message' => 'Admin retrieved successfully'
        ]);
    }


    /**
     * Update the specified admin in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('admins')->ignore($admin->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'boolean',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role_id' => $request->role_id,
            'is_active' => $request->is_active ?? $admin->is_active,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $admin->update($updateData);
        $admin->load('role');

        // Log activity
        logAdminActivity('updated', 'Admin', $id);

        return response()->json([
            'success' => true,
            'data' => $admin,
            'message' => 'Admin updated successfully'
        ]);
    }

    /**
     * Remove the specified admin from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not found'
            ], 404);
        }

        $admin->delete();

        // Log activity
        logAdminActivity('deleted', 'Admin', $id);

        return response()->json([
            'success' => true,
            'message' => 'Admin deleted successfully'
        ]);
    }
}
