<?php

namespace App\Http\Controllers;

use App\Mail\NewRegistrationRequestMail;
use App\Mail\RegistrationConfirmMail;
use App\Mail\RegistrationRejectedMail;
use App\Mail\WelcomeCredentialsMail;
use App\Models\Branch;
use App\Models\PendingRegistration;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\PhoneNumber;
use App\Support\RecoveryCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PendingRegistrationController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    // ─── Public: create a new registration request ───────────────────────
    public function submit(Request $request)
    {
        $data = $request->validate([
            'shopName'   => 'required|string|max:120',
            'itemLabel'  => 'nullable|string|max:40',
            'firstName'  => 'required|string|max:60',
            'lastName'   => 'required|string|max:60',
            'phone'      => 'required|string',
            'countryCode'=> 'nullable|string|max:8',
            'email'      => 'nullable|email|max:120',
            'telegramUsername' => 'nullable|string|max:60',
            'city'       => 'nullable|string|max:80',
            'language'   => 'nullable|in:en,am',
        ]);

        $countryCode = $data['countryCode'] ?? '+251';
        $phone = PhoneNumber::normalize($data['phone'], $countryCode);
        if (!PhoneNumber::isValid($phone)) abort(422, 'Invalid phone number.');

        // Phone is the identity — a real active user on this phone can't re-register.
        if (User::where('phone_number', $phone)->exists()) {
            abort(409, 'An account already exists for this phone number. Please sign in instead.');
        }
        // NOTE: email is intentionally NOT unique in this system — multiple accounts
        // may share an email (a business owner running several shops, etc.).

        // Soft-dedupe: if the same phone already has an open request, don't create a new row — just
        // tell the caller it's already queued.
        $existing = PendingRegistration::where('phone_number', $phone)
            ->whereIn('status', [PendingRegistration::STATUS_UNCONFIRMED, PendingRegistration::STATUS_PENDING])
            ->first();
        if ($existing) {
            return response()->json([
                'status'  => 'already_queued',
                'message' => 'You already have a pending request. We\'ll respond within 24 hours.',
                'hasEmail' => (bool) $existing->email,
            ], 200);
        }

        $hasEmail = !empty($data['email']);
        $reg = PendingRegistration::create([
            'shop_name'      => $data['shopName'],
            'item_label'     => $data['itemLabel'] ?? null,
            'first_name'     => $data['firstName'],
            'last_name'      => $data['lastName'],
            'phone_number'   => $phone,
            'country_code'   => $countryCode,
            'email'             => $data['email'] ?? null,
            // Normalize telegram: strip leading @ and any t.me/ prefix so we store just the handle.
            'telegram_username' => $this->normalizeTelegram($data['telegramUsername'] ?? null),
            'city'              => $data['city'] ?? null,
            'email_language' => $data['language'] ?? 'en',
            'source_ip'      => $request->ip(),
            'status'         => $hasEmail ? PendingRegistration::STATUS_UNCONFIRMED : PendingRegistration::STATUS_PENDING,
            'confirm_token'  => $hasEmail ? Str::random(48) : null,
        ]);

        // Fire-and-forget: confirmation email OR (if no email) the super-admin notification.
        if ($hasEmail) {
            $confirmUrl = rtrim(config('app.url'), '/') . '/register/confirm/' . $reg->confirm_token;
            dispatch(function () use ($reg, $confirmUrl) {
                try { Mail::to($reg->email)->send(new RegistrationConfirmMail($reg, $confirmUrl)); }
                catch (\Throwable $e) { Log::warning('registration-confirm email failed: ' . $e->getMessage()); }
            })->afterResponse();
        } else {
            $this->notifySuperAdmin($reg);
        }

        $this->audit->log('PendingRegistration', $reg->id, 'CREATED', $phone, null, null,
            $hasEmail ? 'Awaiting email confirmation' : 'Queued (no email)', $reg->shop_name);

        return response()->json([
            'status'   => 'submitted',
            'hasEmail' => $hasEmail,
            'message'  => $hasEmail
                ? 'Check your inbox — click the link to confirm your registration.'
                : 'Thanks — we\'ll contact you within 24 hours.',
        ], 201);
    }

    // ─── Public: confirm the email link ──────────────────────────────────
    public function confirm(string $token)
    {
        $reg = PendingRegistration::where('confirm_token', $token)->first();
        if (!$reg) abort(404, 'This confirmation link is invalid or has expired.');

        if ($reg->status === PendingRegistration::STATUS_UNCONFIRMED) {
            $reg->update([
                'status'       => PendingRegistration::STATUS_PENDING,
                'confirmed_at' => now(),
            ]);
            $this->notifySuperAdmin($reg);
            $this->audit->log('PendingRegistration', $reg->id, 'CONFIRMED',
                $reg->phone_number, null, null, null, $reg->shop_name);
        }

        return response()->json([
            'status'    => $reg->status,   // will be PENDING / APPROVED / REJECTED depending on where it is
            'shopName'  => $reg->shop_name,
            'firstName' => $reg->first_name,
        ]);
    }

    // ─── Super-admin: list ────────────────────────────────────────────────
    public function index(Request $request)
    {
        $status = $request->query('status'); // pending | unconfirmed | approved | rejected | all
        $q = PendingRegistration::query()->orderByDesc('created_at');
        if ($status && strtolower($status) !== 'all') {
            $q->where('status', strtoupper($status));
        }
        return response()->json($q->paginate($request->query('size', 25)));
    }

    // Compact counts for the sidebar badge (super-admin only)
    public function counts()
    {
        return response()->json([
            'pending'     => PendingRegistration::where('status', PendingRegistration::STATUS_PENDING)->count(),
            'unconfirmed' => PendingRegistration::where('status', PendingRegistration::STATUS_UNCONFIRMED)->count(),
        ]);
    }

    // ─── Super-admin: approve ────────────────────────────────────────────
    // Two modes:
    //   quick=1 → uses safe 14-day trial defaults (1 branch, Basic plan). One click.
    //   quick=0 → expects full setup-shop payload (branch info, subscription, etc.).
    public function approve(Request $request, int $id)
    {
        $reg = PendingRegistration::findOrFail($id);
        if ($reg->status === PendingRegistration::STATUS_APPROVED) {
            abort(409, 'This registration has already been approved.');
        }
        if ($reg->status === PendingRegistration::STATUS_REJECTED) {
            abort(409, 'This registration was rejected. Reset it or ask the user to re-register.');
        }

        $quick = filter_var($request->input('quick', false), FILTER_VALIDATE_BOOLEAN);
        $data = $request->validate([
            'firstBranchName'    => 'nullable|string|max:80',
            'firstBranchAddress' => 'nullable|string|max:200',
            'durationDays'       => 'nullable|integer|min:1',
            'maxBranches'        => 'nullable|integer|min:1',
            'isTrial'            => 'nullable|boolean',
            'amountPaid'         => 'nullable|numeric|min:0',
            'planName'           => 'nullable|string|max:60',
            'subscriptionNotes'  => 'nullable|string|max:500',
        ]);

        // Sensible defaults (used entirely by Quick Approve).
        $firstBranchName    = $data['firstBranchName']    ?? 'Main Branch';
        $firstBranchAddress = $data['firstBranchAddress'] ?? $reg->city;
        $durationDays       = $data['durationDays']       ?? 14;
        $maxBranches        = $data['maxBranches']        ?? 1;
        $isTrial            = $quick ? true : (bool) ($data['isTrial'] ?? true);
        $amountPaid         = $isTrial ? 0 : ($data['amountPaid'] ?? 0);
        $planName           = $data['planName']           ?? 'Basic';
        $subscriptionNotes  = $data['subscriptionNotes']  ?? ($quick ? 'Quick-approved trial' : null);

        $plainPassword      = RecoveryCode::generate();   // strong random; user must change on first login
        $plainRecoveryCode  = RecoveryCode::generate();

        $shop = DB::transaction(function () use (
            $reg, $firstBranchName, $firstBranchAddress, $durationDays, $maxBranches,
            $isTrial, $amountPaid, $planName, $subscriptionNotes,
            $plainPassword, $plainRecoveryCode
        ) {
            $shop = Shop::create([
                'name'       => $reg->shop_name,
                'address'    => $reg->city,
                'item_label' => $reg->item_label ?: 'Item',
            ]);
            $branch = Branch::create([
                'name'    => $firstBranchName,
                'address' => $firstBranchAddress,
                'shop_id' => $shop->id,
            ]);
            $user = User::create([
                'first_name'           => $reg->first_name,
                'last_name'            => $reg->last_name,
                'email'                => $reg->email,
                'phone_number'         => $reg->phone_number,
                'country_code'         => $reg->country_code,
                'password'             => Hash::make($plainPassword),
                'recovery_code_hash'   => Hash::make($plainRecoveryCode),
                'must_change_password' => true,
                'shop_id'              => $shop->id,
            ]);
            $user->assignRole('ROLE_SHOP_ADMIN');

            $start = now();
            Subscription::create([
                'shop_id'      => $shop->id,
                'plan_name'    => $planName,
                'amount_paid'  => $amountPaid,
                'start_date'   => $start,
                'end_date'     => $start->copy()->addDays($durationDays),
                'max_branches' => $maxBranches,
                'is_trial'     => $isTrial,
                'notes'        => $subscriptionNotes,
            ]);

            // Link the pending row to the created shop.
            $reg->update([
                'status'              => PendingRegistration::STATUS_APPROVED,
                'reviewed_by_user_id' => auth('api')->id(),
                'reviewed_at'         => now(),
                'created_shop_id'     => $shop->id,
                'confirm_token'       => null,
            ]);

            return compact('shop', 'branch', 'user');
        });

        // Fire-and-forget welcome credentials email — reuses the existing template.
        if (!empty($reg->email)) {
            $email = $reg->email;
            $lang  = $reg->email_language ?: 'en';
            $user  = $shop['user'];
            dispatch(function () use ($user, $email, $plainPassword, $plainRecoveryCode, $lang) {
                try {
                    Mail::to($email)->send(new WelcomeCredentialsMail($user, $plainPassword, $plainRecoveryCode, $lang));
                } catch (\Throwable $e) {
                    Log::warning('welcome-credentials email failed after approval: ' . $e->getMessage());
                }
            })->afterResponse();
        }

        $this->audit->log('PendingRegistration', $reg->id, 'APPROVED',
            auth('api')->user()?->phone_number ?? auth('api')->user()?->email ?? ('user_' . auth('api')->id()),
            $shop['shop']->id, null,
            $quick ? 'Quick-approved (14-day trial)' : 'Approved (custom)',
            trim((auth('api')->user()?->first_name ?? '') . ' ' . (auth('api')->user()?->last_name ?? '')) ?: null);

        return response()->json([
            'message'       => 'Approved. Welcome email dispatched.',
            'shop'          => $shop['shop'],
            'user'          => $shop['user'],
            'plainPassword' => $plainPassword,
            'recoveryCode'  => $plainRecoveryCode,
            'emailQueued'   => !empty($reg->email),
        ]);
    }

    // ─── Super-admin: reject ─────────────────────────────────────────────
    public function reject(Request $request, int $id)
    {
        $reg = PendingRegistration::findOrFail($id);
        if ($reg->status === PendingRegistration::STATUS_APPROVED) {
            abort(409, 'This registration was already approved and cannot be rejected.');
        }

        $data = $request->validate([
            'reason' => 'required|string|in:' . implode(',', PendingRegistration::REJECT_REASONS),
            'note'   => 'nullable|string|max:500',
        ]);

        $reg->update([
            'status'              => PendingRegistration::STATUS_REJECTED,
            'reviewed_by_user_id' => auth('api')->id(),
            'reviewed_at'         => now(),
            'rejection_reason'    => $data['reason'],
            'rejection_note'      => $data['note'] ?? null,
            'confirm_token'       => null,
        ]);

        // Fire-and-forget rejection email (only if we have an address).
        if (!empty($reg->email)) {
            $emailReg = $reg;
            dispatch(function () use ($emailReg) {
                try { Mail::to($emailReg->email)->send(new RegistrationRejectedMail($emailReg)); }
                catch (\Throwable $e) { Log::warning('registration-rejected email failed: ' . $e->getMessage()); }
            })->afterResponse();
        }

        $this->audit->log('PendingRegistration', $reg->id, 'REJECTED',
            auth('api')->user()?->phone_number ?? auth('api')->user()?->email ?? ('user_' . auth('api')->id()),
            null, null, $data['reason'] . ($data['note'] ?? '' ? ': ' . $data['note'] : ''),
            trim((auth('api')->user()?->first_name ?? '') . ' ' . (auth('api')->user()?->last_name ?? '')) ?: null);

        return response()->json(['message' => 'Registration rejected.']);
    }

    // ─── Super-admin: delete a rejected row ──────────────────────────────
    public function destroy(int $id)
    {
        $reg = PendingRegistration::findOrFail($id);
        if ($reg->status === PendingRegistration::STATUS_APPROVED) {
            abort(409, 'Approved rows are archived — delete the shop instead if needed.');
        }
        $reg->delete();
        return response()->json(['message' => 'Removed.']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Accepts "@handle", "handle", "https://t.me/handle" or "t.me/handle"
     * and returns the bare handle (no @, no URL). Returns null for empty input.
     */
    private function normalizeTelegram(?string $raw): ?string
    {
        if ($raw === null) return null;
        $s = trim($raw);
        if ($s === '') return null;
        // Strip protocol + t.me/ prefixes
        $s = preg_replace('#^https?://#i', '', $s);
        $s = preg_replace('#^(www\.)?(t\.me|telegram\.me)/#i', '', $s);
        $s = ltrim($s, '@');
        // Telegram allows a-z 0-9 _  (5–32 chars, but we don't hard-enforce length here)
        $s = preg_replace('/[^A-Za-z0-9_]/', '', $s);
        return $s === '' ? null : $s;
    }

    private function notifySuperAdmin(PendingRegistration $reg): void
    {
        $superAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'ROLE_SUPER_ADMIN'))
            ->whereNotNull('email')
            ->pluck('email')
            ->all();
        if (empty($superAdmins)) return;

        $reviewUrl = rtrim(config('app.url'), '/') . '/admin/registrations';
        dispatch(function () use ($reg, $superAdmins, $reviewUrl) {
            try {
                Mail::to($superAdmins)->send(new NewRegistrationRequestMail($reg, $reviewUrl));
            } catch (\Throwable $e) {
                Log::warning('new-registration-request notification email failed: ' . $e->getMessage());
            }
        })->afterResponse();
    }
}
