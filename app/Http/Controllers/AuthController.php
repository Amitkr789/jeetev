<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Fetch users (search + pagination)
    public function index(Request $request)
    {
        $query = Admin::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $users = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $users->items(),
            'meta'    => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    // Insert new user
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:admins,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:6|same:confirm_password',
            'role'     => 'required|in:admin,super_admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $admin = Admin::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'status'   => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'User added successfully.', 'data' => $admin], 201);
    }

    // Single user (edit modal pre-fill)
    public function show($id)
    {
        $admin = Admin::find($id);
        if (!$admin) return response()->json(['success' => false, 'message' => 'User not found.'], 404);

        return response()->json(['success' => true, 'data' => $admin]);
    }

    // Update user
    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);
        if (!$admin) return response()->json(['success' => false, 'message' => 'User not found.'], 404);

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:admins,email,' . $admin->id,
            'phone'    => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6|same:confirm_password',
            'role'     => 'required|in:admin,super_admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only('name', 'email', 'phone', 'role');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $admin->update($data);

        return response()->json(['success' => true, 'message' => 'User updated successfully.', 'data' => $admin]);
    }

    // Toggle block/unblock
    public function toggleStatus($id)
    {
        $admin = Admin::find($id);
        if (!$admin) return response()->json(['success' => false, 'message' => 'User not found.'], 404);

        if (Auth::guard('admin')->id() == $admin->id) {
            return response()->json(['success' => false, 'message' => 'You cannot block yourself.'], 403);
        }

        $admin->status = $admin->status === 'active' ? 'inactive' : 'active';
        $admin->save();

        return response()->json(['success' => true, 'status' => $admin->status]);
    }

    // Single delete
    public function destroy($id)
    {
        $admin = Admin::find($id);
        if (!$admin) return response()->json(['success' => false, 'message' => 'User not found.'], 404);

        $admin->delete();
        return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
    }

    // Multiselect (bulk) delete
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No users selected.'], 422);
        }

        Admin::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => count($ids) . ' user(s) deleted.']);
    }
     public function showLogin()
        {
            return view('pages.auth.login');
        }

    // Handle login (AJAX)
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (!$admin->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been blocked. Please contact the super admin.',
            ], 403);
        }

        Auth::guard('admin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'success'  => true,
            'message'  => 'Login successful.',
            'redirect' => route('auth.home.main'),
        ]);
    }

    // Logout (AJAX)
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success'  => true,
            'redirect' => route('login'),
        ]);
    }
}