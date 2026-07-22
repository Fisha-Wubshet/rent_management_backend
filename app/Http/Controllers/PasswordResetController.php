<?php

namespace App\Http\Controllers;

use App\Mail\PasswordOtpMail;
use App\Mail\RecoveryCodeRegeneratedMail;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RefreshTokenService;
use App\Support\PhoneNumber;
use App\Support\RecoveryCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PasswordResetController extends Controller
{
    public function __construct(
        private RefreshTokenService $refreshTokenService,
        private AuditLogService $audit,
    ) {}

    /**
     * Reset password using the user's recovery code.
     *
     * Body: { phone, countryCode?, recoveryCode, newPassword }
     * Returns: { message }
     *
     * The recovery code is intentionally NOT rotated here — the same code
     * remains valid so shop admins don't have to save a new one on every
     * reset. Users can rotate it manually from Settings when they want to.
     */
    public function resetWithRecoveryCode(Request $request)
    {
        $data = $request->validate([
            'phone'        => 'required|string',
            'countryCode'  => 'nullable|string',
            'recoveryCode' => 'required|string',
            'newPassword'  => 'required|string|min:6',
        ]);

        $phone = PhoneNumber::normalize($data['phone'], $data['countryCode'] ?? '+251');
        $user  = User::where('phone_number', $phone)->first();
        $normCode = RecoveryCode::normalize($data['recoveryCode']);

        if (!$user || !$normCode || !$user->recovery_code_hash || !Hash::check($normCode, $user->recovery_code_hash)) {
            $this->audit->log('User', $user?->id, 'PASSWORD_RESET_FAILED', $phone, $user?->shop_id, $user?->branch_id, 'Invalid recovery code');
            return response()->json(['message' => 'Invalid recovery code.'], 401);
        }

        $user->password = Hash::make($data['newPassword']);
        $user->save();

        // Invalidate all existing refresh tokens so old sessions can't survive
        // a password reset done by an attacker.
        $this->refreshTokenService->deleteForUser($user);

        $this->audit->log('User', $user->id, 'PASSWORD_RESET', $phone, $user->shop_id, $user->branch_id, 'via recovery code');

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Send a 6-digit OTP to the user's email if they have one on file.
     * Rate-limited: at most one request every 60 seconds per phone.
     *
     * Body: { phone, countryCode? }
     * Returns: { message } — always the same message whether or not the user
     * exists, to avoid leaking which phones are registered.
     */
    public function requestEmailOtp(Request $request)
    {
        $data = $request->validate([
            'phone'       => 'required|string',
            'countryCode' => 'nullable|string',
        ]);

        $phone = PhoneNumber::normalize($data['phone'], $data['countryCode'] ?? '+251');
        $user  = $phone ? User::where('phone_number', $phone)->first() : null;

        // Rate limit: one OTP per minute per phone (whether user exists or not).
        $throttleKey = "pwd-otp-req:$phone";
        if (Cache::has($throttleKey)) {
            return response()->json(['message' => 'Please wait before requesting another code.'], 429);
        }
        Cache::put($throttleKey, 1, now()->addSeconds(60));

        // Send only if the user exists AND has an email on file.
        if ($user && !empty($user->email)) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("pwd-otp:{$user->id}", Hash::make($otp), now()->addMinutes(10));
            try {
                Mail::to($user->email)->send(new PasswordOtpMail($user, $otp));
            } catch (\Throwable $e) {
                Log::warning('Failed to send password OTP email: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'If your account has an email on file, a code has been sent.',
            'hasEmail' => (bool) ($user?->email),
        ]);
    }

    /**
     * Verify the OTP and set a new password.
     *
     * Body: { phone, countryCode?, otp, newPassword }
     */
    public function verifyEmailOtp(Request $request)
    {
        $data = $request->validate([
            'phone'       => 'required|string',
            'countryCode' => 'nullable|string',
            'otp'         => 'required|string|size:6',
            'newPassword' => 'required|string|min:6',
        ]);

        $phone = PhoneNumber::normalize($data['phone'], $data['countryCode'] ?? '+251');
        $user  = $phone ? User::where('phone_number', $phone)->first() : null;

        $cacheKey = $user ? "pwd-otp:{$user->id}" : null;
        $stored   = $cacheKey ? Cache::get($cacheKey) : null;

        if (!$user || !$stored || !Hash::check($data['otp'], $stored)) {
            $this->audit->log('User', $user?->id, 'PASSWORD_RESET_FAILED', $phone, $user?->shop_id, $user?->branch_id, 'Invalid email OTP');
            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        Cache::forget($cacheKey);

        $user->password = Hash::make($data['newPassword']);
        $user->save();
        $this->refreshTokenService->deleteForUser($user);

        $this->audit->log('User', $user->id, 'PASSWORD_RESET', $phone, $user->shop_id, $user->branch_id, 'via email OTP');

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Regenerate the caller's recovery code. The old one is invalidated.
     * Only allowed for shop admin / branch manager / super admin — staff never
     * get one (they're reset by their shop admin instead).
     *
     * Requires the caller's current password (defence-in-depth: even if
     * someone briefly gets access to a logged-in session, they can't
     * silently rotate the recovery code without knowing the password).
     *
     * If the account has an email on file, we also fire a notification
     * email — the owner will see an unexpected rotation immediately.
     */
    public function regenerateRecoveryCode(Request $request)
    {
        $user = auth('api')->user();
        if (!$user->hasAnyRole(['ROLE_SUPER_ADMIN', 'ROLE_SHOP_ADMIN', 'ROLE_BRANCH_MANAGER'])) {
            abort(403, 'Recovery codes are only available for managers and admins.');
        }

        $data = $request->validate(['currentPassword' => 'required|string']);
        if (!Hash::check($data['currentPassword'], $user->password)) {
            $this->audit->log('User', $user->id, 'RECOVERY_CODE_REGENERATE_FAILED', $user->phone_number ?? $user->email, $user->shop_id, $user->branch_id, 'Wrong current password');
            return response()->json(['message' => 'Current password is incorrect.'], 401);
        }

        $newCode = RecoveryCode::generate();
        $user->recovery_code_hash = Hash::make($newCode);
        $user->save();

        $this->audit->log('User', $user->id, 'RECOVERY_CODE_REGENERATED', $user->phone_number ?? $user->email, $user->shop_id, $user->branch_id, null);

        // Fire-and-forget notification email so the owner sees rotations
        // even if they weren't the one who triggered them.
        if (!empty($user->email)) {
            try {
                Mail::to($user->email)->send(new RecoveryCodeRegeneratedMail($user, $newCode));
            } catch (\Throwable $e) {
                Log::warning('Failed to send recovery-code regenerated email: ' . $e->getMessage());
            }
        }

        return response()->json(['recoveryCode' => $newCode]);
    }
}
