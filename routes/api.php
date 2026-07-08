<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PaymentController;

/*
 * URL structure mirrors Spring Boot (port 8080, no context-path).
 * Vue frontend baseURL = http://localhost:8080
 * Routes WITHOUT /api prefix: /login, /auth/*, /register-admin, /shop/staff/*
 * Routes WITH /api prefix:    /api/branches, /api/bookings, /api/items, etc.
 */

// --- Public ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);

// --- Authenticated ---
Route::middleware('jwt.auth')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Shop-admin: staff management (no /api prefix in Vue calls)
    Route::middleware('role:ROLE_SHOP_ADMIN')->group(function () {
        Route::post('/register-admin', [AuthController::class, 'registerAdmin']);
        Route::get('/shop/staff', [AuthController::class, 'listStaff']);
        Route::put('/shop/staff/{id}/ban', [AuthController::class, 'banStaff']);
        Route::put('/shop/staff/{id}/unban', [AuthController::class, 'unbanStaff']);
        Route::put('/shop/staff/{id}/reset-password', [AuthController::class, 'resetStaffPassword']);
    });

    // Prefixed /api group (matches Vue calls like /api/branches, /api/bookings, etc.)
    Route::prefix('/api')->group(function () {

        // Profile update — Vue: PUT /api/users/me
        Route::put('/users/me', [AuthController::class, 'updateProfile']);

        // Shop admin only
        Route::middleware('role:ROLE_SHOP_ADMIN')->group(function () {
            Route::apiResource('/branches', BranchController::class);
        });

        // Shop admin + branch manager
        Route::middleware('role:ROLE_SHOP_ADMIN|ROLE_BRANCH_MANAGER')->group(function () {
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
            Route::get('/audit-logs/today-summary', [AuditLogController::class, 'todaySummary']);
            Route::get('/audit-logs/export', [AuditLogController::class, 'export']);
            Route::get('/audit-logs/by-staff/{email}', [AuditLogController::class, 'byStaff'])->where('email', '.+');
            Route::get('/audit-logs/staff-summary/{email}', [AuditLogController::class, 'staffSummary'])->where('email', '.+');
            Route::get('/payments', [PaymentController::class, 'index']);
        });

        // Super admin
        Route::middleware('role:ROLE_SUPER_ADMIN')->group(function () {
            Route::post('/setup-shop', [SuperAdminController::class, 'setupShop']);
            Route::get('/shops', [SuperAdminController::class, 'listShops']);
            Route::get('/shops/{id}', [SuperAdminController::class, 'showShop']);
            Route::put('/shops/{id}', [SuperAdminController::class, 'updateShop']);
            Route::delete('/shops/{id}', [SuperAdminController::class, 'deleteShop']);
            Route::put('/shops/{id}/ban', [SuperAdminController::class, 'banShop']);
            Route::put('/shops/{id}/unban', [SuperAdminController::class, 'unbanShop']);
            Route::get('/users', [SuperAdminController::class, 'listUsers']);
            Route::put('/users/{id}/ban', [SuperAdminController::class, 'banUser']);
            Route::put('/users/{id}/unban', [SuperAdminController::class, 'unbanUser']);
            Route::get('/analytics', [SuperAdminController::class, 'analytics']);
            Route::get('/audit-logs/all', [AuditLogController::class, 'all']);
            Route::apiResource('/subscriptions', SubscriptionController::class)->except(['show', 'destroy']);
            Route::get('/subscriptions/shop/{shopId}', [SubscriptionController::class, 'byShop']);
            Route::put('/subscriptions/{id}/renew', [SubscriptionController::class, 'renew']);
            Route::patch('/subscriptions/{id}', [SubscriptionController::class, 'patch']);
            Route::put('/subscriptions/{id}/suspend', [SubscriptionController::class, 'suspend']);
            Route::put('/subscriptions/{id}/unsuspend', [SubscriptionController::class, 'unsuspend']);
            Route::post('/register', [AuthController::class, 'registerAdmin']);
        });

        // All authenticated users — Bookings
        Route::post('/bookings/create-invoice', [BookingController::class, 'createInvoice']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/dashboard', [BookingController::class, 'dashboard']);
        Route::get('/bookings/today-pickups', [BookingController::class, 'todayPickups']);
        Route::get('/bookings/due-today', [BookingController::class, 'dueToday']);
        Route::get('/bookings/overdue', [BookingController::class, 'overdue']);
        Route::get('/bookings/maintenance-blocks', [BookingController::class, 'maintenanceBlocks']);
        Route::get('/bookings/search/customer/{phone}', [BookingController::class, 'searchByPhone']);
        Route::get('/bookings/history/{code}', [BookingController::class, 'historyByCode']);
        Route::get('/bookings/customer/{customerId}', [BookingController::class, 'customerBookings']);
        Route::post('/bookings/set-unavailable', [BookingController::class, 'setUnavailable']);
        Route::delete('/bookings/make-available', [BookingController::class, 'makeAvailable']);
        Route::post('/bookings/items/{itemId}/return', [BookingController::class, 'markItemReturned']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::put('/bookings/{id}', [BookingController::class, 'update']);
        Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
        Route::delete('/bookings/{id}', [BookingController::class, 'cancel']);
        Route::post('/bookings/{id}/status', [BookingController::class, 'updateStatus']);
        Route::post('/bookings/{id}/pay', [BookingController::class, 'payDue']);
        Route::post('/bookings/{id}/pickup', [BookingController::class, 'pickup']);
        Route::post('/bookings/{id}/complete-return', [BookingController::class, 'completeReturn']);
        Route::post('/bookings/{id}/release-cleaning', [BookingController::class, 'releaseCleaning']);
        Route::get('/bookings/{id}/invoice/pdf', [BookingController::class, 'downloadInvoicePdf']);
        Route::get('/bookings/{id}/change-logs', [BookingController::class, 'changeLogs']);
        Route::get('/bookings/{id}/payments', [BookingController::class, 'payments']);
        Route::patch('/bookings/{id}/change-items', [BookingController::class, 'changeItems']);

        // Customers (specific routes before resource to avoid conflicts)
        Route::get('/customers/autocomplete', [CustomerController::class, 'autocomplete']);
        Route::get('/customers/phone/{phone}', [CustomerController::class, 'byPhone']);
        Route::get('/customers/blacklisted', [CustomerController::class, 'blacklisted']);
        Route::get('/customers/{id}/stats', [CustomerController::class, 'stats']);
        Route::post('/customers/{id}/blacklist', [CustomerController::class, 'blacklist']);
        Route::post('/customers/{id}/unblacklist', [CustomerController::class, 'unblacklist']);
        Route::apiResource('/customers', CustomerController::class);

        // Items
        Route::post('/items/add', [ItemController::class, 'store']);
        Route::get('/items/all', [ItemController::class, 'index']);
        Route::get('/items/search/{code}', [ItemController::class, 'searchByCode']);
        Route::get('/items/{id}/detail', [ItemController::class, 'detail']);
        Route::get('/items/{id}/history', [ItemController::class, 'history']);
        Route::put('/items/{id}', [ItemController::class, 'update']);
        Route::delete('/items/{id}', [ItemController::class, 'destroy']);
        Route::post('/items/{id}/image', [ItemController::class, 'uploadImage']);

        // Categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Reports
        Route::get('/reports/outstanding-balances', [ReportController::class, 'outstandingBalances']);
        Route::get('/reports/revenue/daily', [ReportController::class, 'dailyRevenue']);
        Route::get('/reports/revenue/monthly', [ReportController::class, 'monthlyRevenue']);
        Route::get('/reports/revenue/trend', [ReportController::class, 'revenueTrend']);
        Route::get('/reports/revenue/by-item', [ReportController::class, 'itemRevenue']);
        Route::get('/reports/branch-comparison', [ReportController::class, 'branchComparison']);
        Route::get('/reports/receivables-aging', [ReportController::class, 'receivablesAging']);
        Route::get('/reports/year-over-year', [ReportController::class, 'yearOverYear']);
        Route::get('/reports/inventory-utilization', [ReportController::class, 'inventoryUtilization']);
        Route::get('/reports/deposits', [ReportController::class, 'depositSummary']);

        // Audit logs
        Route::get('/audit-logs/mine', [AuditLogController::class, 'mine']);
        Route::get('/audit-logs/entity/{type}/{id}', [AuditLogController::class, 'byEntity']);
    });
});
