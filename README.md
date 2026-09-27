# GymSathi API (v1)

Backend for `https://api.gymsathi.in/v1/`. Plain PHP 8, MySQL/MariaDB, runs on Hostinger shared hosting.
It replaces `https://gymsathi.in/backend/api/`, which stays online for old app versions. Both share the same database.

---

## 1. Folder structure

```
GymSathi API/                  upload to  domains/gymsathi.in/api/   (NOT inside public_html)
├── public/                    ← document root of api.gymsathi.in — the only web-reachable folder
│   ├── index.php              health check
│   ├── uploads/               logos + member photos (.htaccess blocks script execution)
│   └── v1/<resource>/<action>.php
├── app/
│   ├── bootstrap.php          loaded first everywhere: config, errors, autoload, CORS
│   ├── Http/                  request.php (input/query/page), response.php, validate.php
│   ├── Auth/                  tokens.php (JWT), guards.php (requireUser/Gym/…), GoogleAuth
│   ├── Support/               db.php, dates.php, RateLimit, Uploads, Period, Mailer
│   ├── Services/              business logic (one class per area, see §3)
│   └── Exceptions/            HttpException + Validation/Unauthorized/Forbidden/NotFound/Conflict
├── config/                    secrets.php (git-ignored), secrets.example.php, service-account.json
├── cron/run.php               command-line cron runner
├── migrations/                SQL, run by hand in order
├── storage/                   logs/, ratelimit/, backups/ (not web-reachable)
└── tests/                     smoke.sh (read-only endpoint test), make-token.php
```

## 2. Conventions (read before adding an endpoint)

**URLs:** `v1/<resource>/<action>.php`
- Folder = resource (noun, plural). File = action (verb) from a fixed list:
  `list`, `show`, `create`, `update`, `delete`, plus named actions (`check-in`, `set-status`, `import`, `mark-read`, `*-order`, `*-verify`).
- **One file = one action = one HTTP method.** Reads are `GET` (params in the query string), everything that changes data is `POST` (JSON body, or multipart when a file is uploaded).
- Audience by top folder: owner/staff app (default), `member-app/`, `admin/`, `signup/` (public).

**Every endpoint has the same shape:**

```php
<?php
// POST { gym_id, title, amount, expense_date, category?, description? }
require __DIR__ . '/../../../app/bootstrap.php';
requireMethod('POST');

$user = requireUser();                                     // who (from the token)
$gym  = requireGym($user, input('gym_id'), 'manage');      // may they, in this gym
$data = validate(input(), [                                // clean input
    'title' => 'required|string|maxlen:255',
    'amount' => 'required|numeric|min:0.01',
    'expense_date' => 'required|date',
]);

// do the work: inline SQL for single-table CRUD, a Service for anything bigger
dbRun("INSERT INTO expenses (...) VALUES (...)", [...]);

sendSuccess(['id' => (int)db()->lastInsertId()], "Expense added");
```

**Rules**
- Read input only with `input()` (POST body), `query()` (GET), `uploadedFile()`, `page()`.
- Errors: `throw new ValidationException("…")` / `NotFoundException` / `ForbiddenException` / `ConflictException`. Bootstrap turns them into the JSON error and rolls back any open transaction. Services never send responses.
- DB: `dbOne()`, `dbAll()`, `dbValue()`, `dbRun()`, `dbTransaction(fn)`. Always bound parameters.
- Anything used by more than one endpoint, or touching several tables, goes in a Service.
- Tables link to a gym by its **code** (`members.gym_id = 'GYM007'`); `user_gym_roles` and `notifications` use the numeric `gyms.id`. `requireGym()` returns both (`$gym['gym_id']`, `$gym['id']`).
- Dates: business dates use `today()` (app timezone). Attendance timestamps are UTC (`utcNow()`, `utcToday()`, returned as `…Z` via `isoUtc()`).

## 3. Services

| Service | Responsibility |
|---|---|
| `UserService` | owner/staff accounts, legacy-owner adoption, login response |
| `GymService` | gym lookup/profile, **the only place gyms are created**, free trial (once per owner) |
| `StaffService` | staff roles/permissions in `user_gym_roles` |
| `MemberService` | member CRUD, list filters (shared with reports), bulk import |
| `AttendanceService` | daily list, history, check-in/out |
| `ReportService` | dashboard summary, daily report, charts, exports/CSV |
| `PaymentService` + `RazorpayClient` | online payments: gym plans, app plans, paid signup |
| `OtpService` | OTP send/verify (reset, email verify, member login) |
| `NotificationService` + `FcmClient` | saved notifications + push to owner/manager devices |
| `CronService` | scheduled jobs |

