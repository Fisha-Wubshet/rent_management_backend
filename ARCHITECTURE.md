# RentFlow — Backend Architecture

## Table of Contents
1. [System Overview](#system-overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Authentication & Authorization](#authentication--authorization)
5. [Database Architecture](#database-architecture)
6. [Entity Relationship Diagram](#entity-relationship-diagram)
7. [Models & Relationships](#models--relationships)
8. [API Reference](#api-reference)
9. [Services Layer](#services-layer)
10. [Middleware](#middleware)
11. [Business Logic & Workflows](#business-logic--workflows)
12. [Audit Logging](#audit-logging)
13. [PDF Generation](#pdf-generation)
14. [Error Handling](#error-handling)
15. [Multi-tenancy Model](#multi-tenancy-model)

---

## System Overview

RentFlow is a **multi-tenant SaaS rental management platform**. Each tenant is a *Shop* — a business that rents out items (dresses, suits, cars, or any configurable item type). A shop can have multiple *Branches*, each with its own staff and inventory.

The backend is a **REST API** built with Laravel 12. It serves a Vue 3 single-page application. All state is managed server-side; the frontend is stateless and authenticates via JWT on every request.

**Key capabilities:**
- Multi-shop / multi-branch hierarchy with isolated data
- Rental booking lifecycle: create → pickup → return → cancel
- Real-time item availability checking with cleaning-gap support
- Maintenance blocks to mark items unavailable outside of bookings
- Customer management with blacklist support
- Security deposit tracking and partial deductions
- Subscription-based access gating per shop
- Full audit trail for every action
- Financial reports: revenue, receivables aging, inventory utilization, year-over-year

---

## Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Framework | Laravel | 13.x |
| Language | PHP | 8.2+ |
| Database | PostgreSQL | 15+ |
| Authentication | Tymon JWT Auth | 2.3 |
| Authorization | Spatie Laravel Permission | 8.0 |
| PDF Generation | barryvdh/laravel-dompdf | 3.1 |
| ORM | Eloquent (Laravel built-in) | — |
| Testing | PHPUnit | 12.5 |

---

## Project Structure

```
booking_system/
├── app/
│   ├── Http/
│   │   ├── Controllers/         # Request handlers (thin — business logic in services)
│   │   │   ├── AuthController.php
│   │   │   ├── BookingController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── ItemController.php
│   │   │   ├── BranchController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── ReportController.php
│   │   │   ├── AuditLogController.php
│   │   │   ├── SubscriptionController.php
│   │   │   └── SuperAdminController.php
│   │   └── Middleware/
│   │       ├── JwtMiddleware.php  # Token validation + ban check
│   │       └── RoleMiddleware.php # Role-based access control
│   ├── Models/                  # Eloquent models
│   ├── Services/                # Business logic
│   │   ├── AuditLogService.php
│   │   ├── BookingService.php
│   │   ├── PdfService.php
│   │   └── RefreshTokenService.php
│   └── Providers/
│       └── AppServiceProvider.php
├── database/
│   ├── migrations/              # All schema changes as migrations
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── RoleSeeder.php       # Seeds the 4 roles
├── resources/
│   └── views/pdf/
│       └── invoice.blade.php    # Blade template for PDF invoices
├── routes/
│   └── api.php                  # All API route definitions
└── config/
    ├── jwt.php
    ├── permission.php
    └── cors.php
```

---

## Authentication & Authorization

### JWT Flow

```
Client                          Server
  |                               |
  |-- POST /login --------------→ |  Validate email/password
  |                               |  Generate access token (60 min TTL)
  |                               |  Generate refresh token (7 days TTL, stored in DB)
  |← { token, refreshToken } ---- |
  |                               |
  |-- GET /api/... (Bearer) ----→ |  JwtMiddleware validates token
  |← { data } ------------------- |
  |                               |
  |-- POST /auth/refresh-token -→ |  Validate refresh token from DB
  |                               |  Issue new access token
  |← { token, refreshToken } ---- |
  |                               |
  |-- POST /auth/logout --------→ |  Revoke refresh token from DB
  |← 200 OK -------------------- |
```

Refresh tokens are stored in the `refresh_tokens` table with an expiry. When an access token expires the frontend automatically calls `/auth/refresh-token` with the stored refresh token to get a new pair without re-logging-in.

### Roles

There are exactly four roles, seeded by `RoleSeeder`:

| Role | Scope | Capabilities |
|------|-------|-------------|
| `ROLE_SUPER_ADMIN` | Platform-wide | Manage all shops, users, subscriptions, analytics |
| `ROLE_SHOP_ADMIN` | Their shop | All branches, staff, items, bookings, reports, audit logs |
| `ROLE_BRANCH_MANAGER` | Their branch | Bookings, items, customers, reports, audit logs |
| `ROLE_STAFF` | Their branch | Bookings, items, customers, own activity log |

Role assignment is enforced in two places:
1. **`RoleMiddleware`** — middleware groups in `routes/api.php` restrict entire route groups
2. **Controller-level checks** — some endpoints do finer-grained checks (e.g., branch managers can only see their own branch data)

### User-to-Tenant Binding

- A `ROLE_SHOP_ADMIN` has `shop_id` set, `branch_id` null.
- A `ROLE_BRANCH_MANAGER` and `ROLE_STAFF` have both `shop_id` null and `branch_id` set. Their `shop_id` is derived via `user->branch->shop_id`.
- A `ROLE_SUPER_ADMIN` has both null.

Every controller derives `shopId` with this helper pattern:
```php
private function shopId(): int {
    $user = auth('api')->user();
    return $user->shop_id ?? $user->branch->shop_id;
}
```

---

## Database Architecture

### Table Overview

| Table | Purpose |
|-------|---------|
| `shops` | Top-level tenants |
| `branches` | Physical locations within a shop |
| `users` | Staff accounts — belong to a shop (admin) or branch (manager/staff) |
| `categories` | Item grouping per shop |
| `items` | Rentable inventory per branch |
| `customers` | Customer profiles per shop |
| `bookings` | Rental reservations |
| `booking_items` | Junction: which items are in each booking |
| `booking_change_logs` | History of every modification to a booking |
| `audit_logs` | System-wide action audit trail |
| `item_blocks` | Maintenance / unavailability windows per item |
| `subscriptions` | One-per-shop SaaS subscription record |
| `refresh_tokens` | JWT refresh token store |
| `roles` / `permissions` | Spatie permission tables |

### Detailed Schema

#### `shops`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK, auto-increment | |
| name | varchar | NOT NULL | |
| address | varchar | nullable | |
| item_label | varchar | default `'Dress'` | Per-shop label for rental object type |
| banned | boolean | default `false` | Platform-level ban |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `branches`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| name | varchar | NOT NULL | |
| address | varchar | nullable | |
| phone | varchar | nullable | |
| shop_id | bigint | FK → shops.id | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `users`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| first_name | varchar | NOT NULL | |
| last_name | varchar | NOT NULL | |
| email | varchar | UNIQUE, NOT NULL | Used as login credential |
| password | varchar | NOT NULL | Bcrypt hashed |
| banned | boolean | default `false` | |
| last_login | timestamp | nullable | |
| shop_id | bigint | FK → shops.id, nullable | Set for ROLE_SHOP_ADMIN |
| branch_id | bigint | FK → branches.id, nullable | Set for BRANCH_MANAGER / STAFF |
| remember_token | varchar | nullable | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `categories`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| name | varchar | NOT NULL | |
| shop_id | bigint | FK → shops.id | |
| deleted | boolean | default `false` | Soft delete flag |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `items`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| name | varchar | NOT NULL | |
| unique_code | varchar | NOT NULL | Human-readable identifier (e.g. `DR-001`) |
| min_price | decimal(10,2) | default `0` | Minimum rental price |
| has_cleaning_gap | boolean | default `false` | Adds 1-day buffer after return before next booking |
| quantity | integer | default `1` | How many identical units exist |
| description | text | nullable | |
| image_url | varchar | nullable | |
| category_id | bigint | FK → categories.id, nullable | |
| branch_id | bigint | FK → branches.id | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `customers`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| first_name | varchar | NOT NULL | |
| last_name | varchar | NOT NULL | |
| phone_number | varchar | NOT NULL | Primary contact |
| alt_phone_number | varchar | nullable | |
| notes | text | nullable | |
| blacklisted | boolean | default `false` | |
| blacklist_reason | text | nullable | |
| blacklisted_at | timestamp | nullable | |
| deleted | boolean | default `false` | Soft delete |
| shop_id | bigint | FK → shops.id | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `bookings`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| invoice_number | varchar | UNIQUE | Format: `INV-XXXXXXXX` |
| booking_date | date | NOT NULL | Pickup date |
| return_date | date | NOT NULL | Expected return date |
| first_name | varchar | NOT NULL | Customer name snapshot |
| last_name | varchar | NOT NULL | |
| phone_number | varchar | NOT NULL | |
| alt_phone_number | varchar | nullable | |
| booking_type | varchar | default `'CUSTOMER'` | `CUSTOMER` or `MAINTENANCE` |
| status | varchar | default `'CONFIRMED'` | `CONFIRMED`, `PICKED_UP`, `RETURNED`, `CANCELLED` |
| customer_id | bigint | FK → customers.id, nullable | Null for MAINTENANCE bookings |
| total_agreed_price | decimal(10,2) | default `0` | |
| total_advance_payment | decimal(10,2) | default `0` | Amount paid upfront |
| security_deposit | decimal(10,2) | default `0` | Refundable deposit |
| security_deposit_returned | boolean | default `false` | |
| deposit_deduction | decimal(10,2) | nullable | Amount withheld from deposit |
| deposit_deduction_reason | text | nullable | |
| shop_id | bigint | FK → shops.id | |
| branch_id | bigint | FK → branches.id | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `booking_items`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| booking_id | bigint | FK → bookings.id | |
| item_id | bigint | FK → items.id | Physical FK column: `dress_id` (legacy, not renamed) |
| is_returned | boolean | default `false` | Per-item return tracking |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `booking_change_logs`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| booking_id | bigint | FK → bookings.id | |
| changed_by_id | bigint | FK → users.id, nullable | |
| changed_by_name | varchar | nullable | Display name snapshot |
| removed_items_summary | text | nullable | Human-readable list |
| added_items_summary | text | nullable | |
| old_total_price | decimal(10,2) | nullable | |
| new_total_price | decimal(10,2) | nullable | |
| new_advance_payment | decimal(10,2) | nullable | |
| additional_payment | decimal(10,2) | nullable | Extra payment recorded at change time |
| date_change_summary | text | nullable | Description of date changes |
| notes | text | nullable | Staff notes |
| changed_at | timestamp | default `NOW()` | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `audit_logs`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| entity_type | varchar | NOT NULL | e.g. `Booking`, `Customer`, `Item` |
| entity_id | bigint | nullable | ID of the affected entity |
| action | varchar | NOT NULL | e.g. `BOOKING_CREATED`, `CUSTOMER_BLACKLISTED` |
| performed_by | varchar | nullable | Actor's email (immutable identifier) |
| performed_by_name | varchar | nullable | Actor's display name at time of action |
| shop_id | bigint | nullable | |
| branch_id | bigint | nullable | |
| details | text | nullable | JSON or free-text detail string |
| timestamp | timestamp | default `NOW()` | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

#### `item_blocks`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| item_id | bigint | FK → items.id | |
| branch_id | bigint | FK → branches.id | |
| shop_id | bigint | NOT NULL | |
| start_date | date | NOT NULL | |
| end_date | date | NOT NULL | |
| quantity | unsigned int | default `1` | How many units are blocked |
| reason | varchar | nullable | |
| created_by | varchar | nullable | Actor email |
| created_at | timestamp | | |
| updated_at | timestamp | | |
| INDEX | (item_id, start_date, end_date) | | For fast availability queries |

#### `subscriptions`
| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigint | PK | |
| shop_id | bigint | FK → shops.id, UNIQUE | One subscription per shop |
| plan_name | varchar | default `'Basic'` | |
| amount_paid | decimal(10,2) | default `0` | |
| start_date | date | NOT NULL | |
| end_date | date | NOT NULL | |
| max_branches | integer | default `1` | |
| is_trial | boolean | default `false` | |
| is_suspended | boolean | default `false` | |
| notes | text | nullable | |
| renewed_at | timestamp | nullable | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

---

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                   │
│   SHOPS ─────────────── has one ──────────────── SUBSCRIPTIONS   │
│     │                                                             │
│     ├─── has many ──── BRANCHES                                  │
│     │                      │                                      │
│     │                      ├─── has many ──── USERS              │
│     │                      │                                      │
│     │                      ├─── has many ──── ITEMS              │
│     │                      │                      │               │
│     │                      │                      └── ITEM_BLOCKS │
│     │                      │                                      │
│     │                      └─── has many ──── BOOKINGS           │
│     │                                              │               │
│     ├─── has many ──── USERS                       ├── BOOKING_ITEMS
│     │                                              │       │       │
│     ├─── has many ──── CUSTOMERS ──── has many ────┘       └─ ITEMS
│     │                                                              │
│     ├─── has many ──── CATEGORIES ── has many ──── ITEMS          │
│     │                                                              │
│     └─── has many ──── AUDIT_LOGS                                 │
│                                                                    │
│   BOOKINGS ─── has many ─── BOOKING_CHANGE_LOGS                  │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
```

---

## Models & Relationships

### Shop
```php
hasOne(Subscription::class)
hasMany(Branch::class)
hasMany(User::class)
hasMany(Customer::class)
hasMany(Booking::class)
hasMany(Category::class)
```

### Branch
```php
belongsTo(Shop::class)
hasMany(User::class)
hasMany(Item::class)
hasMany(Booking::class)
```

### User
```php
belongsTo(Shop::class)   // nullable — set for ROLE_SHOP_ADMIN
belongsTo(Branch::class) // nullable — set for BRANCH_MANAGER / STAFF
// Traits: JWTSubject, HasRoles (Spatie)
// Appended: enabled (= !banned)
```

### Booking
```php
belongsTo(Shop::class)
belongsTo(Branch::class)
belongsTo(Customer::class)  // nullable (null for MAINTENANCE type)
hasMany(BookingItem::class)
hasMany(BookingChangeLog::class, 'booking_id')
// Appended: balance_due = total_agreed_price - total_advance_payment
```

### BookingItem
```php
belongsTo(Booking::class)
belongsTo(Item::class)   // FK column name: dress_id (legacy, not renamed)
```

### Item
```php
belongsTo(Branch::class)
belongsTo(Category::class)
hasMany(BookingItem::class)
hasMany(ItemBlock::class)
```

### Customer
```php
belongsTo(Shop::class)
hasMany(Booking::class)
```

### Subscription
```php
belongsTo(Shop::class)
// Computed status: SUSPENDED → EXPIRED → GRACE_PERIOD → EXPIRING_SOON → ACTIVE
// isAccessAllowed(): returns false if SUSPENDED or past GRACE_PERIOD
```

---

## API Reference

All endpoints are prefixed with the server base URL. Authenticated endpoints require `Authorization: Bearer <token>`.

### Public

| Method | Path | Description |
|--------|------|-------------|
| POST | `/login` | Authenticate, returns JWT + refresh token |
| POST | `/auth/refresh-token` | Exchange refresh token for new access token |

### Auth (any authenticated user)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/logout` | Revoke refresh token |
| GET | `/me` | Get current user profile |
| PUT | `/api/users/me` | Update own name, email, or password |

### Bookings (all shop users)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/bookings/create-invoice` | Create new booking |
| GET | `/api/bookings` | List bookings (filterable by status, date, branch) |
| GET | `/api/bookings/dashboard` | Dashboard summary stats |
| GET | `/api/bookings/today-pickups` | Bookings scheduled for pickup today |
| GET | `/api/bookings/due-today` | Bookings due for return today |
| GET | `/api/bookings/overdue` | Overdue bookings |
| GET | `/api/bookings/maintenance-blocks` | Active maintenance blocks |
| GET | `/api/bookings/search/customer/{phone}` | Find bookings by customer phone |
| GET | `/api/bookings/history/{code}` | Booking history by invoice number |
| GET | `/api/bookings/customer/{customerId}` | All bookings for a customer |
| POST | `/api/bookings/set-unavailable` | Create maintenance block (param: `dressId`) |
| DELETE | `/api/bookings/make-available` | Remove maintenance block (param: `dressId`) |
| POST | `/api/bookings/items/{itemId}/return` | Mark individual item as returned |
| GET | `/api/bookings/{id}` | Get booking detail |
| PUT | `/api/bookings/{id}` | Update booking (price, dates, notes) |
| DELETE | `/api/bookings/{id}` | Cancel booking |
| POST | `/api/bookings/{id}/status` | Update booking status |
| POST | `/api/bookings/{id}/pay` | Record additional payment |
| POST | `/api/bookings/{id}/pickup` | Mark booking as picked up |
| POST | `/api/bookings/{id}/complete-return` | Complete return with deposit settlement |
| GET | `/api/bookings/{id}/invoice/pdf` | Download invoice as PDF |
| GET | `/api/bookings/{id}/change-logs` | View change history |
| PATCH | `/api/bookings/{id}/change-items` | Swap items in an existing booking |

### Customers (all shop users)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/customers` | List customers |
| POST | `/api/customers` | Create customer |
| GET | `/api/customers/{id}` | Get customer |
| PUT | `/api/customers/{id}` | Update customer |
| DELETE | `/api/customers/{id}` | Soft delete customer |
| GET | `/api/customers/autocomplete` | Search by name/phone (typeahead) |
| GET | `/api/customers/phone/{phone}` | Look up by phone |
| GET | `/api/customers/blacklisted` | Blacklisted customers |
| GET | `/api/customers/{id}/stats` | Booking stats for customer |
| POST | `/api/customers/{id}/blacklist` | Blacklist customer |
| POST | `/api/customers/{id}/unblacklist` | Remove from blacklist |

### Items (all shop users)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/items/add` | Add item to branch inventory |
| GET | `/api/items/all` | List all items in shop |
| GET | `/api/items/search/{code}` | Find item by unique code |
| GET | `/api/items/{id}/detail` | Item detail with availability |
| GET | `/api/items/{id}/history` | Booking history for item |
| PUT | `/api/items/{id}` | Update item |
| DELETE | `/api/items/{id}` | Delete item |
| POST | `/api/items/{id}/image` | Upload item image |

### Categories (admin / branch manager)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/categories` | List categories |
| POST | `/api/categories` | Create category |
| PUT | `/api/categories/{id}` | Update category |
| DELETE | `/api/categories/{id}` | Delete category |

### Reports (admin / branch manager)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/reports/outstanding-balances` | Bookings with unpaid balance |
| GET | `/api/reports/revenue/daily` | Revenue for a specific date |
| GET | `/api/reports/revenue/monthly` | Revenue for a month |
| GET | `/api/reports/revenue/trend` | Rolling N-month trend |
| GET | `/api/reports/revenue/by-item` | Revenue breakdown per item |
| GET | `/api/reports/branch-comparison` | Side-by-side branch revenue |
| GET | `/api/reports/receivables-aging` | Outstanding balances by age buckets |
| GET | `/api/reports/year-over-year` | Month-by-month comparison across two years |
| GET | `/api/reports/inventory-utilization` | % of available days each item was booked |

### Audit Logs

| Method | Path | Roles | Description |
|--------|------|-------|-------------|
| GET | `/api/audit-logs` | Admin / Manager | Paginated log for own shop |
| GET | `/api/audit-logs/today-summary` | Admin / Manager | Today's activity grouped by action and staff |
| GET | `/api/audit-logs/export` | Admin / Manager | CSV export |
| GET | `/api/audit-logs/mine` | Any auth | Own activity |
| GET | `/api/audit-logs/entity/{type}/{id}` | Any auth | Logs for a specific entity |
| GET | `/api/audit-logs/all` | Super Admin | All logs across all shops |

### Shop Admin

| Method | Path | Description |
|--------|------|-------------|
| POST | `/register-admin` | Register new staff/manager under own shop |
| GET | `/shop/staff` | List all staff in shop |
| PUT | `/shop/staff/{id}/ban` | Ban staff account |
| PUT | `/shop/staff/{id}/unban` | Unban staff account |
| PUT | `/shop/staff/{id}/reset-password` | Reset staff password |
| CRUD | `/api/branches` | Branch management |

### Super Admin

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/setup-shop` | Create new shop + admin + subscription |
| GET/PUT/DELETE | `/api/shops/{id}` | Shop management |
| PUT | `/api/shops/{id}/ban` | Ban shop (disables all logins) |
| PUT | `/api/shops/{id}/unban` | Unban shop |
| GET | `/api/users` | All users across platform |
| PUT | `/api/users/{id}/ban` | Ban any user |
| GET | `/api/analytics` | Platform-wide analytics |
| CRUD | `/api/subscriptions` | Subscription management |
| PUT | `/api/subscriptions/{id}/renew` | Renew subscription |
| PUT | `/api/subscriptions/{id}/suspend` | Suspend subscription |
| POST | `/api/register` | Register super admin account |

---

## Services Layer

### BookingService

The primary booking orchestration service.

**`generateInvoiceNumber(): string`**
Generates a unique invoice number in the format `INV-XXXXXXXX` using random hex. Retries on collision.

**`checkConflicts(array $itemIds, string $bookingDate, string $returnDate, ?int $excludeBookingId): array`**
Checks whether any of the given items are unavailable in the requested date range.
- Queries `booking_items` joined with `bookings` (excluding CANCELLED)
- Expands dates by 1 day for items where `has_cleaning_gap = true`
- Returns array of conflicting item codes/names

**`createInvoice(array $data, int $shopId, int $branchId, string $performedBy): Booking`**
Full booking creation transaction:
1. Auto-creates or looks up `Customer` record by phone number
2. Generates unique invoice number
3. Creates `Booking` record
4. Creates `BookingItem` records for each item
5. Writes audit log entry

### AuditLogService

**`log(string $entityType, ?int $entityId, string $action, string $performedBy, ?int $shopId, ?int $branchId, ?string $details, ?string $performedByName): void`**

Creates a single `AuditLog` record. Called from every controller that mutates data. The `performedByName` parameter stores the actor's display name at the time of the action (so name changes don't rewrite history).

### PdfService

Wraps `barryvdh/laravel-dompdf` to render the invoice Blade template (`resources/views/pdf/invoice.blade.php`) and return a PDF response. Called from `BookingController::downloadInvoice()`.

### RefreshTokenService

Manages the `refresh_tokens` table:
- `create(int $userId): string` — generates a random token, stores hashed
- `validate(string $token): ?RefreshToken` — verifies token, checks expiry
- `revoke(string $token): void` — deletes token on logout

---

## Middleware

### JwtMiddleware

Runs on every protected route.

1. Extracts `Authorization: Bearer <token>` header
2. Calls `JWTAuth::parseToken()->authenticate()`
3. If token is expired → returns 401 `{ message: "Token has expired" }`
4. If token is invalid → returns 401 `{ message: "Token is invalid" }`
5. If user not found → returns 404
6. **Checks `user->banned`** → returns 403 `{ message: "Account is banned" }`
7. Sets authenticated user in Laravel's auth guard

### RoleMiddleware

Applied to route groups with a `roles` parameter (comma-separated).

1. Checks user is authenticated
2. Calls `user->hasAnyRole([...roles])` (Spatie)
3. Returns 403 if role check fails

Example in routes:
```php
Route::middleware(['jwt.auth', 'role:ROLE_SHOP_ADMIN'])->group(function () { ... });
```

---

## Business Logic & Workflows

### Booking Lifecycle

```
CONFIRMED
    │
    ├── [staff marks pickup] ──→ PICKED_UP
    │                                │
    │                                └── [staff completes return] ──→ RETURNED
    │
    └── [staff cancels] ──────────→ CANCELLED (from any state)
```

- **CONFIRMED**: Booking exists, items are reserved, customer has not picked up yet.
- **PICKED_UP**: Items are out. Individual `booking_items.is_returned` can be updated per-item as pieces come back.
- **RETURNED**: All items returned. Security deposit settlement happens here — any deduction is recorded.
- **CANCELLED**: Booking is void. Cancelled bookings do **not** block item availability.

### Item Availability Check

When creating or modifying a booking the system checks availability:

```
for each requested item:
  bookedCount = COUNT(booking_items)
    JOIN bookings WHERE status != 'CANCELLED'
    AND date ranges overlap
    AND item_id matches

  if has_cleaning_gap:
    expand check window by ±1 day

  if bookedCount >= item.quantity:
    → conflict
```

The SQL `IS NULL OR status <> 'CANCELLED'` pattern is used intentionally — plain `status <> 'CANCELLED'` in SQL evaluates NULL to NULL (not TRUE), which would silently pass NULL-status MAINTENANCE blocks through.

### Maintenance Blocks

A `MAINTENANCE` booking is a synthetic booking with:
- `booking_type = 'MAINTENANCE'`
- `status = 'CONFIRMED'` (so it blocks availability)
- No customer attached
- Date range represents the unavailability window

The availability check treats MAINTENANCE bookings identically to CUSTOMER bookings.

### Cleaning Gap

If `item.has_cleaning_gap = true`, the system adds 1 buffer day after each return before the item can be booked again. This is done by shifting the availability check window by one day when querying conflicts.

### Security Deposit Settlement

When completing a return (`POST /api/bookings/{id}/complete-return`):
- Staff can specify `deposit_deduction` and `deposit_deduction_reason`
- `security_deposit_returned` is set to true if no deduction
- `balance_due` is recalculated and returned in the response

---

## Audit Logging

Every create, update, delete, and status change action writes a record to `audit_logs`. The pattern in every controller:

```php
// actorName() helper (defined on each controller)
private function actorName(): string {
    $u = auth('api')->user();
    return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email;
}

// Example call
$this->audit->log(
    'Booking',          // entity_type
    $booking->id,       // entity_id
    'BOOKING_CREATED',  // action
    auth('api')->user()->email,  // performed_by (immutable)
    $shopId,
    $branchId,
    json_encode($details),
    $this->actorName()  // performed_by_name (display name snapshot)
);
```

When reading audit logs, `AuditLogController::nameMap()` resolves names from the `users` table for older records that pre-date the `performed_by_name` column, ensuring every log always displays a name.

---

## PDF Generation

Invoice PDFs are generated with `barryvdh/laravel-dompdf`:

- Template: `resources/views/pdf/invoice.blade.php`
- Triggered by: `GET /api/bookings/{id}/invoice/pdf`
- Returns: `application/pdf` response with `Content-Disposition: attachment`
- Data passed: booking, items, customer info, shop name, formatted dates

---

## Error Handling

All validation and business rule errors follow a consistent JSON shape:

```json
{
  "timestamp": "2026-06-25T10:30:00.000000",
  "status": 400,
  "error": "Business Rule Violation",
  "message": "human-readable explanation"
}
```

- **400** — validation failure or business rule violation (e.g. advance payment exceeds agreed price)
- **401** — invalid or expired token
- **403** — banned account or insufficient role
- **404** — resource not found
- **500** — unhandled server error

---

## Multi-tenancy Model

RentFlow uses a **shared database, shared schema** multi-tenancy approach. Tenant isolation is enforced at the application layer — every query is scoped to the authenticated user's `shop_id`.

- Every data-owning table has a `shop_id` column
- Every controller derives `shopId` from the authenticated user
- No cross-shop data leakage is possible as long as `shopId()` is called on every query
- Super admin bypasses this isolation intentionally to manage all shops

Branch-level isolation is an additional layer — a `ROLE_BRANCH_MANAGER` or `ROLE_STAFF` can only see their own branch's data within their shop.
