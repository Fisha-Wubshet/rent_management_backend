<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Shop;
use App\Models\Branch;
use App\Services\AuditLogService;
use App\Services\RefreshTokenService;
use App\Support\PhoneNumber;
use App\Support\RecoveryCode;
use App\Mail\WelcomeCredentialsMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        private RefreshTokenService $refreshTokenService,
        private AuditLogService $audit,
    ) {}

    public function login(Request $request)
    {
        // Accept either `phone` (+ optional `countryCode`, defaulting to +251) or
        // legacy `email`. During the migration to phone-first auth we support
        // both; the frontend will send phone.
        $request->validate([
            'phone'       => 'nullable|string',
            'countryCode' => 'nullable|string',
            'email'       => 'nullable|email',
            'password'    => 'required',
        ]);

        $user = null;
        $identifier = null;
        if ($request->filled('phone')) {
            $identifier = PhoneNumber::normalize($request->phone, $request->countryCode ?? '+251');
            if ($identifier) {
                $user = User::where('phone_number', $identifier)->with('shop', 'branch.shop')->first();
            }
        }
        if (!$user && $request->filled('email')) {
            $identifier = $request->email;
            $user = User::where('email', $identifier)->with('shop', 'branch.shop')->first();
        }
        if (!$identifier) {
            return response()->json(['message' => 'Phone number or email is required.'], 422);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            $name = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null : null;
            $this->audit->log('User', $user?->id, 'LOGIN_FAILED', $identifier, $user?->shop_id, $user?->branch_id, 'Invalid credentials', $name);
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
        $this->audit->log('User', $user->id, 'LOGIN', $user->phone_number ?? $user->email ?? ('user_' . $user->id), $user->shop_id, $user->branch_id, null, trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null);

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
            $this->audit->log('User', $user->id, 'LOGOUT', $user->phone_number ?? $user->email ?? ('user_' . $user->id), $user->shop_id, $user->branch_id ?? null, null, trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null);
        }
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * First-login password change: user was created by an admin with a
     * temporary password (must_change_password = true). They must set their
     * own password before they can use the app. Current password is NOT
     * required because they just proved auth via the temporary one to get
     * the JWT; asking again would be UX friction with no security benefit.
     */
    public function firstLoginChangePassword(Request $request)
    {
        $user = User::findOrFail(auth('api')->id());
        if (!$user->must_change_password) {
            abort(400, 'Not required — you can change your password from your profile.');
        }
        $data = $request->validate(['newPassword' => 'required|string|min:6']);

        $user->password = Hash::make($data['newPassword']);
        $user->must_change_password = false;
        $user->save();

        $this->audit->log('User', $user->id, 'FIRST_LOGIN_PASSWORD_SET', $user->phone_number ?? $user->email, $user->shop_id, $user->branch_id, null);

        return response()->json(['message' => 'Password set successfully.']);
    }

    public function updateProfile(Request $request)
    {
        $user = \App\Models\User::findOrFail(auth('api')->id());
        $data = $request->validate([
            'firstName' => 'nullable|string',
            'lastName' => 'nullable|string',
            'email' => 'nullable|email',
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
            'phoneNumber' => $user->phone_number,
            'countryCode' => $user->country_code,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'roles' => $roles,
            'shopId' => $user->shop_id,
            'branchId' => $user->branch_id,
            'branchName' => $user->branch?->name,
            'itemLabel' => $itemLabel ?? 'Dress',
            'hasRecoveryCode' => !empty($user->recovery_code_hash),
            'mustChangePassword' => (bool) $user->must_change_password,
        ];
    }

    public function listStaff()
    {
        $user = auth('api')->user();
        $staff = User::where('shop_id', $user->shop_id)
            ->with('roles:id,name', 'branch:id,name')
            ->orderByDesc('id')
            ->get();
        return response()->json($staff);
    }

    /**
     * Detailed profile + KPI stats for a single staff member.
     * Only accessible to a shop admin, and only for their own shop's users.
     *
     * Returns:
     *   {
     *     ...user fields...,
     *     stats: {
     *       totalBookings:     N,   // every booking they created (incl. cancelled)
     *       activeBookings:    N,   // CONFIRMED / PICKED_UP / RETURNED
     *       cancelledBookings: N,   // CANCELLED
     *     }
     *   }
     */
    public function showStaff($id)
    {
        $admin  = auth('api')->user();
        $target = User::with('roles:id,name', 'branch:id,name')->findOrFail($id);
        if ($target->shop_id !== $admin->shop_id) abort(403, 'Not authorized.');

        // Aggregate in one query so we don't run three separate COUNTs.
        $counts = \App\Models\Booking::where('created_by_id', $target->id)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
            ")->first();

        $total     = (int) ($counts?->total ?? 0);
        $cancelled = (int) ($counts?->cancelled ?? 0);

        $data = $target->toArray();
        $data['stats'] = [
            'totalBookings'     => $total,
            'activeBookings'    => max(0, $total - $cancelled),
            'cancelledBookings' => $cancelled,
        ];
        return response()->json($data);
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

    /**
     * Update a staff member's profile — name, phone, email, branch, role.
     * Only a shop admin can call this, and only for users within their own shop.
     */
    public function updateStaff(Request $request, $id)
    {
        $admin  = auth('api')->user();
        $target = User::findOrFail($id);
        if ($target->shop_id !== $admin->shop_id) abort(403, 'Not authorized.');
        if ($admin->id === $target->id) abort(400, 'Use the profile page to edit your own account.');

        $data = $request->validate([
            'firstName'   => 'sometimes|required|string',
            'lastName'    => 'sometimes|required|string',
            'phone'       => 'sometimes|required|string',
            'countryCode' => 'nullable|string',
            'email'       => 'nullable|email',
            'role'        => 'sometimes|required|in:ROLE_STAFF,ROLE_BRANCH_MANAGER',
            'branchId'    => 'sometimes|required|integer',
        ]);

        if (isset($data['phone'])) {
            $phone = PhoneNumber::normalize($data['phone'], $data['countryCode'] ?? $target->country_code ?? '+251');
            if (!PhoneNumber::isValid($phone)) abort(422, 'Invalid phone number.');
            $clash = User::where('phone_number', $phone)->where('id', '!=', $target->id)->exists();
            if ($clash) abort(422, 'A user with this phone number already exists.');
            $target->phone_number = $phone;
            $target->country_code = $data['countryCode'] ?? $target->country_code ?? '+251';
        }
        if (array_key_exists('email', $data)) {
            $target->email = $data['email'] ?: null;
        }
        if (isset($data['firstName'])) $target->first_name = $data['firstName'];
        if (isset($data['lastName']))  $target->last_name  = $data['lastName'];

        if (isset($data['branchId'])) {
            $branch = \App\Models\Branch::find($data['branchId']);
            if (!$branch || $branch->shop_id !== $admin->shop_id) abort(422, 'Invalid branch.');
            $target->branch_id = $branch->id;
        }

        $target->save();

        if (isset($data['role'])) {
            $target->syncRoles([$data['role']]);
        }

        $this->audit->log('User', $target->id, 'STAFF_UPDATED', $target->phone_number ?? $target->email, $target->shop_id, $target->branch_id, null);

        return response()->json($target->fresh(['roles', 'branch']));
    }

    public function resetStaffPassword(Request $request, $id)
    {
        $data = $request->validate([
            'password'      => 'required|string|min:6',
            'emailLanguage' => 'nullable|in:en,am',
        ]);
        $admin = auth('api')->user();
        $target = User::findOrFail($id);
        if ($target->shop_id !== $admin->shop_id) abort(403, 'Not authorized.');
        if ($admin->id === $target->id) abort(400, 'Use the profile page to change your own password.');

        $plainPassword = $data['password'];
        $target->update([
            'password'             => Hash::make($plainPassword),
            'must_change_password' => true,   // force them to pick their own on next login
        ]);

        // Fire-and-forget email if we have an address on file.
        $hasEmail = !empty($target->email);
        if ($hasEmail) {
            $email    = $target->email;
            $emailLang = $data['emailLanguage'] ?? 'en';
            dispatch(function () use ($target, $email, $plainPassword, $emailLang) {
                try {
                    // Reset flow — no recovery-code rotation here (recovery code stays put).
                    Mail::to($email)->send(new WelcomeCredentialsMail($target, $plainPassword, null, $emailLang, true));
                } catch (\Throwable $e) {
                    Log::warning('password-reset email failed for ' . $email . ': ' . $e->getMessage());
                }
            })->afterResponse();
        }

        return response()->json([
            'message'       => 'Password reset successfully.',
            'plainPassword' => $plainPassword,
            'emailQueued'   => $hasEmail,
        ]);
    }

    public function registerAdmin(Request $request)
    {
        $admin = auth('api')->user();
        $data = $request->validate([
            'phone'         => 'required|string',
            'countryCode'   => 'nullable|string',
            'email'         => 'nullable|email',
            'password'      => 'required|min:6',
            'firstName'     => 'required|string',
            'lastName'      => 'required|string',
            'roles'         => 'nullable|array',
            'branchId'      => 'nullable|integer',
            'emailLanguage' => 'nullable|in:en,am',
        ]);

        $countryCode = $data['countryCode'] ?? '+251';
        $phone = PhoneNumber::normalize($data['phone'], $countryCode);
        if (!PhoneNumber::isValid($phone)) abort(422, 'Invalid phone number.');

        if (User::where('phone_number', $phone)->exists()) {
            abort(422, 'A user with this phone number already exists.');
        }

        $roles = $data['roles'] ?? ['ROLE_STAFF'];
        $needsBranch = in_array('ROLE_BRANCH_MANAGER', $roles) || in_array('ROLE_STAFF', $roles);
        if ($needsBranch && !isset($data['branchId'])) abort(400, 'branchId is required for BRANCH_MANAGER and STAFF roles.');

        // Managers and shop admins get a recovery code; staff do not (their
        // shop admin resets their password).
        $issueRecoveryCode = in_array('ROLE_SHOP_ADMIN', $roles) || in_array('ROLE_BRANCH_MANAGER', $roles);
        $plainRecoveryCode = $issueRecoveryCode ? RecoveryCode::generate() : null;
        $plainPassword     = $data['password'];
        $emailLang         = $data['emailLanguage'] ?? 'en';

        $user = User::create([
            'first_name'           => $data['firstName'],
            'last_name'            => $data['lastName'],
            'email'                => $data['email'] ?? null,
            'phone_number'         => $phone,
            'country_code'         => $countryCode,
            'password'             => Hash::make($plainPassword),
            'recovery_code_hash'   => $plainRecoveryCode ? Hash::make($plainRecoveryCode) : null,
            'must_change_password' => true,
            'shop_id'              => $admin->shop_id,
            'branch_id'            => $data['branchId'] ?? null,
        ]);
        $user->syncRoles($roles);
        $token = JWTAuth::fromUser($user);
        $rt = $this->refreshTokenService->create($user);

        // Fire-and-forget welcome email — dispatched after the HTTP response
        // so the caller doesn't wait on SMTP and delivery failures don't
        // affect the create-staff result.
        $hasEmail = !empty($user->email);
        if ($hasEmail) {
            $email = $user->email;
            dispatch(function () use ($user, $email, $plainPassword, $plainRecoveryCode, $emailLang) {
                try {
                    Mail::to($email)->send(new WelcomeCredentialsMail($user, $plainPassword, $plainRecoveryCode, $emailLang));
                } catch (\Throwable $e) {
                    Log::warning('welcome-credentials email failed for ' . $email . ': ' . $e->getMessage());
                }
            })->afterResponse();
        }

        $response = $this->buildAuthResponse($user, $token, $rt->token);
        // The plain password + recovery code are returned exactly once, right
        // after creation, so the calling admin can share them with the new user.
        $response['plainPassword'] = $plainPassword;
        $response['emailQueued']   = $hasEmail;
        if ($plainRecoveryCode) $response['recoveryCode'] = $plainRecoveryCode;
        return response()->json($response);
    }
}