## 4. Response format

Every response:
```json
{ "status": "success" | "error", "message": "text to show the user", "data": … }
```
Paginated lists: `data = { "items": [...], "pagination": { "total", "page", "limit", "total_pages" } }`
Query params `page` (default 1) and `limit` (default 20, max 500 for app lists, 100 for admin).

| HTTP | Meaning |
|---|---|
| 200 | OK |
| 400 | invalid input (message says what) |
| 401 | not logged in / token expired → app should log out |
| 403 | logged in but not allowed |
| 404 | not found |
| 405 | wrong HTTP method |
| 409 | conflict (duplicate phone, plan in use, payment already processed) |
| 429 | too many attempts |
| 500 | server error (details only in `storage/logs/php-error.log`) |

## 5. Authentication & permissions

| Caller | Login | Token (`Authorization: Bearer …`) |
|---|---|---|
| Owner / staff | `auth/login`, `auth/login-google`, `signup/register` | `user`, 30 days |
| Member | `member-app/login-otp` → `member-app/login` | `member`, 30 days |
| Super admin | `admin/login` | `admin`, 1 day (also `admin_token` cookie) |

Identity comes **only** from the token. Access to a gym comes **only** from `user_gym_roles`.

| Ability (in `requireGym`) | owner | manager | trainer / receptionist / staff |
|---|---|---|---|
| any role (`null`) | ✅ | ✅ | ✅ |
| `members.read` / `members.write` | ✅ | ✅ | if permission toggle on |
| `plans.read` / `plans.write` | ✅ | ✅ | if permission toggle on |
| `batches.read` / `batches.write` | ✅ | ✅ | if permission toggle on |
| `manage` (money, reports, staff list, gym settings) | ✅ | ✅ | ❌ |
| `owner` (staff changes, delete gym) | ✅ | ❌ | ❌ |

Staff permissions JSON (same as the app): `{"members":{"read":true,"write":false},"plans":{…},"batches":{…}}`. `write` implies `read`.
`reports/summary` works for everyone, but money fields are only included for owner/manager.

## 6. Endpoints and Flutter mapping

Old base: `https://gymsathi.in/backend/api` → new base: `https://api.gymsathi.in/v1`.
All updates/deletes are now `POST` and use named ids (`member_id`, `plan_id`, `batch_id`, `expense_id`) instead of `id`.

### auth/ — owner & staff account
| New | Method | Params | Old | Changes for the app |
|---|---|---|---|---|
| `auth/login.php` | POST | email, password | auth/login-email.php | token is a JWT; same response fields |
| `auth/login-google.php` | POST | **id_token** | auth/login-google.php | send `account.authentication.idToken`; response has `registered: true/false` |
| `auth/forgot-password.php` | POST | email | auth/send-otp.php | |
| `auth/reset-password.php` | POST | email, otp, new_password | auth/reset-password.php | `phone` no longer needed |
| `auth/me.php` | GET | — | auth/profile.php | returns the user + `gyms` |
| `auth/update-profile.php` | POST | name?, phone?, email? | (gym/update email/owner_name) | new; returns login payload |
| `auth/change-password.php` | POST | current_password, new_password | (gym/update password) | new |

### signup/ — new owners
| New | Method | Params | Old | Changes |
|---|---|---|---|---|
| `signup/send-email-otp.php` | POST | email | auth/send-verification-otp.php | 409 if email already registered |
| `signup/verify-email-otp.php` | POST | email, otp | auth/verify-email-otp.php | |
| `signup/register.php` | POST multipart | gym_name, owner_name, email+password **or** id_token, phone?, address?, logo? | gym/register.php | **returns the login payload** (user, gyms, token) |
| `signup/paid-order.php` | POST | plan_id, email, owner_name | payments/razorpay/checkout-order.php | order is in `data` |
| `signup/paid-verify.php` | POST | razorpay_*, owner_name, email, password, phone?, gym_name?, address?, district?, state?, pincode? | payments/razorpay/checkout-verify.php | |

