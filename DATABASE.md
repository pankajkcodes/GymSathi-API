# Database

One MySQL database, shared by the new API and the old API. Changes are in `migrations/`.

## Tables

| Table | For |
|---|---|
| `users` | Gym owner and staff accounts |
| `admins` | GymSathi team accounts (admin panel only) |
| `user_gym_roles` | Which user belongs to which gym, and their role/permissions |
| `gyms` | One row per gym |
| `members` | People who join the gym |
| `plans` | Plans a gym sells to its members |
| `batches` | Gym time slots |
| `attendance` | Member check-ins |
| `payments` | Payments from members |
| `expenses` | Gym expenses |
| `app_plans` | Plans GymSathi sells to gyms |
| `gym_subscriptions` | Which GymSathi plan a gym has and when it ends |
| `password_resets` | OTPs (reset, email verify, member login) |
| `fcm_tokens` | Device tokens for push notifications |
| `notifications` | Notification history shown in the app |

## Links

- user → gyms through `user_gym_roles`
- gym → members, plans, batches, attendance, payments, expenses
- gym → `gym_subscriptions` → `app_plans`

## Note

- Most tables use the gym code (`gym_id = 'GYM007'`). `user_gym_roles` and `notifications` use the number `gyms.id`. Check which one before writing a query.
- `users.has_used_trial` (0 or 1) tracks whether an account has already consumed its free trial (enforces one trial per account across all gyms).
- `members.member_number` (1, 2, 3, ...) provides a clean sequential number starting from 1 for each gym.
- `members.member_code` (e.g. `'GYM003-M001'`) provides a formatted sequential member ID scoped per gym, alongside the global `id`.
