<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Shop;
use App\Models\Branch;
use App\Services\AuditLogService;
use App\Services\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        private RefreshTokenService $refreshTokenService,
        private AuditLogService $audit,
    ) {}

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', $request->email)->with('shop', 'branch.shop')->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            $name = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null : null;
        $this->audit->log('User', $user?->id, 'LOGIN_FAILED', $request->email, $user?->shop_id, $user?->branch_id, 'Invalid credentials', $name);
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }
        if ($user->banned) return response()->json(['message' => 'Your account has been banned.'], 403);

        // Check shop ban / subscription
        if (!$user->hasRole('ROLE_SUPER_ADMIN') && $user->shop) {
            if ($user->shop->banned) abort(403, 'Your shop has been banned. Please contact support.');
            $sub = $user->shop->subscription;
            if ($sub && !$sub->isAccessAllowed()) {
                $msg = $sub->status === 'SUSPENDED'
                    ? 'Your shop has been suspended.'
                    : "Your subscription expired on {$sub->end_date}. Please renew.";
                abort(403, $msg);
            }
        }

        $user->update(['last_login' => now()]);
        $this->audit->log('User', $user->id, 'LOGIN', $user->email, $user->shop_id, $user->branch_id, null, trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null);

        $token = JWTAuth::fromUser($user);
        $refreshToken = $this->refreshTokenService->create($user);

        return response()->json($this->buildAuthResponse($user, $token, $refreshToken->token));
    }

    public function refreshToken(Request $request)
    {
        $request->validate(['refreshToken' => 'required|string']);
        $rt = $this->refreshTokenService->findValid($request->refreshToken);
        $user = $rt->user->load('shop', 'branch.shop');
        $token = JWTAuth::fromUser($user);
        return response()->json($this->buildAuthResponse($user, $token, $rt->token));
    }

    public function logout()
    {
        $user = auth('api')->user();
        if ($user) $this->refreshTokenService->deleteForUser($user);
        if ($user) {
            $this->audit->log('User', $user->id, 'LOGOUT', $user->email, $user->shop_id, $user->branch_id ?? null, null, trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null);
        }
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    public function updateProfile(Request $request)
    {
        $user = auth('api')->user();
        $data = $request->validate([
            'firstName' => 'nullable|string',
            'lastName' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'currentPassword' => 'nullable|string',
            'newPassword' => 'nullable|string|min:6',
        ]);
        if (isset($data['newPassword'])) {
            if (!isset($data['currentPassword']) || !Hash::check($data['currentPassword'], $user->password)) {
                abort(400, 'Current password is incorrect.');
            }
            $user->password = Hash::make($data['newPassword']);
        }
        if (isset($data['firstName'])) $user->first_name = $data['firstName'];
        if (isset($data['lastName'])) $user->last_name = $data['lastName'];
        if (isset($data['email'])) $user->email = $data['email'];
        $user->save();
        return response()->json(['message' => 'Profile updated successfully.']);
    }

    private function buildAuthResponse(User $user, string $token, string $refreshToken): array
    {
        $roles = $user->getRoleNames()->toArray();
        $itemLabel = null;
        if ($user->shop) $itemLabel = $user->shop->item_label;
        elseif ($user->branch?->shop) $itemLabel = $user->branch->shop->item_label;

        return [
            'token' => $token,
            'refreshToken' => $refreshToken,
            'userId' => $user->id,
            'email' => $user->email,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'roles' => $roles,
            'shopId' => $user->shop_id,
            'branchId' => $user->branch_id,
            'branchName' => $user->branch?->name,
            'itemLabel' => $itemLabel ?? 'Dress',
        ];
    }

    public function listStaff()
    {
        $user = auth('api')->user();
        $staff = User::where('shop_id', $user->shop_id)->orderByDesc('id')->get();
        return response()->json($staff);
    }

    public function banStaff($id)
    {
        $admin = auth('api')->user();
        $target = User::findOrFail($id);
        if ($admin->id === $target->id) abort(400, 'You cannot ban your own account.');
        if ($target->shop_id !== $admin->shop_id) abort(403, 'Not authorized.');
        $target->update(['banned' => true]);
        return response()->json(['message' => "Staff $id banned."]);
    }

    public function unbanStaff($id)
    {
        $admin = auth('api')->user();
        $target = User::findOrFail($id);
        if ($target->shop_id !== $admin->shop_id) abort(403, 'Not authorized.');
        $target->update(['banned' => false]);
        return response()->json(['message' => "Staff $id unbanned."]);
    }

    public function resetStaffPassword(Request $request, $id)
    {
        $request->validate(['password' => 'required|string|min:6']);
        $admin = auth('api')->user();
        $target = User::findOrFail($id);
        if ($target->shop_id !== $admin->shop_id) abort(403, 'Not authorized.');
        if ($admin->id === $target->id) abort(400, 'Use the profile page to change your own password.');
        $target->update(['password' => Hash::make($request->input('password'))]);
        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function registerAdmin(Request $request)
    {
        $admin = auth('api')->user();
        $data = $request->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'roles' => 'nullable|array',
            'branchId' => 'nullable|integer',
        ]);
        $roles = $data['roles'] ?? ['ROLE_STAFF'];
        $needsBranch = in_array('ROLE_BRANCH_MANAGER', $roles) || in_array('ROLE_STAFF', $roles);
        if ($needsBranch && !isset($data['branchId'])) abort(400, 'branchId is required for BRANCH_MANAGER and STAFF roles.');

        $user = User::create([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'shop_id' => $admin->shop_id,
            'branch_id' => $data['branchId'] ?? null,
        ]);
        $user->syncRoles($roles);
        $token = JWTAuth::fromUser($user);
        $rt = $this->refreshTokenService->create($user);
        return response()->json($this->buildAuthResponse($user, $token, $rt->token));
    }
}