### gyms/, staff/
| New | Method | Params | Old | Changes |
|---|---|---|---|---|
| `gyms/list.php` | GET | — | (login response `gyms`) | gym switcher list |
| `gyms/show.php` | GET | gym_id | gym/profile.php (GET) | adds `your_role`; starts trial if needed |
| `gyms/create.php` | POST multipart | gym_name, phone?, address?, logo? | gym/create.php | no `user_id`/`owner_name` |
| `gyms/update.php` | POST multipart | gym_id, gym_name?, phone?, address?, logo? | gym/update.php, gym/profile.php (PUT) | owner name/email/password moved to `auth/` |
| `gyms/delete.php` | POST | gym_id | gym/delete.php | no `user_id` |
| `staff/list.php` | GET | gym_id | gym/get_staff.php | |
| `staff/create.php` | POST | gym_ids[], email, name, phone?, password?, role?, permissions? | gym/add_staff.php | |
| `staff/update.php` | POST | gym_id, user_id, role, permissions? | — | new |
| `staff/delete.php` | POST | gym_id, user_id | gym/remove_staff.php | |

### members/, batches/, gym-plans/
| New | Method | Params | Old | Changes |
|---|---|---|---|---|
| `members/list.php` | GET | gym_id, filter?, search?, page?, limit? | members/plans.php, members/manage.php (GET list) | list is `data.items` (was `data.data`); filter `no_plan` (old `no_plans`/`no_purchase` still accepted) |
| `members/show.php` | GET | gym_id, member_id | members/manage.php (GET id) | |
| `members/create.php` | POST json/multipart | gym_id, name, phone, email?, start_date?, expiry_date?, batch_id?, plan_id?, status?, profile_image? | members/manage.php (POST) | 409 on duplicate phone |
| `members/update.php` | POST json/multipart | gym_id, **member_id**, any field | members/manage.php (POST/PUT with id) | returns the updated member |
| `members/set-status.php` | POST | gym_id, member_id, status (active/blocked/inactive) | members/block.php | |
| `members/delete.php` | POST | gym_id, member_id | members/manage.php (DELETE) | |
| `members/import.php` | POST | gym_id, members[] | members/bulk-upload.php | |
| `batches/list.php` | GET | gym_id | gym/batches.php (GET) | |
| `batches/create.php` | POST | gym_id, batch_name, start_time, end_time | gym/batches.php (POST) | |
| `batches/update.php` | POST | gym_id, **batch_id**, … | gym/batches.php (PUT) | |
| `batches/delete.php` | POST | gym_id, batch_id | gym/batches.php (DELETE) | |
| `gym-plans/list.php` | GET | gym_id | gym/plans.php (GET) | |
| `gym-plans/create.php` | POST | gym_id, plan_name, duration_months, price, description? | gym/plans.php (POST) | |
| `gym-plans/update.php` | POST | gym_id, **plan_id**, … | gym/plans.php (PUT) | |
| `gym-plans/delete.php` | POST | gym_id, plan_id | gym/plans.php (DELETE) | 409 if members use it |

### attendance/
| New | Method | Params | Old | Changes |
|---|---|---|---|---|
| `attendance/daily.php` | GET | gym_id, date?, filter?, search?, page?, limit? | members/attendance.php, attendance/checkin.php (GET list) | `data.items` + `data.date` |
| `attendance/history.php` | GET | gym_id, member_id | attendance/checkin.php (GET member_id) | |
| `attendance/check-in.php` | POST | gym_id, member_id, date? | attendance/checkin.php (POST) | |

### payments/, expenses/, app-plans/
| New | Method | Params | Old | Changes |
|---|---|---|---|---|
| `payments/list.php` | GET | gym_id, member_id?, page?, limit? | payments/list.php (GET) | paginated |
| `payments/create.php` | POST | gym_id, member_id, amount, payment_method?, transaction_id? | payments/list.php (POST) | |
| `payments/online-order.php` | POST | gym_id, member_id, plan_id | create-order.php (type=gym_plan) | order in `data` |
| `payments/online-verify.php` | POST | razorpay_order_id, razorpay_payment_id, razorpay_signature | verify-payment.php | only these 3 fields |
| `expenses/list.php` | GET | gym_id, from_date?, to_date? | payments/expenses.php (GET) | |
| `expenses/show.php` | GET | gym_id, expense_id | payments/expenses.php (GET id) | |
| `expenses/create.php` | POST | gym_id, title, amount, expense_date, category?, description? | payments/expenses.php (POST) | |
| `expenses/update.php` | POST | gym_id, **expense_id**, … | payments/expenses.php (PUT) | |
| `expenses/delete.php` | POST | gym_id, expense_id | payments/expenses.php (DELETE) | |
| `app-plans/list.php` | GET | gym_id? | subscriptions/plans.php | |
| `app-plans/history.php` | GET | gym_id | subscriptions/history.php | |
| `app-plans/purchase-order.php` | POST | gym_id, plan_id | create-order.php (type=app_plan) | order in `data` |
| `app-plans/purchase-verify.php` | POST | razorpay_order_id, razorpay_payment_id, razorpay_signature | verify-payment.php (type=app_plan) | only these 3 fields |

### reports/, notifications/, devices/
| New | Method | Params | Old | Changes |
|---|---|---|---|---|
| `reports/summary.php` | GET | gym_id | stats/general.php | `no_purchase_members` → `no_plan_members`; money fields owner/manager only |
| `reports/daily.php` | GET | gym_id, date? | reports/dashboard.php | flat object (was `today`/`overall` nested); standard `status` response |
| `reports/chart.php` | GET | gym_id, type=revenue\|attendance | stats/reports.php (chart mode) | |
| `reports/export.php` | GET | gym_id, type=members\|payments\|expenses\|attendance\|expiring, format=json\|csv, start_date?, end_date? | stats/reports.php | type names: member→members, collection/revenue→payments, expense→expenses, expiry→expiring |
| `notifications/list.php` | GET | gym_id | notifications/get-all.php | |
| `notifications/mark-read.php` | POST | gym_id, notification_id **or** all: true | notifications/mark-read.php | `mark_all` → `all` |
| `devices/register.php` | POST | token, platform? | fcm/update.php | no gym_id; call after each login |
| `devices/unregister.php` | POST | token | — | new; call on logout |

### member-app/ — member's own app
| New | Method | Params | Old | Changes |
|---|---|---|---|---|
| `member-app/login-otp.php` | POST | email | member/send-otp.php | |
| `member-app/login.php` | POST | email, otp | member/login.php | token is a JWT |
| `member-app/me.php` | GET | — | member/profile.php | no member_id |
| `member-app/attendance.php` | GET | — | member/attendance.php | no member_id |
| `member-app/payments.php` | GET | — | member/payments.php | no member_id |
| `member-app/online-order.php` | POST | plan_id | — | member pays own plan |
| `member-app/online-verify.php` | POST | razorpay_order_id, razorpay_payment_id, razorpay_signature | — | |

### admin/ — super admin panel
| New | Method | Old / Purpose |
|---|---|---|
| `admin/login.php` | POST username, password | admin/login.php |
| `admin/logout.php` | POST | admin/logout.php |
| `admin/me.php` | GET | profile of authenticated admin |
| `admin/dashboard.php` | GET | stats overview (summary KPIs) |
| `admin/stats.php` | GET | alias for stats |
| `admin/period-stats.php` | GET period, from?, to? | admin/period-stats.php (`active_plans` → `subscriptions_sold`) |
| `admin/gyms/list.php` | GET filter?, period?, search?, page?, limit? | admin/gyms.php (GET) — `owner_email` instead of `email` |
| `admin/gyms/show.php` | GET gym_id | admin/gym-details.php (`gym_info` → `gym`, + `owner`, `staff`) |
| `admin/gyms/create.php` | POST gym_name, owner_name, email, phone?, address? | admin/gyms.php (POST) |
| `admin/gyms/update-status.php` | POST gym_id, status | admin/gyms.php (PUT status) |
| `admin/gyms/end-trial.php` | POST gym_id | admin/gyms.php (PUT action=end_trial) |
| `admin/members/list.php` | GET gym_id?, filter?, period?, search?, page?, limit? | admin/members.php |
| `admin/staff/list.php` | GET gym_id? | admin/get_all_staff.php, gym/get_staff.php |
| `admin/staff/create.php` | POST gym_ids[], email, name, … | gym/add_staff.php |
| `admin/staff/delete.php` | POST gym_id, user_id | gym/remove_staff.php |
| `admin/staff/permissions.php` | POST user_id, gym_assignments[] | admin/update_staff_permissions.php |
| `admin/app-plans/list\|create\|update\|delete.php` | GET / POST (`plan_id` for update/delete) | admin/app-plans.php |
| `admin/subscriptions/list.php` | GET filter?, period?, page?, limit? | admin/gym-subscriptions.php |
| `admin/subscriptions/renew.php` | POST gym_id, plan_id, duration_months, amount?, notes? | manual subscription extension |
| `admin/users/list.php` | GET filter?, search?, page?, limit? | list owner/staff user accounts |
| `admin/users/impersonate.php` | POST user_id | admin temporary login token generation |
| `admin/notifications/send.php` | POST gym_id, title, message, … | notifications/create.php |
| `admin/notifications/send-push.php` | POST token, title, body | notifications/send-fcm-notification.php |
| `admin/notifications/broadcast.php` | POST title, message | platform-wide owner push broadcast |
| `admin/notifications/broadcast-daily.php` | POST date?, gym_ids? | reports/broadcast-daily-report.php |
| `admin/system/health.php` | GET | database and system health check |
| `admin/system/settings.php` | GET / POST | platform configuration |
| `cron/trigger.php` | POST ?task= (cron secret) | cron/*.php |

Admin lists return `data.items` + `data.pagination` (were `gyms`/`members`/`data` + `pagination`).

## 7. Deploy (Hostinger)

1. **Back up the database.**
2. **Run database migrations in phpMyAdmin in order:**
   - `migrations/001_new_api_prereqs.sql`
   - `migrations/004_member_codes_and_user_trial.sql`
   - `migrations/005_member_numbers.sql`
3. **Subdomain Setup (`api.gymsathi.in`):** 
   - In hPanel → **Subdomains** → create `api`.
   - Check **"Custom folder for subdomain"** and point it directly to the `public/` directory:
     ```text
     public_html/api/public
     ```
     *(Or `domains/gymsathi.in/api/public` depending on your account structure).*
   - **Do NOT delete the `public/` directory!** It ensures `config/secrets.php` and `storage/logs/` remain outside the public web root. If Hostinger forces root to `public_html/api`, the repository's root `.htaccess` will silently route traffic into `public/`.
4. **Permissions & Uploads:**
   - Set `storage/` and `public/uploads/` to writable (`0755`).
   - Copy legacy image uploads into `public/uploads/`.
5. **Configuration (`config/secrets.php`):**
   - Copy `config/secrets.example.php` to `config/secrets.php`.
   - Configure production DB credentials (`db.host`, `db.name`, `db.user`, `db.pass`).
   - *(Note: If any endpoint returns `503 Service temporarily unavailable`, the database connection failed—verify DB credentials in hPanel).*
   - Set long random strings for `jwt_secret` and `cron_secret`.
   - Configure `razorpay` keys, `google_client_ids`, and add admin/web origins to `cors_origins`.
6. **SSL & Verification:**
   - Enable SSL for `api.gymsathi.in`.
   - Verify health check: `curl -i https://api.gymsathi.in/` → `{"status":"success","message":"ok"}`.
7. **Scheduled Cron Jobs (hPanel → Advanced → Cron Jobs):**
   ```
   0 1 * * *  /usr/bin/php /home/<user>/public_html/api/cron/run.php checkMemberExpiry
   5 1 * * *  /usr/bin/php /home/<user>/public_html/api/cron/run.php checkSubscriptions
   0 8 * * *  /usr/bin/php /home/<user>/public_html/api/cron/run.php dailyRevenueReport
   0 3 * * *  /usr/bin/php /home/<user>/public_html/api/cron/run.php systemCleanup
   ```

## 8. Testing

```bash
php -S 127.0.0.1:8099 -t public &
BASE=http://127.0.0.1:8099/v1 \
UT=$(php tests/make-token.php user <owner id>) AT=$(php tests/make-token.php admin <admin id>) \
GYM=<owner's gym code> OTHER=<a gym they can't access> MEMBER=<member id in GYM> \
bash tests/smoke.sh
```
Read-only: only GET endpoints plus POST requests that are rejected before writing. 72 checks.

## 9. Legacy API Retirement

The legacy `backend/` on `gymsathi.in` has been officially retired and removed. All clients (mobile app versions `>= 1.1.0` and the React admin panel) communicate exclusively with `https://api.gymsathi.in/v1/`.
